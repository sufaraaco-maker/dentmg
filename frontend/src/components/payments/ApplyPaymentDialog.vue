<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { usePaymentsStore } from '@/stores/payments'
import { useInvoicesStore } from '@/stores/invoices'
import { isPaymentError } from '@/services/payments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { Payment } from '@/types/payment'

/**
 * Applies a currently-unapplied payment to a specific issued invoice (backend design doc §3/§8) —
 * allowed exactly once, full-amount only. Lists only the patient's `issued` invoices, since that's
 * the only status PaymentService::apply() accepts.
 */
const props = defineProps<{
  visible: boolean
  payment: Payment
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [void]
}>()

const { t } = useI18n()
const toast = useToast()
const paymentsStore = usePaymentsStore()
const invoicesStore = useInvoicesStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const selectedInvoiceId = ref<string | null>(null)

const invoiceOptions = computed(() =>
  invoicesStore
    .invoicesForPatient(props.payment.patient_id)
    .filter((invoice) => invoice.status === 'issued')
    .map((invoice) => ({
      label: `${invoice.invoice_number} — ${invoice.balance_due ?? invoice.total ?? '0.00'} ${invoice.currency_code}`,
      value: invoice.id,
    })),
)

watch(
  () => props.visible,
  async (visible) => {
    if (!visible) return
    errors.value = {}
    selectedInvoiceId.value = null
    await invoicesStore.fetchForPatient(props.payment.patient_id)
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

async function submit() {
  if (!selectedInvoiceId.value) {
    errors.value = { invoice_id: [t('payments.dialog.fieldRequired')] }
    return
  }

  saving.value = true
  errors.value = {}

  try {
    await paymentsStore.apply(props.payment.id, { invoice_id: selectedInvoiceId.value })
    await invoicesStore.fetchOne(selectedInvoiceId.value)

    toast.add({ severity: 'success', summary: t('payments.applyDialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isPaymentError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else {
      toast.add({ severity: 'error', summary: t('payments.applyDialog.saveError'), life: 3000 })
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('payments.applyDialog.title')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <p v-if="!invoiceOptions.length" class="text-sm text-surface-500">
        {{ t('payments.applyDialog.empty') }}
      </p>
      <div v-else class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('payments.applyDialog.invoice')
        }}</label>
        <Select
          v-model="selectedInvoiceId"
          :options="invoiceOptions"
          option-label="label"
          option-value="value"
          :invalid="!!errors.invoice_id"
          fluid
        />
        <Message v-if="errors.invoice_id" severity="error" size="small">{{ errors.invoice_id[0] }}</Message>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button
          type="submit"
          :label="t('payments.actions.apply')"
          :disabled="!invoiceOptions.length"
          :loading="saving"
        />
      </div>
    </form>
  </Dialog>
</template>
