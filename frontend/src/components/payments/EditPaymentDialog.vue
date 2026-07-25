<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import { usePaymentsStore } from '@/stores/payments'
import { isPaymentError } from '@/services/payments'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseLocalDate, toLocalDateString } from '@/lib/date'
import type { Payment } from '@/types/payment'

/**
 * Edits a payment's administrative metadata — reference/notes/received_at are the only fields
 * `PUT /payments/{payment}` accepts (backend design doc §8); amount/method/currency_code/
 * patient_id are immutable after creation, so this dialog never offers them.
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

const saving = ref(false)

function emptyForm() {
  return {
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
    Object.assign(form, {
      reference: props.payment.reference ?? '',
      notes: props.payment.notes ?? '',
      received_at: parseLocalDate(props.payment.received_at),
    })
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

async function submit() {
  saving.value = true

  try {
    await paymentsStore.update(props.payment.id, {
      reference: form.reference || null,
      notes: form.notes || null,
      received_at: toLocalDateString(form.received_at),
    })

    toast.add({ severity: 'success', summary: t('payments.dialog.saved'), life: 3000 })
    emit('saved')
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isPaymentError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
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
    :header="t('payments.dialog.editTitle')"
    class="w-full max-w-md"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('payments.dialog.reference') }}</label>
          <InputText v-model="form.reference" autofocus fluid />
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('payments.dialog.receivedAt') }}</label>
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
