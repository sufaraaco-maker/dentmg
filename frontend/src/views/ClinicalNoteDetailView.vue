<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import ClinicalNoteStatusChip from '@/components/clinicalNotes/ClinicalNoteStatusChip.vue'
import { useClinicalNotesStore } from '@/stores/clinicalNotes'
import { useAuthStore } from '@/stores/auth'
import { isClinicalNoteError } from '@/services/clinicalNotes'
import { parseServerDateTime } from '@/lib/date'
import { CLINICAL_NOTE_TYPES } from '@/types/clinicalNote'
import type { ClinicalNote } from '@/types/clinicalNote'

/**
 * Clinical Note Detail — mirrors `TreatmentPlanDetailView.vue`'s precedent of "detail is too much
 * content for a tab panel" (design doc §11): SOAP fields need more room than a tab panel gives.
 *
 * Hydrates from `clinicalNotesStore.cache` directly (the same reactive `Map`
 * `PatientClinicalNotesPanel.vue` reads from), falling back to `fetchOne` only when arriving via a
 * direct/bookmarked link with an empty cache — same pattern as Treatment Plan Detail.
 *
 * Draft vs. Signed is the central UX distinction this view exists to make unmistakable (Step 4
 * requirement): editable `Textarea`/`Select` fields while `draft`, switching to plain read-only
 * text once `signed` — never the same input just disabled, so there is no ambiguity about whether
 * a field might still accept input.
 */
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const clinicalNotesStore = useClinicalNotesStore()
const auth = useAuthStore()

const patientId = computed(() => route.params.id as string)
const noteId = computed(() => route.params.noteId as string)

const note = computed(() => clinicalNotesStore.cache.get(noteId.value) ?? null)
const loading = ref(false)
const notFound = ref(false)

// Matches every other write ability in this module (design doc §10): admin + dentist only.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)
const isDraft = computed(() => note.value?.status === 'draft')
const isSigned = computed(() => note.value?.status === 'signed')
const canEdit = computed(() => canWrite.value && isDraft.value)
const canSign = computed(() => canWrite.value && isDraft.value)
const canAddendum = computed(() => canWrite.value && isSigned.value)

async function load() {
  notFound.value = false

  if (!clinicalNotesStore.cache.has(noteId.value)) {
    loading.value = true
    try {
      await clinicalNotesStore.fetchOne(noteId.value)
    } catch {
      notFound.value = true
    } finally {
      loading.value = false
    }
  }
}

watch(noteId, load, { immediate: true })

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

// --- draft edit form ---------------------------------------------------------------------------

function formFromNote(source: ClinicalNote | null) {
  return {
    note_type: source?.note_type ?? 'progress',
    chief_complaint: source?.chief_complaint ?? '',
    subjective: source?.subjective ?? '',
    objective: source?.objective ?? '',
    assessment: source?.assessment ?? '',
    plan: source?.plan ?? '',
  }
}

const form = reactive(formFromNote(note.value))

// Re-syncs the editable form whenever a fresh copy of the note arrives (initial load, or after a
// stale-state resync following a locked-note error) — never overwrites the form with the same
// values the dentist is actively mid-edit on, since this only fires on note identity/reference change.
watch(note, (fresh) => Object.assign(form, formFromNote(fresh)))

const saving = ref(false)

async function saveDraft() {
  if (!note.value) return

  saving.value = true
  try {
    await clinicalNotesStore.update(note.value.id, {
      note_type: form.note_type,
      chief_complaint: form.chief_complaint || null,
      subjective: form.subjective || null,
      objective: form.objective || null,
      assessment: form.assessment || null,
      plan: form.plan || null,
    })
    toast.add({ severity: 'success', summary: t('clinicalNotes.detail.savedDraft'), life: 3000 })
  } catch (err) {
    await handleSaveError(err)
  } finally {
    saving.value = false
  }
}

