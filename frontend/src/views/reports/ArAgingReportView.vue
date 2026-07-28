<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import Message from 'primevue/message'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { downloadReportCsv, getArAgingReport } from '@/services/reports'
import type { ArAgingBucket, ArAgingReport } from '@/types/reports'

/**
 * A/R Aging Report (design doc §4.3) — admin only, financial. Point-in-time snapshot, no date
 * range filter (matches every competitor's own "as of today" framing, per the design doc's
 * competitive research).
 */
const { t } = useI18n()

const BUCKETS: ArAgingBucket[] = ['current', '1_30', '31_60', '61_90', '90_plus']

const report = ref<ArAgingReport | null>(null)
const loading = ref(false)
const error = ref(false)

async function fetchReport() {
  loading.value = true
  error.value = false
  try {
    report.value = await getArAgingReport()
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

function exportCsv() {
  return downloadReportCsv('/reports/ar-aging', {}, 'ar-aging.csv')
}

onMounted(fetchReport)
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('reports.nav.arAging') }}
      </h1>
      <Button
        :label="t('reports.actions.exportCsv')"
        icon="pi pi-download"
        severity="secondary"
        outlined
        @click="exportCsv"
      />
    </div>

    <Message v-if="error" severity="error">{{ t('reports.loadError') }}</Message>

    <div v-if="report" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div class="rounded-lg border border-surface-200 p-4 dark:border-surface-700">
        <p class="text-sm text-surface-500">{{ t('reports.arAging.total') }}</p>
        <p
          dir="ltr"
          class="text-start text-2xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.total }}
        </p>
      </div>
      <div
        v-for="bucket in BUCKETS"
        :key="bucket"
        class="rounded-lg border border-surface-200 p-4 dark:border-surface-700"
      >
        <p class="text-sm text-surface-500">{{ t(`reports.arAging.buckets.${bucket}`) }}</p>
        <p
          dir="ltr"
          class="text-start text-xl font-semibold tabular-nums text-surface-900 dark:text-surface-0"
        >
          {{ report.summary.buckets[bucket] }}
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
      <Column field="patient" :header="t('reports.arAging.columns.patient')" />
      <Column field="invoice_number" :header="t('reports.arAging.columns.invoiceNumber')" />
      <Column :header="t('reports.arAging.columns.dueDate')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.due_date ?? '—' }}</span></template
        >
      </Column>
      <Column :header="t('reports.arAging.columns.daysOverdue')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.days_overdue }}</span></template
        >
      </Column>
      <Column :header="t('reports.arAging.columns.balanceDue')">
        <template #body="{ data }"
          ><span dir="ltr">{{ data.balance_due }}</span></template
        >
      </Column>
    </DataTable>
  </div>
</template>
