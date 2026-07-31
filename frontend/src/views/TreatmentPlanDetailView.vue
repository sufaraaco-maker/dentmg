<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import Message from 'primevue/message'
import Dialog from 'primevue/dialog'
import Tag from 'primevue/tag'
import TreatmentPlanStatusChip from '@/components/treatmentPlans/TreatmentPlanStatusChip.vue'
import TreatmentPlanItemsTable from '@/components/treatmentPlans/TreatmentPlanItemsTable.vue'
import TreatmentPlanActionsBar from '@/components/treatmentPlans/TreatmentPlanActionsBar.vue'
import TreatmentPlanItemDialog from '@/components/treatmentPlans/TreatmentPlanItemDialog.vue'
import { useTreatmentPlansStore } from '@/stores/treatmentPlans'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { useAuthStore } from '@/stores/auth'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import {
  aiAssistantErrorCode,
  recordAiDecision,
  suggestTreatmentItems,
} from '@/services/aiAssistant/aiAssistantApi'
import { parseServerDateTime } from '@/lib/date'
import type { TreatmentPlanItem } from '@/types/treatmentPlan'
import type { AiTreatmentSuggestionCandidate } from '@/types/aiAssistant'

/**
 * Plan Detail (design doc §11/§21 Steps 7-8) — metadata, status, cost summary, revision
 * relationship, item list, status-transition actions, and item add/edit/complete/cancel/delete.
 * Diagnosis-entry and appointment linkage UI is still out of scope (Step 9).
 *
 * Hydrates from `treatmentPlansStore.cache` directly (a computed over the same reactive `Map`
 * `PatientTreatmentPlansPanel.vue` reads from, design doc §9) rather than holding a local `ref`
 * copy the way `AppointmentDetailView.vue` does — when navigating here from the Patient Treatment
 * Plans tab, the list fetch already eager-loaded items+relations (`TreatmentPlanController::WITH`
 * is identical for `index`/`show`), so the plan is already fully hydrated and `fetchOne` is
 * skipped entirely; a direct/bookmarked visit with an empty cache still falls back to fetching.
 * Every action below (`TreatmentPlanActionsBar`, item mutations) upserts straight into that same
 * cache, so this view's `plan` computed picks up every change with no extra fetch or local state.
 */
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const treatmentPlansStore = useTreatmentPlansStore()
const conditionsStore = useDentalConditionsStore()
const auth = useAuthStore()
const aiAssistantStore = useAiAssistantStore()

onMounted(() => aiAssistantStore.load())

const patientId = computed(() => route.params.id as string)
const planId = computed(() => route.params.planId as string)

const plan = computed(() => treatmentPlansStore.cache.get(planId.value) ?? null)
const loading = ref(false)
const notFound = ref(false)

// Matches every other write ability in this module (design doc §10): admin + dentist only.
const canWrite = computed(() => auth.isAdmin || auth.isDentist)
// Items may only be added while the plan is still a draft (`TreatmentPlanService::addItem()`).
const canAddItem = computed(() => canWrite.value && plan.value?.status === 'draft')
// PHI-bearing feature (design doc §4.5) — absent from the UI, not just disabled, unless the admin
// has both turned the module on and confirmed the BAA-with-Anthropic prerequisite (§5 Layer 2).
const canUseAiSuggestions = computed(
  () => canAddItem.value && aiAssistantStore.enabled && aiAssistantStore.phiAcknowledged,
)

const itemDialogVisible = ref(false)
const editingItem = ref<TreatmentPlanItem | null>(null)

function openAddItem() {
  editingItem.value = null
  itemDialogVisible.value = true
}

function openEditItem(item: TreatmentPlanItem) {
  editingItem.value = item
  itemDialogVisible.value = true
}

// --- AI Treatment Suggestions (design doc §4.5, PHI-bearing) ------------------------------------

const suggestionsDialogVisible = ref(false)
const suggesting = ref(false)
const suggestionsError = ref<string | null>(null)
const candidates = ref<AiTreatmentSuggestionCandidate[]>([])
const addedCandidates = ref<Set<number>>(new Set())
const addingCandidateIndex = ref<number | null>(null)
const suggestionInteractionId = ref<string | null>(null)
const anyCandidateAdded = ref(false)

function conditionName(id: string): string {
  return conditionsStore.items.find((c) => c.id === id)?.name ?? id
}

