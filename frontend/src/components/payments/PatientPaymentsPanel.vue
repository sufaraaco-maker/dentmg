<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import PaymentsTable from './PaymentsTable.vue'
import RecordPaymentDialog from './RecordPaymentDialog.vue'
import RefundPaymentDialog from './RefundPaymentDialog.vue'
import EditPaymentDialog from './EditPaymentDialog.vue'
import ApplyPaymentDialog from './ApplyPaymentDialog.vue'
import { usePaymentsStore } from '@/stores/payments'
import { useAuthStore } from '@/stores/auth'
import type { Payment } from '@/types/payment'

/**
 * Patient Detail's dedicated Payments tab (Payments design doc §11/§15 item 5, resolved at
 * approval 2026-07-25) — every payment for the patient, including unapplied credits, sibling to
 * the Invoices tab rather than folded into it. Owns the per-patient fetch and every dialog,
 * mirroring `PatientInvoicesPanel.vue`'s identical role.
 */
const props = defineProps<{ patientId: string }>()

const { t } = useI18n()
const toast = useToast()
const paymentsStore = usePaymentsStore()
const auth = useAuthStore()

const canWrite = computed(() => auth.isAdmin || auth.isReceptionist)

const payments = computed(() => paymentsStore.paymentsForPatient(props.patientId))

watch(
  () => paymentsStore.error,
  (error) => {
    if (error) toast.add({ severity: 'error', summary: t(error), life: 3000 })
  },
)

watch(() => props.patientId, (patientId) => paymentsStore.fetchForPatient(patientId), { immediate: true })

const recordVisible = ref(false)
const refundVisible = ref(false)
const editVisible = ref(false)
const applyVisible = ref(false)
const activePayment = ref<Payment | null>(null)

function openRefund(payment: Payment) {
  activePayment.value = payment
  refundVisible.value = true
}

function openEdit(payment: Payment) {
  activePayment.value = payment
  editVisible.value = true
}

function openApply(payment: Payment) {
  activePayment.value = payment
  applyVisible.value = true
}
</script>

<template>
  <Card v-bind="$attrs">
    <template #title>
      <div class="flex items-center justify-between">
        <span>{{ t('patients.paymentsPanel.title') }}</span>
        <Button
          v-if="canWrite"
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
        show-invoice-column
        @refund-payment="openRefund"
        @edit-payment="openEdit"
        @apply-payment="openApply"
      />
    </template>
  </Card>

  <RecordPaymentDialog v-model:visible="recordVisible" :patient-id="patientId" />
  <RefundPaymentDialog v-if="activePayment" v-model:visible="refundVisible" :payment="activePayment" />
  <EditPaymentDialog v-if="activePayment" v-model:visible="editVisible" :payment="activePayment" />
  <ApplyPaymentDialog v-if="activePayment" v-model:visible="applyVisible" :payment="activePayment" />
</template>
