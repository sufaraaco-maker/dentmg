<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Message from 'primevue/message'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ReportDateRangeFilter from '@/components/reports/ReportDateRangeFilter.vue'
import { downloadReportCsv, getNewPatientsReport } from '@/services/reports'
import { toLocalDateString } from '@/lib/date'
import type { NewPatientsReport } from '@/types/reports'

/** New Patients Report (design doc §4.6) — operational, open to every role. */
const { t } = useI18n()

const today = new Date()
const dateFrom = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth(), 1)))
const dateTo = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth() + 1, 0)))

const report = ref<NewPatientsReport | null>(null)
const loading = ref(false)
const error = ref(false)

async function fetchReport() {
  loading.value = true
  error.value = false
  try {
    report.value = await getNewPatientsReport({ date_from: dateFrom.value, date_to: dateTo.value })
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  return downloadReportCsv(
    '/reports/new-patients',
    { date_from: dateFrom.value, date_to: dateTo.value },
    'new-patients.csv',
  )
}

onMounted(fetchReport)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('reports.nav.newPatients') }}
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
    />

    <Message v-if="error" severity="error">{{ t('reports.loadError') }}</Message>

    <div v-if="report" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.newPatients.total') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.total }}
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
      <Column field="name" :header="t('reports.newPatients.columns.name')" />
      <Column :header="t('reports.newPatients.columns.patientCode')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.patient_code }}</span></template
        >
      </Column>
      <Column :header="t('reports.newPatients.columns.registeredAt')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.registered_at }}</span></template
        >
      </Column>
    </DataTable>
  </div>
</template>
