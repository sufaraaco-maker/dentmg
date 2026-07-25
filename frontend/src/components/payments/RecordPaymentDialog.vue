<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { usePaymentsStore } from '@/stores/payments'
import { useInvoicesStore } from '@/stores/invoices'
import { isPaymentError } from '@/services/payments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toLocalDateString } from '@/lib/date'
import { PAYMENT_METHODS, type PaymentMethod } from '@/types/payment'

/**
 * Records a payment (backend design doc §3). Two flows share this one dialog: when `invoiceId` is
 * given (opened from Invoice Detail), the payment is created applied to that invoice directly; when
 * omitted (opened from the Patient Payments tab), it's recorded as an unapplied/advance credit —
 * applying it to a specific invoice later is a separate action (`ApplyPaymentDialog.vue`), so no
 * invoice picker exists in this dialog at all (design doc §4/§11).
 */
const props = defineProps<{
  visible: boolean
  patientId: string
  invoiceId?: string | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [void]
}>()

const { t } = useI18n()
const toast = useToast()
const paymentsStore = usePaymentsStore()
const invoicesStore = useInvoicesStore()

const methodOptions = computed(() =>
  PAYMENT_METHODS.map((method) => ({ label: t(`payments.method.${method}`), value: method })),
)

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    method: 'cash' as PaymentMethod,
    amount: null as number | null,
    reference: '',
    notes: '',
    received_at: new Date(),
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, emptyForm())
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (form.amount === null || form.amount <= 0) {
    nextErrors.amount = [t('payments.dialog.fieldRequired')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    await paymentsStore.record(props.patientId, {
      invoice_id: props.invoiceId ?? null,
      method: form.method,
      amount: form.amount!,
      reference: form.reference || null,
      notes: form.notes || null,
      received_at: toLocalDateString(form.received_at),
    })

    // The invoice's amount_paid/balance_due/payment_status changed (backend design doc §6) —
    // resync the cache InvoiceDetailView reads from rather than leaving it stale.
    if (props.invoiceId) await invoicesStore.fetchOne(props.invoiceId)

    toast.add({ severity: 'success', summary: t('payments.dialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isPaymentError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('payments.dialog.saveError'), life: 3000 })
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
    :header="t('payments.dialog.recordTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('payments.list.method')
          }}</label>
          <Select
            v-model="form.method"
            :options="methodOptions"
            option-label="label"
            option-value="value"
            fluid
          />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('payments.dialog.amount')
          }}</label>
          <InputNumber
            v-model="form.amount"
            :min="0"
            :min-fraction-digits="2"
            :max-fraction-digits="2"
            autofocus
            :invalid="!!errors.amount"
            fluid
          />
          <Message v-if="errors.amount" severity="error" size="small">{{ errors.amount[0] }}</Message>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('payments.dialog.reference')
          }}</label>
          <InputText v-model="form.reference" fluid />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('payments.dialog.receivedAt')
          }}</label>
          <DatePicker v-model="form.received_at" date-format="yy-mm-dd" show-icon fluid />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('payments.dialog.notes') }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
