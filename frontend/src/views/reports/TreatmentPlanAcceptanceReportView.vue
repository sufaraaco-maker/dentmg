<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ReportDateRangeFilter from '@/components/reports/ReportDateRangeFilter.vue'
import { downloadReportCsv, getTreatmentPlanAcceptanceReport } from '@/services/reports'
import { useProvidersStore } from '@/stores/providers'
import { toLocalDateString } from '@/lib/date'
import type { TreatmentPlanAcceptanceReport } from '@/types/reports'

/** Treatment Plan Acceptance Report (design doc §4.5) — operational, open to every role. */
const { t } = useI18n()
const providers = useProvidersStore()

const today = new Date()
const dateFrom = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth(), 1)))
const dateTo = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth() + 1, 0)))
const dentistId = ref<string | null>(null)

const report = ref<TreatmentPlanAcceptanceReport | null>(null)
const loading = ref(false)
const error = ref(false)

const dentistOptions = ref<{ id: string | null; name: string }[]>([])

async function fetchReport() {
  loading.value = true
  error.value = false
  try {
    report.value = await getTreatmentPlanAcceptanceReport({
      date_from: dateFrom.value,
      date_to: dateTo.value,
      dentist_id: dentistId.value,
    })
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  return downloadReportCsv(
    '/reports/treatment-plan-acceptance',
    { date_from: dateFrom.value, date_to: dateTo.value, dentist_id: dentistId.value },
    'treatment-plan-acceptance.csv',
  )
}

onMounted(async () => {
  await providers.fetchAll()
  dentistOptions.value = [{ id: null, name: t('reports.filters.allDentists') }, ...providers.items]
  await fetchReport()
})
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('reports.nav.treatmentPlanAcceptance') }}
      </h1>
      <Button
        :label="t('reports.actions.exportCsv')"
        icon="pi pi-download"
        severity="secondary"
        outlined
        @click="exportCsv"
      />
    </div>

    <ReportDateRangeFilter
      v-model:date-from="dateFrom"
      v-model:date-to="dateTo"
      :loading="loading"
      @apply="fetchReport"
    >
      <template #extra-filters>
        <div class="flex flex-col gap-1">
          <label for="tp-acceptance-dentist" class="text-sm text-surface-600 dark:text-surface-400">
            {{ t('reports.filters.dentist') }}
          </label>
          <Select
            id="tp-acceptance-dentist"
            v-model="dentistId"
            :options="dentistOptions"
            option-label="name"
            option-value="id"
            class="w-56"
          />
        </div>
      </template>
    </ReportDateRangeFilter>

    <Message v-if="error" severity="error">{{ t('reports.loadError') }}</Message>

    <div v-if="report" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.treatmentPlanAcceptance.presented') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.presented }}
        </p>
      </div>
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.treatmentPlanAcceptance.acceptanceRate') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.acceptance_rate }}%
        </p>
      </div>
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.treatmentPlanAcceptance.rejected') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.rejected }}
        </p>
      </div>
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.treatmentPlanAcceptance.acceptedValue') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.accepted_value }}
        </p>
      </div>
    </div>

    <DataTable
      :value="report?.rows ?? []"
      :loading="loading"
      :paginator="(report?.rows.length ?? 0) > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('reports.empty') }}</span>
      </template>
      <Column field="patient" :header="t('reports.treatmentPlanAcceptance.columns.patient')" />
      <Column :header="t('reports.treatmentPlanAcceptance.columns.dentist')">
        <template #body="{ data }">{{ data.dentist ?? '—' }}</template>
      </Column>
      <Column :header="t('reports.treatmentPlanAcceptance.columns.presentedAt')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.presented_at }}</span></template
        >
      </Column>
      <Column :header="t('reports.treatmentPlanAcceptance.columns.status')">
        <template #body="{ data }">{{ t(`treatmentPlans.status.${data.status}`) }}</template>
      </Column>
      <Column :header="t('reports.treatmentPlanAcceptance.columns.value')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.value }}</span></template
        >
      </Column>
    </DataTable>
  </div>
</template>