async function openSuggestionsDialog() {
  if (!plan.value) return

  suggestionsDialogVisible.value = true
  suggesting.value = true
  suggestionsError.value = null
  candidates.value = []
  addedCandidates.value = new Set()
  anyCandidateAdded.value = false
  suggestionInteractionId.value = null

  try {
    const result = await suggestTreatmentItems(plan.value.id)
    candidates.value = result.candidates
    suggestionInteractionId.value = result.interaction_id
  } catch (err) {
    suggestionsError.value =
      aiAssistantErrorCode(err) === 'ai_assistant_unavailable'
        ? t('aiAssistant.unavailableError')
        : t('aiAssistant.genericError')
  } finally {
    suggesting.value = false
  }
}

async function addCandidate(candidate: AiTreatmentSuggestionCandidate, index: number) {
  if (!plan.value) return

  addingCandidateIndex.value = index
  try {
    await treatmentPlansStore.addItem(plan.value.id, {
      dental_condition_id: candidate.dental_condition_id,
      tooth_number: candidate.tooth_number,
      surfaces: candidate.surfaces ?? undefined,
    })
    addedCandidates.value = new Set(addedCandidates.value).add(index)
    anyCandidateAdded.value = true
  } finally {
    addingCandidateIndex.value = null
  }
}

function closeSuggestionsDialog() {
  suggestionsDialogVisible.value = false
  if (suggestionInteractionId.value) {
    const summary = anyCandidateAdded.value
      ? `Added ${addedCandidates.value.size} of ${candidates.value.length} suggested items`
      : undefined
    recordAiDecision(suggestionInteractionId.value, anyCandidateAdded.value, summary).catch(() => {})
  }
}

async function load() {
  notFound.value = false

  if (!treatmentPlansStore.cache.has(planId.value)) {
    loading.value = true
    try {
      await treatmentPlansStore.fetchOne(planId.value)
    } catch {
      notFound.value = true
    } finally {
      loading.value = false
    }
  }

  await conditionsStore.fetchAll()
}

watch(planId, load, { immediate: true })

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

/** The plan (if any) whose own `superseded_by_plan_id` points at this one — only resolvable when
 *  already present in the shared cache (e.g. arrived via the patient's Treatment Plans tab, which
 *  loads the patient's full plan history); never triggers an extra fetch just for this banner. */
const predecessor = computed(() => {
  if (!plan.value) return null
  return (
    Array.from(treatmentPlansStore.cache.values()).find((p) => p.superseded_by_plan_id === plan.value!.id) ??
    null
  )
})

function goToPatient() {
  router.push({ name: 'patient-detail', params: { id: patientId.value } })
}

