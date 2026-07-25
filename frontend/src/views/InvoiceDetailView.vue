<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import InvoiceStatusChip from '@/components/invoices/InvoiceStatusChip.vue'
import InvoiceItemsTable from '@/components/invoices/InvoiceItemsTable.vue'
import InvoiceActionsBar from '@/components/invoices/InvoiceActionsBar.vue'
import InvoiceItemDialog from '@/components/invoices/InvoiceItemDialog.vue'
import AddFromTreatmentPlanDialog from '@/components/invoices/AddFromTreatmentPlanDialog.vue'
import EditInvoiceDialog from '@/components/invoices/EditInvoiceDialog.vue'
import InvoicePaymentsPanel from '@/components/payments/InvoicePaymentsPanel.vue'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'
import { parseServerDateTime, parseLocalDate } from '@/lib/date'
import type { Invoice, InvoiceItem } from '@/types/invoice'

// Mirrors InvoiceStatusChip.vue's Tag+severity convention (backend design doc §4/§11): `unpaid`
// reads as neutral (not yet a problem on an issued invoice), `paid` as success.
const PAYMENT_STATUS_SEVERITY: Record<
  NonNullable<Invoice['payment_status']>,
  'secondary' | 'warn' | 'success'
> = {
  unpaid: 'secondary',
  partially_paid: 'warn',
  paid: 'success',
}

/**
 * Invoice Detail (backend design doc §11) — metadata, status, itemized line table, total panel,
 * status-transition actions, item add (manual/from-plan)/edit/delete.
 *
 * Hydrates from `invoicesStore.cache` directly (a computed over the same reactive `Map`
 * `PatientInvoicesPanel.vue` reads from), mirroring `TreatmentPlanDetailView.vue`'s identical
 * pattern rather than holding a local `ref` copy — when navigating here from the Patient Invoices
 * tab, the list fetch already eager-loaded items+relations (`InvoiceController::WITH` is identical
 * for `index`/`show`), so the invoice is already fully hydrated and `fetchOne` is skipped
 * entirely; a direct/bookmarked visit with an empty cache still falls back to fetching. Every
 * action below upserts straight into that same cache, so this view's `invoice` computed picks up
 * every change with no extra fetch or local state.
 */
const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const toast = useToast()
const confirm = useConfirm()
const invoicesStore = useInvoicesStore()
const auth = useAuthStore()

const patientId = computed(() => route.params.id as string)
const invoiceId = computed(() => route.params.invoiceId as string)

const invoice = computed(() => invoicesStore.cache.get(invoiceId.value) ?? null)
const loading = ref(false)
const notFound = ref(false)

// Billing is front-desk/administrative work (backend design doc §10): admin + receptionist write,
// dentist read-only.
const canWrite = computed(() => auth.isAdmin || auth.isReceptionist)
const canDelete = computed(() => auth.isAdmin)
const isDraft = computed(() => invoice.value?.status === 'draft')
const canAddItem = computed(() => canWrite.value && isDraft.value)
const canEditMeta = computed(() => canWrite.value && isDraft.value)

const itemDialogVisible = ref(false)
const editingItem = ref<InvoiceItem | null>(null)
const pickerVisible = ref(false)
const editMetaVisible = ref(false)

function openAddItem() {
  editingItem.value = null
  itemDialogVisible.value = true
}

function openEditItem(item: InvoiceItem) {
  editingItem.value = item
  itemDialogVisible.value = true
}

async function load() {
  notFound.value = false

  if (!invoicesStore.cache.has(invoiceId.value)) {
    loading.value = true
    try {
      await invoicesStore.fetchOne(invoiceId.value)
    } catch {
      notFound.value = true
    } finally {
      loading.value = false
    }
  }
}

watch(invoiceId, load, { immediate: true })

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseLocalDate(value))
}

function formatDateTime(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium', timeStyle: 'short' }).format(
    parseServerDateTime(value),
  )
}

function goToPatient() {
  router.push({ name: 'patient-detail', params: { id: patientId.value } })
}