async function handleSaveError(err: unknown): Promise<void> {
  if (isClinicalNoteError(err)) {
    if (err.code === 'clinical_note_locked') {
      toast.add({ severity: 'error', summary: t('clinicalNotes.detail.lockedEditAttemptError'), life: 4000 })
      // Our editable-form view was a stale approximation of the backend's real state (same class
      // of race as TreatmentPlanActionsBar's identical resync) — refetch so the UI stops offering
      // edits the backend just rejected.
      await clinicalNotesStore.fetchOne(note.value!.id)
    } else {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    }
    return
  }

  toast.add({ severity: 'error', summary: t('clinicalNotes.detail.saveDraftError'), life: 3000 })
}

// --- sign ---------------------------------------------------------------------------------------

const signing = ref(false)

async function sign() {
  if (!note.value) return

  signing.value = true
  try {
    await clinicalNotesStore.sign(note.value.id)
    toast.add({ severity: 'success', summary: t('clinicalNotes.detail.signSuccess'), life: 3000 })
  } catch (err) {
    if (isClinicalNoteError(err) && err.code === 'invalid_clinical_note_operation') {
      toast.add({ severity: 'error', summary: t('clinicalNotes.detail.blankNoteError'), life: 4000 })
    } else if (isClinicalNoteError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else {
      toast.add({ severity: 'error', summary: t('clinicalNotes.detail.signError'), life: 3000 })
    }
  } finally {
    signing.value = false
  }
}

function confirmSign(): void {
  confirm.require({
    message: t('clinicalNotes.detail.confirmSignMessage'),
    header: t('clinicalNotes.detail.confirmSignHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: t('clinicalNotes.detail.sign'),
    rejectLabel: t('common.cancel'),
    accept: sign,
  })
}

// --- addendums ------------------------------------------------------------------------------------

const addendumBody = ref('')
const addingAddendum = ref(false)

async function submitAddendum() {
  if (!note.value || !addendumBody.value.trim()) return

  addingAddendum.value = true
  try {
    await clinicalNotesStore.addAddendum(note.value.id, addendumBody.value.trim())
    addendumBody.value = ''
    toast.add({ severity: 'success', summary: t('clinicalNotes.detail.addendumSaved'), life: 3000 })
  } catch (err) {
    if (isClinicalNoteError(err) && err.code === 'invalid_clinical_note_operation') {
      toast.add({ severity: 'error', summary: t('clinicalNotes.detail.addendumDraftError'), life: 4000 })
    } else if (isClinicalNoteError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else {
      toast.add({ severity: 'error', summary: t('clinicalNotes.detail.addendumSaveError'), life: 3000 })
    }
  } finally {
    addingAddendum.value = false
  }
}

function goToPatient() {
  router.push({ name: 'patient-detail', params: { id: patientId.value } })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
      <Button icon="pi pi-arrow-left" text rounded @click="goToPatient" />
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('clinicalNotes.title') }}
      </h1>
      <ClinicalNoteStatusChip v-if="note" :status="note.status" />
    </div>

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('clinicalNotes.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="note">
      <Card>
        <template #title>{{ t('clinicalNotes.detail.overview') }}</template>
        <template #content>
          <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-surface-500">{{ t('clinicalNotes.list.dentist') }}</dt>
            <dd>{{ note.dentist?.name ?? '—' }}</dd>

            <dt class="text-surface-500">{{ t('clinicalNotes.detail.createdBy') }}</dt>
            <dd>{{ note.created_by?.name ?? '—' }}</dd>

            <dt class="text-surface-500">{{ t('clinicalNotes.list.date') }}</dt>
            <dd dir="ltr" class="text-start">{{ formatDateTime(note.created_at) }}</dd>

            <template v-if="note.signed_at">
              <dt class="text-surface-500">{{ t('clinicalNotes.detail.signedBy') }}</dt>
              <dd>{{ note.signed_by?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('clinicalNotes.detail.signedAt') }}</dt>
              <dd dir="ltr" class="text-start">{{ formatDateTime(note.signed_at) }}</dd>
            </template>
          </dl>
        </template>
      </Card>

      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>{{ t('clinicalNotes.title') }}</span>
            <Button
              v-if="canSign"
              :label="t('clinicalNotes.detail.sign')"
              icon="pi pi-verified"
              severity="success"
              size="small"
              :loading="signing"
              @click="confirmSign"
            />
          </div>
        </template>
        <template #content>
          <p v-if="isSigned" class="mb-4 text-xs text-surface-500 dark:text-surface-400">
            <i class="pi pi-lock me-1" />{{ t('clinicalNotes.detail.lockedNote') }}
          </p>

          <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.dialog.noteType')
              }}</label>
              <Select
                v-if="canEdit"
                v-model="form.note_type"
                :options="CLINICAL_NOTE_TYPES"
                :option-label="(value: string) => t(`clinicalNotes.type.${value}`)"
                fluid
              />
              <p v-else class="text-sm">{{ t(`clinicalNotes.type.${note.note_type}`) }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.detail.chiefComplaint')
              }}</label>
              <Textarea v-if="canEdit" v-model="form.chief_complaint" rows="2" auto-resize maxlength="1000" />
              <p v-else class="whitespace-pre-wrap text-sm">{{ note.chief_complaint || '—' }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.detail.subjective')
              }}</label>
              <Textarea v-if="canEdit" v-model="form.subjective" rows="3" auto-resize maxlength="10000" />
              <p v-else class="whitespace-pre-wrap text-sm">{{ note.subjective || '—' }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.detail.objective')
              }}</label>
              <Textarea v-if="canEdit" v-model="form.objective" rows="3" auto-resize maxlength="10000" />
              <p v-else class="whitespace-pre-wrap text-sm">{{ note.objective || '—' }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.detail.assessment')
              }}</label>
              <Textarea v-if="canEdit" v-model="form.assessment" rows="3" auto-resize maxlength="10000" />
              <p v-else class="whitespace-pre-wrap text-sm">{{ note.assessment || '—' }}</p>
            </div>

            <div class="flex flex-col gap-2">
              <label class="text-sm text-surface-700 dark:text-surface-200">{{
                t('clinicalNotes.detail.plan')
              }}</label>
              <Textarea v-if="canEdit" v-model="form.plan" rows="3" auto-resize maxlength="10000" />
              <p v-else class="whitespace-pre-wrap text-sm">{{ note.plan || '—' }}</p>
            </div>

            <div v-if="canEdit" class="flex justify-end">
              <Button :label="t('clinicalNotes.detail.saveDraft')" :loading="saving" @click="saveDraft" />
            </div>
          </div>
        </template>
      </Card>

      <Card>
        <template #title>{{ t('clinicalNotes.detail.addendums') }}</template>
        <template #content>
          <p v-if="!note.addendums?.length" class="text-sm text-surface-500">
            {{ t('clinicalNotes.detail.noAddendums') }}
          </p>
          <ul v-else class="flex flex-col gap-3">
            <li
              v-for="addendum in note.addendums"
              :key="addendum.id"
              class="rounded border border-surface-200 p-3 text-sm dark:border-surface-700"
            >
              <p class="whitespace-pre-wrap">{{ addendum.body }}</p>
              <p class="mt-1 text-xs text-surface-500 dark:text-surface-400" dir="ltr">
                {{ addendum.author?.name ?? '—' }} · {{ formatDateTime(addendum.created_at) }}
              </p>
            </li>
          </ul>

          <div v-if="canAddendum" class="mt-4 flex flex-col gap-2">
            <label class="text-sm text-surface-700 dark:text-surface-200">{{
              t('clinicalNotes.detail.addAddendum')
            }}</label>
            <Textarea
              v-model="addendumBody"
              rows="2"
              auto-resize
              maxlength="10000"
              :placeholder="t('clinicalNotes.detail.addendumPlaceholder')"
            />
            <div class="flex justify-end">
              <Button
                :label="t('clinicalNotes.detail.addAddendum')"
                :loading="addingAddendum"
                :disabled="!addendumBody.trim()"
                @click="submitAddendum"
              />
            </div>
          </div>
        </template>
      </Card>
    </template>
  </div>
</template>
