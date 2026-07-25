<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import PaymentMethodTag from './PaymentMethodTag.vue'
import { usePaymentsStore } from '@/stores/payments'
import { useInvoicesStore } from '@/stores/invoices'
import { useAuthStore } from '@/stores/auth'
import { isPaymentError } from '@/services/payments'
import { parseLocalDate } from '@/lib/date'
import type { Payment } from '@/types/payment'

/**
 * Shared payment list, backing both the Patient Payments tab (`showInvoiceColumn` — every payment,
 * including unapplied credits) and the Invoice Detail Payments panel (invoice-scoped, column
 * hidden — backend design doc §11). Delete is handled internally (confirm + store call), mirroring
 * `InvoiceItemsTable.vue`'s identical precedent; edit/refund/apply are emitted so the host owns
 * which dialog opens, since those dialogs need context (the full `Payment`) the host already has.
 */
defineProps<{
  payments: Payment[]
  loading: boolean
  showInvoiceColumn?: boolean
}>()

const emit = defineEmits<{
  'edit-payment': [payment: Payment]
  'refund-payment': [payment: Payment]
  'apply-payment': [payment: Payment]
}>()

const { t, locale } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const paymentsStore = usePaymentsStore()
const invoicesStore = useInvoicesStore()
const auth = useAuthStore()

// Matches every other write ability in this financial-module family (backend design doc §10):
// admin + receptionist write, dentist read-only.
const canWrite = () => auth.isAdmin || auth.isReceptionist
const canDelete = () => auth.isAdmin

function formatDate(value: string): string {
  return new Intl.DateTimeFormat(locale.value, { dateStyle: 'medium' }).format(parseLocalDate(value))
}

function invoiceLabel(payment: Payment): string {
  if (payment.invoice_id === null) return t('payments.unapplied')
  return invoicesStore.cache.get(payment.invoice_id)?.invoice_number ?? t('payments.unapplied')
}

function canRefund(payment: Payment): boolean {
  return canWrite() && !payment.is_refund && Number(payment.remaining_refundable_amount) > 0
}

function canApply(payment: Payment): boolean {
  return canWrite() && payment.invoice_id === null && !payment.is_refund
}

function canDeletePayment(payment: Payment): boolean {
  return canDelete() && payment.remaining_refundable_amount === payment.amount
}

function confirmDelete(payment: Payment) {
  confirm.require({
    message: t('payments.deleteConfirmMessage'),
    header: t('payments.deleteConfirmHeader'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await paymentsStore.remove(payment.id)
        toast.add({ severity: 'success', summary: t('payments.deleted'), life: 3000 })
      } catch (err) {
        if (isPaymentError(err)) {
          toast.add({ severity: 'error', summary: err.message, life: 4000 })
        } else {
          toast.add({ severity: 'error', summary: t('payments.deleteError'), life: 3000 })
        }
      }
    },
  })
}
</script>

<template>
  <DataTable :value="payments" :loading="loading" data-key="id" paginator :rows="10">
    <template #empty>{{ t('payments.empty') }}</template>

    <Column field="received_at" :header="t('payments.list.receivedAt')" sortable>
      <template #body="{ data }"
        ><span dir="ltr">{{ formatDate((data as Payment).received_at) }}</span></template
      >
    </Column>

    <Column :header="t('payments.list.method')">
      <template #body="{ data }"
        ><PaymentMethodTag :method="(data as Payment).method" size="small"
      /></template>
    </Column>

    <Column v-if="showInvoiceColumn" :header="t('payments.list.invoice')">
      <template #body="{ data }">
        <Tag
          v-if="(data as Payment).invoice_id === null"
          :value="t('payments.unapplied')"
          severity="secondary"
          class="text-xs px-2 py-0.5"
        />
        <span v-else dir="ltr">{{ invoiceLabel(data as Payment) }}</span>
      </template>
    </Column>

    <Column :header="t('payments.list.reference')">
      <template #body="{ data }">{{ (data as Payment).reference ?? '—' }}</template>
    </Column>

    <Column :header="t('payments.list.amount')">
      <template #body="{ data }">
        <span dir="ltr" :class="(data as Payment).is_refund ? 'text-red-600 dark:text-red-400' : undefined">
          {{ (data as Payment).amount }} {{ (data as Payment).currency_code }}
        </span>
      </template>
    </Column>

    <Column :header="t('payments.list.actions')" style="width: 10rem">
      <template #body="{ data }">
        <div class="flex gap-1">
          <Button
            v-if="canApply(data as Payment)"
            icon="pi pi-link"
            text
            rounded
            size="small"
            :aria-label="t('payments.actions.apply')"
            @click="emit('apply-payment', data as Payment)"
          />
          <Button
            v-if="canRefund(data as Payment)"
            icon="pi pi-replay"
            text
            rounded
            size="small"
            :aria-label="t('payments.actions.refund')"
            @click="emit('refund-payment', data as Payment)"
          />
          <Button
            v-if="canWrite()"
            icon="pi pi-pencil"
            text
            rounded
            size="small"
            :aria-label="t('common.edit')"
            @click="emit('edit-payment', data as Payment)"
          />
          <Button
            v-if="canDeletePayment(data as Payment)"
            icon="pi pi-trash"
            text
            rounded
            size="small"
            severity="danger"
            :aria-label="t('common.delete')"
            @click="confirmDelete(data as Payment)"
          />
        </div>
      </template>
    </Column>
  </DataTable>
</template>
