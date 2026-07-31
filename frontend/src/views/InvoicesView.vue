<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { parseServerDateTime } from '@/lib/date'
import { invoicesApi } from '@/services/invoices'
import InvoiceStatusChip from '@/components/invoices/InvoiceStatusChip.vue'
import { INVOICE_STATUSES, type Invoice, type InvoiceStatus } from '@/types/invoice'

/**
 * Clinic-wide Billing entry point (frontend-ux-redesign design doc §5.1/§11) — the Sidebar's
 * Billing item pointed at `comingSoon` before this view existed, even though patient-scoped
 * Invoice CRUD was already fully built; this is purely a cross-patient index over the same
 * `Invoice` model, mirroring `SuppliesView.vue`'s server-paginated lazy `DataTable` pattern.
 */
const { t, locale } = useI18n()
const router = useRouter()

const invoices = ref<Invoice[]>([])
const totalRecords = ref(0)
const perPage = ref(15)
const page = ref(1)
const search = ref('')
const status = ref<InvoiceStatus | null>(null)
const loading = ref(false)

async function fetchInvoices() {
  loading.value = true
  try {
    const data = await invoicesApi.listAll({
      search: search.value || undefined,
      status: status.value ?? undefined,
      page: page.value,
    })
    invoices.value = data.data
    totalRecords.value = data.meta.total
    perPage.value = data.meta.per_page
  } finally {
    loading.value = false
  }
}

function onPage(event: { page: number }) {
  page.value = event.page + 1
  fetchInvoices()
}

let searchTimeout: ReturnType<typeof setTimeout>
watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchInvoices()
  }, 300)
})

watch(status, () => {
  page.value = 1
  fetchInvoices()
})

function formatDate(createdAt: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseServerDateTime(createdAt))
}

function viewInvoice(invoice: Invoice) {
  if (!invoice.patient) return
  router.push({ name: 'invoice-detail', params: { id: invoice.patient.id, invoiceId: invoice.id } })
}

onMounted(fetchInvoices)
</script>

<template>
  <div class="flex flex-col gap-4">
    <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
      {{ t('invoices.index.title') }}
    </h1>

    <div class="flex flex-wrap items-center gap-4">
      <IconField class="max-w-sm">
        <InputIcon class="pi pi-search" />
        <InputText v-model="search" :placeholder="t('invoices.index.search')" class="w-full" />
      </IconField>
      <Select
        v-model="status"
        :options="[null, ...INVOICE_STATUSES]"
        :placeholder="t('invoices.index.allStatuses')"
        class="w-48"
      >
        <template #value="{ value }">
          {{ value ? t(`invoices.status.${value}`) : t('invoices.index.allStatuses') }}
        </template>
        <template #option="{ option }">
          {{ option ? t(`invoices.status.${option}`) : t('invoices.index.allStatuses') }}
        </template>
      </Select>
    </div>

    <DataTable
      :value="invoices"
      :loading="loading"
      lazy
      paginator
      :rows="perPage"
      :total-records="totalRecords"
      class="cursor-pointer"
      @page="onPage"
      @row-click="({ data }) => viewInvoice(data)"
    >
      <template #empty>
        <span class="text-surface-500 dark:text-surface-400">{{ t('invoices.list.empty') }}</span>
      </template>

      <Column :header="t('invoices.index.patient')">
        <template #body="{ data }">
          {{ data.patient ? `${data.patient.first_name} ${data.patient.last_name}` : '—' }}
        </template>
      </Column>

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

      <Column field="created_at" :header="t('invoices.list.createdAt')">
        <template #body="{ data }">{{ formatDate(data.created_at) }}</template>
      </Column>

      <Column :header="t('invoices.list.total')">
        <template #body="{ data }">
          <span dir="ltr">{{ data.total ?? '0.00' }} {{ data.currency_code }}</span>
        </template>
      </Column>
    </DataTable>
  </div>
</template>
