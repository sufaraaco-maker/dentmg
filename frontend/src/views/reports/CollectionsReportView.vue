<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ReportDateRangeFilter from '@/components/reports/ReportDateRangeFilter.vue'
import AiReportNarrativeButton from '@/components/aiAssistant/AiReportNarrativeButton.vue'
import { downloadReportCsv, getCollectionsReport } from '@/services/reports'
import { toLocalDateString } from '@/lib/date'
import type { PaymentMethod } from '@/types/payment'
import type { CollectionsReport } from '@/types/reports'

/** Collections Report (design doc §4.2) — admin only, financial. */
const { t } = useI18n()

const METHODS: PaymentMethod[] = ['cash', 'card', 'bank_transfer', 'other']

const today = new Date()
const dateFrom = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth(), 1)))
const dateTo = ref(toLocalDateString(new Date(today.getFullYear(), today.getMonth() + 1, 0)))
const method = ref<PaymentMethod | null>(null)

const report = ref<CollectionsReport | null>(null)
const loading = ref(false)
const error = ref(false)

const methodOptions: { value: PaymentMethod | null }[] = [
  { value: null },
  ...METHODS.map((m) => ({ value: m })),
]

async function fetchReport() {
  loading.value = true
  error.value = false
  try {
    report.value = await getCollectionsReport({
      date_from: dateFrom.value,
      date_to: dateTo.value,
      method: method.value,
    })
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  return downloadReportCsv(
    '/reports/collections',
    { date_from: dateFrom.value, date_to: dateTo.value, method: method.value },
    'collections.csv',
  )
}

onMounted(fetchReport)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('reports.nav.collections') }}
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
          <label for="collections-method" class="text-sm text-surface-600 dark:text-surface-400">
            {{ t('reports.filters.method') }}
          </label>
          <Select
            id="collections-method"
            v-model="method"
            :options="methodOptions"
            option-label="value"
            option-value="value"
            class="w-56"
          >
            <template #option="{ option }">
              {{
                option.value === null ? t('reports.filters.allMethods') : t(`payments.method.${option.value}`)
              }}
            </template>
            <template #value="{ value: selected }">
              {{ selected === null ? t('reports.filters.allMethods') : t(`payments.method.${selected}`) }}
            </template>
          </Select>
        </div>
      </template>
    </ReportDateRangeFilter>

    <AiReportNarrativeButton
      report-type="collections"
      :params="{ date_from: dateFrom, date_to: dateTo, method }"
    />

    <Message v-if="error" severity="error">{{ t('reports.loadError') }}</Message>

    <div v-if="report" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.collections.total') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.total }}
        </p>
      </div>
    </div>

    <DataTable v-if="report" :value="report.summary.by_method" class="mb-2">
      <template #header>{{ t('reports.collections.byMethod') }}</template>
      <Column :header="t('reports.collections.columns.method')">
        <template #body="{ data }">{{ t(`payments.method.${data.method}`) }}</template>
      </Column>
      <Column :header="t('reports.collections.columns.amount')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.amount }}</span></template
        >
      </Column>
    </DataTable>

    <DataTable
      :value="report?.rows ?? []"
      :loading="loading"
      :paginator="(report?.rows.length ?? 0) > 20"
      :rows="20"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('reports.empty') }}</span>
      </template>
      <Column :header="t('reports.collections.columns.date')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.date }}</span></template
        >
      </Column>
      <Column field="patient" :header="t('reports.collections.columns.patient')" />
      <Column field="invoice_number" :header="t('reports.collections.columns.invoiceNumber')" />
      <Column :header="t('reports.collections.columns.method')">
        <template #body="{ data }">{{ t(`payments.method.${data.method}`) }}</template>
      </Column>
      <Column :header="t('reports.collections.columns.amount')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.amount }}</span></template
        >
      </Column>
    </DataTable>
  </div>
</template>
