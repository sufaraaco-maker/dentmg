<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import Select from 'primevue/select'
import Paginator from 'primevue/paginator'
import Skeleton from 'primevue/skeleton'
import { FlaskConical } from 'lucide-vue-next'
import LabCaseStatusChip from './LabCaseStatusChip.vue'
import LabCaseActionsBar from './LabCaseActionsBar.vue'
import CreateLabCaseDialog from './CreateLabCaseDialog.vue'
import EmptyState from '@/components/common/EmptyState.vue'
import { usePatientLabCasesStore } from '@/stores/patientLabCases'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime } from '@/lib/date'
import type { LabCase, LabCaseStatus } from '@/types/laboratory'

/**
 * Patient Detail's Laboratory tab (Phase 2.4, patient-laboratory-redesign-design.md §4.5/§7
 * decision 1 — read/write, approved). Structurally mirrors `PatientImagingPanel.vue` (list +
 * status filter + `EmptyState.vue`/`Skeleton` + `Paginator` + inline create), not a `DataTable`:
 * unlike `TreatmentPlanListTable.vue`/`InvoiceListTable.vue` (row-click-only, actions live on a
 * separate detail page), this tab also needs per-row `LabCaseActionsBar.vue` status-transition
 * buttons — mixing a whole-row click handler with in-row buttons is a real event-bubbling hazard
 * PrimeVue's `DataTable` doesn't handle for free, so this uses explicit per-row "View" navigation
 * instead of `@row-click`, avoiding the conflict entirely rather than working around it.
 *
 * The standalone `LabsView.vue`/`LabCasesView.vue`/`LabCaseDetailView.vue` pages are untouched —
 * this panel is an additional, patient-scoped entry point, not a replacement.
 */
const props = defineProps<{ patientId: string }>()

const { t, locale } = useI18n()
const router = useRouter()
const toast = useToast()
const labCasesStore = usePatientLabCasesStore()
const auth = useAuthStore()

// Matches LabCasePolicy::create exactly (design doc §9) — no new permission invented.
const canCreate = computed(() => auth.isAdmin || auth.isDentist)

const STATUS_OPTIONS: LabCaseStatus[] = ['draft', 'sent', 'received', 'quality_checked', 'cancelled']

const statusFilter = ref<LabCaseStatus | null>(null)
const page = ref(1)
const dialogVisible = ref(false)

const cases = computed(() => labCasesStore.casesForPatient(props.patientId))
const pageMeta = computed(() => labCasesStore.pageMetaForPatient(props.patientId))
const loading = computed(() => labCasesStore.loading)

// See PatientTreatmentPlansPanel.vue's/PatientImagingPanel.vue's identical watcher: a failed fetch
// otherwise fails silently, indistinguishable from "no cases on file".
watch(
  () => labCasesStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

function fetchCases(force = false) {
  labCasesStore.fetchForPatient(props.patientId, page.value, statusFilter.value, force)
}

watch(
  () => props.patientId,
  () => {
    page.value = 1
    fetchCases(true)
  },
  { immediate: true },
)

watch(statusFilter, () => {
  page.value = 1
  fetchCases(true)
})

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchCases()
}

function onCreated() {
  // labCasesStore.create() already refreshes page 1 for this patient — reset the local page
  // control to match what's now loaded (same convention as PatientTreatmentPlansPanel.vue).
  page.value = 1
  dialogVisible.value = false
}

function onCaseUpdated(labCase: LabCase) {
  labCasesStore.upsert(labCase)
}

function viewCase(labCase: LabCase) {
  router.push({ name: 'lab-case-detail', params: { id: labCase.id } })
}

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(value))
}
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.laboratoryPanel.title') }}</span>
        <Button
          v-if="canCreate"
          :label="t('patients.laboratoryPanel.new')"
          icon="pi pi-plus"
          size="small"
          @click="dialogVisible = true"
        />
      </div>
    </template>
    <template #content>
      <div class="mb-4 flex flex-wrap items-center gap-4">
        <Select
          v-model="statusFilter"
          :options="[
            { value: null, label: t('laboratory.labCases.allStatuses') },
            ...STATUS_OPTIONS.map((s) => ({ value: s, label: t(`laboratory.labCases.status.${s}`) })),
          ]"
          option-label="label"
          option-value="value"
          class="w-56"
        />
      </div>

      <div v-if="loading && !cases.length" class="flex flex-col gap-2">
        <Skeleton v-for="n in 3" :key="n" class="h-16 w-full" />
      </div>

      <EmptyState
        v-else-if="!cases.length"
        :icon="FlaskConical"
        :title="t('patients.laboratoryPanel.empty')"
      />

      <div v-else class="flex flex-col gap-2">
        <div
          v-for="labCase in cases"
          :key="labCase.id"
          class="flex flex-col gap-3 rounded-border border border-surface-200 p-3 dark:border-surface-700 sm:flex-row sm:items-center sm:justify-between"
        >
          <button type="button" class="flex flex-col gap-1 text-start" @click="viewCase(labCase)">
            <span class="flex items-center gap-2">
              <span dir="ltr" class="font-medium text-surface-900 dark:text-surface-0">{{
                labCase.case_number
              }}</span>
              <LabCaseStatusChip :status="labCase.status" size="small" />
            </span>
            <span class="text-sm text-surface-500 dark:text-surface-400">
              {{ labCase.lab?.name ?? '—' }}
              <template v-if="labCase.due_at">
                · {{ t('laboratory.labCases.dueAt') }}:
                <span dir="ltr">{{ formatDate(labCase.due_at) }}</span>
              </template>
            </span>
          </button>

          <LabCaseActionsBar :lab-case="labCase" @updated="onCaseUpdated" />
        </div>
      </div>

      <Paginator
        v-if="pageMeta.total > pageMeta.perPage"
        class="mt-4"
        :rows="pageMeta.perPage"
        :total-records="pageMeta.total"
        :first="(page - 1) * pageMeta.perPage"
        @page="onPage"
      />
    </template>
  </Card>

  <CreateLabCaseDialog v-model:visible="dialogVisible" :patient-id="patientId" @created="onCreated" />
</template>