function goToPlan(id: string) {
  router.push({ name: 'treatment-plan-detail', params: { id: patientId.value, planId: id } })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center gap-3">
      <Button icon="pi pi-arrow-left" text rounded @click="goToPatient" />
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ plan?.title ?? t('treatmentPlans.list.untitled') }}
      </h1>
      <TreatmentPlanStatusChip v-if="plan" :status="plan.status" />
    </div>

    <TreatmentPlanActionsBar v-if="plan" :plan="plan" />

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('treatmentPlans.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="plan">
      <Message v-if="plan.superseded_by_plan" severity="warn" :closable="false">
        {{
          t('treatmentPlans.detail.supersededBy', {
            title: plan.superseded_by_plan.title ?? t('treatmentPlans.list.untitled'),
          })
        }}
        <Button
          :label="t('treatmentPlans.detail.viewPlan')"
          text
          size="small"
          class="ms-2"
          @click="goToPlan(plan.superseded_by_plan!.id)"
        />
      </Message>

      <Message v-if="predecessor" severity="info" :closable="false">
        {{
          t('treatmentPlans.detail.supersedes', {
            title: predecessor.title ?? t('treatmentPlans.list.untitled'),
          })
        }}
        <Button
          :label="t('treatmentPlans.detail.viewPlan')"
          text
          size="small"
          class="ms-2"
          @click="goToPlan(predecessor.id)"
        />
      </Message>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <template #title>{{ t('treatmentPlans.detail.overview') }}</template>
          <template #content>
            <dl class="grid grid-cols-2 gap-y-3 text-sm">
              <dt class="text-surface-500">{{ t('treatmentPlans.list.dentist') }}</dt>
              <dd>{{ plan.dentist?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('treatmentPlans.detail.createdBy') }}</dt>
              <dd>{{ plan.created_by?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('treatmentPlans.list.createdAt') }}</dt>
              <dd dir="ltr" class="text-start">{{ formatDateTime(plan.created_at) }}</dd>

              <template v-if="plan.presented_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.status.presented') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.presented_at) }}</dd>
              </template>
              <template v-if="plan.accepted_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.status.accepted') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.accepted_at) }}</dd>
              </template>
              <template v-if="plan.rejected_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.status.rejected') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.rejected_at) }}</dd>
              </template>
              <template v-if="plan.started_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.detail.startedAt') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.started_at) }}</dd>
              </template>
              <template v-if="plan.completed_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.status.completed') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.completed_at) }}</dd>
              </template>
              <template v-if="plan.cancelled_at">
                <dt class="text-surface-500">{{ t('treatmentPlans.status.cancelled') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(plan.cancelled_at) }}</dd>
              </template>
            </dl>
            <p v-if="plan.notes" class="mt-3 text-sm text-surface-600 dark:text-surface-300">
              {{ plan.notes }}
            </p>
          </template>
        </Card>

        <Card>
          <template #title>{{ t('treatmentPlans.detail.costSummary') }}</template>
          <template #content>
            <p class="text-2xl font-semibold" dir="ltr">{{ plan.estimated_cost ?? '0.00' }}</p>
            <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
              {{ t('treatmentPlans.detail.estimateOnly') }}
            </p>
          </template>
        </Card>
      </div>

      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>{{ t('treatmentPlans.detail.items') }}</span>
            <div class="flex items-center gap-2">
              <Button
                v-if="canUseAiSuggestions"
                :label="t('aiAssistant.treatmentSuggestion.action')"
                icon="pi pi-sparkles"
                severity="secondary"
                outlined
                size="small"
                @click="openSuggestionsDialog"
              />
              <Button
                v-if="canAddItem"
                :label="t('treatmentPlans.items.add')"
                icon="pi pi-plus"
                size="small"
                @click="openAddItem"
              />
            </div>
          </div>
        </template>
        <template #content>
          <p v-if="!plan.items?.length" class="text-sm text-surface-500">
            {{ t('treatmentPlans.items.empty') }}
          </p>
          <TreatmentPlanItemsTable v-else :items="plan.items" @edit-item="openEditItem" />
        </template>
      </Card>
    </template>

    <Dialog
      :visible="suggestionsDialogVisible"
      modal
      :header="t('aiAssistant.treatmentSuggestion.dialogTitle')"
      class="w-full max-w-2xl"
      @update:visible="(v: boolean) => (v ? null : closeSuggestionsDialog())"
    >
      <Skeleton v-if="suggesting" height="10rem" />

      <Message v-else-if="suggestionsError" severity="error">{{ suggestionsError }}</Message>

      <p v-else-if="!candidates.length" class="text-sm text-surface-500">
        {{ t('aiAssistant.treatmentSuggestion.empty') }}
      </p>

      <ul v-else class="flex flex-col gap-3">
        <li
          v-for="(candidate, index) in candidates"
          :key="index"
          class="flex items-start justify-between gap-3 rounded-lg border border-surface-200 p-3 dark:border-surface-700"
        >
          <div>
            <div class="flex items-center gap-2">
              <Tag severity="info" icon="pi pi-sparkles" :value="t('aiAssistant.unreviewedLabel')" />
              <span class="font-medium text-surface-900 dark:text-surface-0">{{
                conditionName(candidate.dental_condition_id)
              }}</span>
              <span v-if="candidate.tooth_number" class="text-sm text-surface-500" dir="ltr">
                #{{ candidate.tooth_number
                }}<template v-if="candidate.surfaces?.length">
                  ({{ candidate.surfaces.join(', ') }})</template
                >
              </span>
            </div>
            <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">{{ candidate.rationale }}</p>
          </div>
          <Button
            v-if="!addedCandidates.has(index)"
            :label="t('aiAssistant.treatmentSuggestion.add')"
            icon="pi pi-plus"
            size="small"
            :loading="addingCandidateIndex === index"
            @click="addCandidate(candidate, index)"
          />
          <Tag v-else severity="success" :value="t('aiAssistant.treatmentSuggestion.added')" />
        </li>
      </ul>

      <template #footer>
        <Button :label="t('common.close')" text @click="closeSuggestionsDialog" />
      </template>
    </Dialog>
  </div>

  <TreatmentPlanItemDialog v-if="plan" v-model:visible="itemDialogVisible" :plan="plan" :item="editingItem" />
</template>
