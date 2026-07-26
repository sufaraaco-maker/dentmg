<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import ClinicalNoteListTable from './ClinicalNoteListTable.vue'
import CreateClinicalNoteDialog from './CreateClinicalNoteDialog.vue'
import { useClinicalNotesStore } from '@/stores/clinicalNotes'
import { useAuthStore } from '@/stores/auth'

/**
 * Patient Detail's Clinical Notes tab — mirrors `PatientTreatmentPlansPanel.vue`'s role as the
 * store-aware host a tab delegates to. Receptionists never reach this component in practice
 * (`PatientDetailView.vue` only renders this tab for admin/dentist, design doc §10/§15 Decision D),
 * but `canWrite` still gates the "New Clinical Note" button as defense in depth, matching every
 * other module's belt-and-suspenders pattern.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const router = useRouter()
const toast = useToast()
const clinicalNotesStore = useClinicalNotesStore()
const auth = useAuthStore()

// Matches Treatment Plans'/Dental Chart's identical matrix (design doc §10): admin + dentist only.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)

const dialogVisible = ref(false)

const notes = computed(() => clinicalNotesStore.notesForPatient(props.patientId))

// See PatientTreatmentPlansPanel.vue's identical watcher: a failed fetch otherwise fails silently.
watch(
  () => clinicalNotesStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function goToDetail(noteId: string) {
  router.push({ name: 'clinical-note-detail', params: { id: props.patientId, noteId } })
}

function onSaved(noteId: string) {
  // No refetch needed: `clinicalNotesStore.create()` already upserts into the same reactive
  // cache `notes` reads from. Navigate straight to the new draft so the dentist can fill in the
  // SOAP body immediately.
  dialogVisible.value = false
  goToDetail(noteId)
}

watch(
  () => props.patientId,
  (patientId) => clinicalNotesStore.fetchForPatient(patientId),
  { immediate: true },
)
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.clinicalNotesPanel.title') }}</span>
        <Button
          v-if="canWrite"
          :label="t('patients.clinicalNotesPanel.new')"
          icon="pi pi-plus"
          size="small"
          @click="dialogVisible = true"
        />
      </div>
    </template>
    <template #content>
      <p v-if="!clinicalNotesStore.loading && !notes.length" class="text-sm text-surface-500">
        {{ t('patients.clinicalNotesPanel.empty') }}
      </p>
      <ClinicalNoteListTable
        v-else
        :notes="notes"
        :loading="clinicalNotesStore.loading"
        @row-click="goToDetail"
      />
    </template>
  </Card>

  <CreateClinicalNoteDialog
    v-model:visible="dialogVisible"
    :patient-id="patientId"
    @saved="(note) => onSaved(note.id)"
  />
</template>
