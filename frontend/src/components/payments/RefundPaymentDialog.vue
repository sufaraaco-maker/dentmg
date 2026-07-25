<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { usePaymentsStore } from '@/stores/payments'
import { useInvoicesStore } from '@/stores/invoices'
import { isPaymentError } from '@/services/payments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { Payment } from '@/types/payment'

/**
 * Refunds a payment, fully or partially, capped at its own `remaining_refundable_amount` (backend
 * design doc §4/§8) — creates a new negative `Payment` row; the original is never edited.
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

const form = reactive({
  amount: null as number | null,
  notes: '',
})

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    form.amount = Number(props.payment.remaining_refundable_amount)
    form.notes = ''
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const max = Number(props.payment.remaining_refundable_amount)
  const nextErrors: Record<string, string[]> = {}

  if (form.amount === null || form.amount <= 0 || form.amount > max) {
    nextErrors.amount = [t('payments.refundDialog.amountInvalid', { max })]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    await paymentsStore.refund(props.payment.id, {
      amount: form.amount!,
      notes: form.notes || null,
    })

    if (props.payment.invoice_id) await invoicesStore.fetchOne(props.payment.invoice_id)

    toast.add({ severity: 'success', summary: t('payments.refundDialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isPaymentError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else {
      toast.add({ severity: 'error', summary: t('payments.refundDialog.saveError'), life: 3000 })
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
    :header="t('payments.refundDialog.title')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <p class="text-sm text-surface-600 dark:text-surface-300">
        {{
          t('payments.refundDialog.remaining', {
            amount: payment.remaining_refundable_amount,
            currency: payment.currency_code,
          })
        }}
      </p>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('payments.dialog.amount')
        }}</label>
        <InputNumber
          v-model="form.amount"
          :min="0"
          :max="Number(payment.remaining_refundable_amount)"
          :min-fraction-digits="2"
          :max-fraction-digits="2"
          autofocus
          :invalid="!!errors.amount"
          fluid
        />
        <Message v-if="errors.amount" severity="error" size="small">{{ errors.amount[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('payments.dialog.notes') }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('payments.actions.refund')" severity="danger" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
