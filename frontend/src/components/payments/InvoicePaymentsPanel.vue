<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import Card from 'primevue/card'
import Button from 'primevue/button'
import PaymentsTable from './PaymentsTable.vue'
import RecordPaymentDialog from './RecordPaymentDialog.vue'
import RefundPaymentDialog from './RefundPaymentDialog.vue'
import EditPaymentDialog from './EditPaymentDialog.vue'
import { usePaymentsStore } from '@/stores/payments'
import { useAuthStore } from '@/stores/auth'
import type { Invoice } from '@/types/invoice'
import type { Payment } from '@/types/payment'

/**
 * Invoice Detail's Payments panel (backend design doc §11) — payments applied to this invoice,
 * filtered client-side from the patient's full payment list (no
 * `GET /api/invoices/{invoice}/payments` exists, §9/§7). "Record Payment" is only offered while
 * the invoice is `issued` (the only status `PaymentService` accepts a direct/apply target).
 */
const props = defineProps<{ invoice: Invoice }>()

const { t } = useI18n()
const paymentsStore = usePaymentsStore()
const auth = useAuthStore()

const canWrite = computed(() => auth.isAdmin || auth.isReceptionist)
const canRecord = computed(() => canWrite.value && props.invoice.status === 'issued')

const payments = computed(() => paymentsStore.paymentsForInvoice(props.invoice.id))

watch(
  () => props.invoice.patient_id,
  (patientId) => paymentsStore.fetchForPatient(patientId),
  { immediate: true },
)

const recordVisible = ref(false)
const refundVisible = ref(false)
const editVisible = ref(false)
const activePayment = ref<Payment | null>(null)

function openRefund(payment: Payment) {
  activePayment.value = payment
  refundVisible.value = true
}

function openEdit(payment: Payment) {
  activePayment.value = payment
  editVisible.value = true
}
</script>

<template>
  <Card>
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('invoices.detail.payments') }}</span>
        <Button
          v-if="canRecord"
          :label="t('patients.paymentsPanel.new')"
          icon="pi pi-plus"
          size="small"
          @click="recordVisible = true"
        />
      </div>
    </template>
    <template #content>
      <p v-if="!paymentsStore.loading && !payments.length" class="text-sm text-surface-500">
        {{ t('patients.paymentsPanel.empty') }}
      </p>
      <PaymentsTable
        v-else
        :payments="payments"
        :loading="paymentsStore.loading"
        @refund-payment="openRefund"
        @edit-payment="openEdit"
      />
    </template>
  </Card>

  <RecordPaymentDialog
    v-model:visible="recordVisible"
    :patient-id="invoice.patient_id"
    :invoice-id="invoice.id"
  />
  <RefundPaymentDialog v-if="activePayment" v-model:visible="refundVisible" :payment="activePayment" />
  <EditPaymentDialog v-if="activePayment" v-model:visible="editVisible" :payment="activePayment" />
</template>
