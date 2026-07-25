<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import DatePicker from 'primevue/datepicker'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useInvoicesStore } from '@/stores/invoices'
import { isInvoiceError } from '@/services/invoices'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { parseLocalDate, toLocalDateString } from '@/lib/date'
import type { Invoice } from '@/types/invoice'

const NOTES_MAX = 2000

/**
 * Edits the invoice's administrative metadata (notes/issue_date/due_date) — the only fields
 * `PUT /invoices/{invoice}` accepts (backend design doc §9). Only reachable while `draft`
 * (`InvoiceDetailView.vue` gates the trigger button on that); the backend re-enforces the same
 * lock regardless (`InvoiceService::assertEditable()`), this dialog just avoids offering a
 * doomed request.
 */
const props = defineProps<{
  visible: boolean
  invoice: Invoice
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [invoice: Invoice]
}>()

const { t } = useI18n()
const toast = useToast()
const invoicesStore = useInvoicesStore()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    issue_date: null as Date | null,
    due_date: null as Date | null,
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return
    errors.value = {}
    Object.assign(form, {
      issue_date: props.invoice.issue_date ? parseLocalDate(props.invoice.issue_date) : null,
      due_date: props.invoice.due_date ? parseLocalDate(props.invoice.due_date) : null,
      notes: props.invoice.notes ?? '',
    })
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

const notesRemaining = ref(NOTES_MAX)
watch(
  () => form.notes,
  (notes) => {
    notesRemaining.value = NOTES_MAX - notes.length
  },
)

async function submit() {
  saving.value = true
  errors.value = {}

  try {
    const saved = await invoicesStore.update(props.invoice.id, {
      issue_date: form.issue_date ? toLocalDateString(form.issue_date) : null,
      due_date: form.due_date ? toLocalDateString(form.due_date) : null,
      notes: form.notes || null,
    })

    toast.add({ severity: 'success', summary: t('invoices.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isInvoiceError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('invoices.dialog.saveError'), life: 3000 })
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
    :header="t('invoices.dialog.editTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('invoices.dialog.issueDate')
          }}</label>
          <DatePicker
            v-model="form.issue_date"
            date-format="yy-mm-dd"
            show-icon
            show-clear
            autofocus
            fluid
            :invalid="!!errors.issue_date"
          />
          <Message v-if="errors.issue_date" severity="error" size="small">{{ errors.issue_date[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('invoices.dialog.dueDate')
          }}</label>
          <DatePicker
            v-model="form.due_date"
            date-format="yy-mm-dd"
            show-icon
            show-clear
            fluid
            :invalid="!!errors.due_date"
          />
          <Message v-if="errors.due_date" severity="error" size="small">{{ errors.due_date[0] }}</Message>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{ t('invoices.dialog.notes') }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
        <span v-if="notesRemaining < NOTES_MAX * 0.2" class="text-xs text-surface-400">
          {{ t('invoices.dialog.charactersRemaining', { n: notesRemaining }) }}
        </span>
        <Message v-if="errors.notes" severity="error" size="small">{{ errors.notes[0] }}</Message>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