function confirmDelete() {
  if (!invoice.value) return

  confirm.require({
    message: t('invoices.detail.confirmDeleteMessage'),
    header: t('invoices.detail.confirmDeleteHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await invoicesStore.remove(invoice.value!.id)
        toast.add({ severity: 'success', summary: t('invoices.detail.deleted'), life: 3000 })
        goToPatient()
      } catch {
        toast.add({ severity: 'error', summary: t('invoices.detail.deleteError'), life: 3000 })
      }
    },
  })
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="goToPatient" />
        <h1 class="text-2xl font-semibold text-surface-900 dark:text-surface-0" dir="ltr">
          {{ invoice?.invoice_number ?? t('invoices.detail.draftTitle') }}
        </h1>
        <InvoiceStatusChip v-if="invoice" :status="invoice.status" />
      </div>
      <div v-if="invoice && canDelete && isDraft" class="flex gap-2">
        <Button
          :label="t('common.delete')"
          icon="pi pi-trash"
          severity="danger"
          outlined
          size="small"
          @click="confirmDelete"
        />
      </div>
    </div>

    <InvoiceActionsBar v-if="invoice" :invoice="invoice" />

    <Skeleton v-if="loading" height="20rem" />

    <Card v-else-if="notFound">
      <template #content>
        <p class="text-surface-600 dark:text-surface-300">{{ t('invoices.detail.notFound') }}</p>
      </template>
    </Card>

    <template v-else-if="invoice">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
          <template #title>
            <div class="flex items-center justify-between">
              <span>{{ t('invoices.detail.overview') }}</span>
              <Button
                v-if="canEditMeta"
                icon="pi pi-pencil"
                text
                rounded
                size="small"
                :aria-label="t('common.edit')"
                @click="editMetaVisible = true"
              />
            </div>
          </template>
          <template #content>
            <dl class="grid grid-cols-2 gap-y-3 text-sm">
              <dt class="text-surface-500">{{ t('invoices.detail.createdBy') }}</dt>
              <dd>{{ invoice.created_by?.name ?? '—' }}</dd>

              <dt class="text-surface-500">{{ t('invoices.list.createdAt') }}</dt>
              <dd dir="ltr" class="text-start">{{ formatDateTime(invoice.created_at) }}</dd>

              <dt class="text-surface-500">{{ t('invoices.detail.issueDate') }}</dt>
              <dd dir="ltr" class="text-start">
                {{ invoice.issue_date ? formatDate(invoice.issue_date) : '—' }}
              </dd>

              <dt class="text-surface-500">{{ t('invoices.detail.dueDate') }}</dt>
              <dd dir="ltr" class="text-start">
                {{ invoice.due_date ? formatDate(invoice.due_date) : '—' }}
              </dd>

              <template v-if="invoice.issued_at">
                <dt class="text-surface-500">{{ t('invoices.status.issued') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(invoice.issued_at) }}</dd>
              </template>
              <template v-if="invoice.voided_at">
                <dt class="text-surface-500">{{ t('invoices.status.void') }}</dt>
                <dd dir="ltr" class="text-start">{{ formatDateTime(invoice.voided_at) }}</dd>
              </template>
            </dl>
            <p v-if="invoice.notes" class="mt-3 text-sm text-surface-600 dark:text-surface-300">
              {{ invoice.notes }}
            </p>
          </template>
        </Card>

        <Card>
          <template #title>{{ t('invoices.detail.total') }}</template>
          <template #content>
            <p class="text-2xl font-semibold" dir="ltr">
              {{ invoice.total ?? '0.00' }} {{ invoice.currency_code }}
            </p>
            <dl v-if="invoice.payment_status" class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
              <dt class="text-surface-500">{{ t('invoices.detail.amountPaid') }}</dt>
              <dd dir="ltr" class="text-start">{{ invoice.amount_paid }} {{ invoice.currency_code }}</dd>

              <dt class="text-surface-500">{{ t('invoices.detail.balanceDue') }}</dt>
              <dd dir="ltr" class="text-start">{{ invoice.balance_due }} {{ invoice.currency_code }}</dd>

              <dt class="text-surface-500">{{ t('invoices.detail.paymentStatus') }}</dt>
              <dd>
                <Tag
                  :value="t(`payments.status.${invoice.payment_status}`)"
                  :severity="PAYMENT_STATUS_SEVERITY[invoice.payment_status]"
                />
              </dd>
            </dl>
          </template>
        </Card>
      </div>

      <Card>
        <template #title>
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span>{{ t('invoices.detail.items') }}</span>
            <div v-if="canAddItem" class="flex gap-2">
              <Button
                :label="t('invoices.items.addFromPlan')"
                icon="pi pi-clipboard"
                size="small"
                outlined
                @click="pickerVisible = true"
              />
              <Button :label="t('invoices.items.add')" icon="pi pi-plus" size="small" @click="openAddItem" />
            </div>
          </div>
        </template>
        <template #content>
          <p v-if="!invoice.items?.length" class="text-sm text-surface-500">
            {{ t('invoices.items.empty') }}
          </p>
          <InvoiceItemsTable v-else :invoice="invoice" :items="invoice.items" @edit-item="openEditItem" />
        </template>
      </Card>

      <InvoicePaymentsPanel :invoice="invoice" />
    </template>
  </div>

  <InvoiceItemDialog
    v-if="invoice"
    v-model:visible="itemDialogVisible"
    :invoice="invoice"
    :item="editingItem"
  />
  <AddFromTreatmentPlanDialog v-if="invoice" v-model:visible="pickerVisible" :invoice="invoice" />
  <EditInvoiceDialog v-if="invoice" v-model:visible="editMetaVisible" :invoice="invoice" />
</template>
