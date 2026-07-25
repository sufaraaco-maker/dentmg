<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InvoiceStatusChip from './InvoiceStatusChip.vue'
import { parseServerDateTime } from '@/lib/date'
import type { Invoice } from '@/types/invoice'

/**
 * Patient Invoices tab's list table — pure/presentational, mirroring
 * `TreatmentPlanListTable.vue`'s client-paginated `DataTable` convention: `GET .../invoices` is
 * deliberately unpaginated (backend design doc §9/§13, a patient's lifetime invoice count stays
 * small), so this table fetches nothing itself and paginates client-side purely for a manageable
 * row count. Row click navigates to the Invoice Detail route, same as `TreatmentPlanListTable`'s
 * `row-click`.
 */
defineProps<{
  invoices: Invoice[]
  loading: boolean
}>()

const emit = defineEmits<{ (e: 'row-click', invoiceId: string): void }>()

const { t, locale } = useI18n()

function formatDate(createdAt: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(createdAt))
}

function itemCount(invoice: Invoice): number {
  return invoice.items?.length ?? 0
}
</script>

<template>
  <DataTable
    :value="invoices"
    :loading="loading"
    data-key="id"
    paginator
    :rows="10"
    sort-field="created_at"
    :sort-order="-1"
    class="cursor-pointer"
    @row-click="({ data }) => emit('row-click', data.id)"
  >
    <template #empty>{{ t('invoices.list.empty') }}</template>

    <Column field="invoice_number" :header="t('invoices.list.number')">
      <template #body="{ data }">
        <span dir="ltr">{{ data.invoice_number ?? t('invoices.list.draftLabel') }}</span>
      </template>
    </Column>

    <Column :header="t('invoices.list.status')">
      <template #body="{ data }">
        <InvoiceStatusChip :status="data.status" size="small" />
      </template>
    </Column>

    <Column field="created_at" :header="t('invoices.list.createdAt')" sortable>
      <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
    </Column>

    <Column :header="t('invoices.list.items')">
      <template #body="{ data }">{{ itemCount(data) }}</template>
    </Column>

    <Column :header="t('invoices.list.total')">
      <template #body="{ data }">
        <span dir="ltr">{{ data.total ?? '0.00' }} {{ data.currency_code }}</span>
      </template>
    </Column>
  </DataTable>
</template>
