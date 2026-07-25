<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useInvoicesStore } from '@/stores/invoices'
import { isInvoiceError } from '@/services/invoices'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { INVOICE_ITEM_KINDS, type Invoice, type InvoiceItem, type InvoiceItemKind } from '@/types/invoice'

const NOTES_MAX = 2000

/**
 * Add/Edit a manual line (charge, discount, or tax) — the "Add Charge" counterpart to
 * `AddFromTreatmentPlanDialog.vue`'s picker (backend design doc §11). Only ever writes
 * `kind`/`description`/`unit_amount`/`quantity`/`notes`; never sets `treatment_plan_item_id`
 * itself — a line already linked to one (via the picker) keeps `kind` locked to `charge` here
 * since unlinking isn't a supported edit (mirrors `InvoiceService::assertKindAllowsSource()`).
 * Only ever reachable while the parent invoice is still `draft` — `InvoiceItemsTable.vue` gates
 * the trigger buttons on that; the backend re-enforces the same lock regardless
 * (`InvoiceService::assertEditable()`).
 */
const props = defineProps<{
  visible: boolean
  invoice: Invoice
  item?: InvoiceItem | null
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  saved: [invoice: Invoice]
}>()

const { t } = useI18n()
const toast = useToast()
const invoicesStore = useInvoicesStore()

const isEditMode = computed(() => Boolean(props.item))
const isLinkedToTreatmentPlan = computed(() => Boolean(props.item?.treatment_plan_item_id))

const kindOptions = computed(() =>
  INVOICE_ITEM_KINDS.map((kind) => ({ label: t(`invoices.items.kind.${kind}`), value: kind })),
)

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return {
    kind: 'charge' as InvoiceItemKind,
    description: '',
    unit_amount: null as number | null,
    quantity: 1,
    notes: '',
  }
}

const form = reactive(emptyForm())

watch(
  () => props.visible,
  (visible) => {
    if (!visible) return

    errors.value = {}
    const source = props.item

    if (source) {
      Object.assign(form, {
        kind: source.kind,
        description: source.description,
        unit_amount: Number(source.unit_amount),
        quantity: source.quantity,
        notes: source.notes ?? '',
      })
    } else {
      Object.assign(form, emptyForm())
    }
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

const notesRemaining = computed(() => NOTES_MAX - form.notes.length)
const showNotesCounter = computed(() => form.notes.length > NOTES_MAX * 0.8)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.description.trim()) nextErrors.description = [t('invoices.items.dialog.fieldRequired')]
  if (form.unit_amount === null || form.unit_amount < 0) {
    nextErrors.unit_amount = [t('invoices.items.dialog.fieldRequired')]
  }
  if (!form.quantity || form.quantity < 1) nextErrors.quantity = [t('invoices.items.dialog.fieldRequired')]

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      kind: form.kind,
      description: form.description,
      unit_amount: form.unit_amount,
      quantity: form.quantity,
      notes: form.notes || null,
    }

    const saved = isEditMode.value
      ? await invoicesStore.updateItem(props.item!.id, payload)
      : await invoicesStore.addItem(props.invoice.id, payload)

    toast.add({ severity: 'success', summary: t('invoices.items.dialog.saved'), life: 3000 })
    emit('saved', saved)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isInvoiceError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
    } else if ((err as { response?: { status?: number } })?.response?.status === 422) {
      errors.value =
        (err as { response: { data: { errors?: Record<string, string[]> } } }).response.data.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('invoices.items.dialog.saveError'), life: 3000 })
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
    :header="isEditMode ? t('invoices.items.dialog.editTitle') : t('invoices.items.dialog.createTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div
        v-if="isLinkedToTreatmentPlan"
        class="flex flex-col gap-1 text-sm text-surface-600 dark:text-surface-300"
      >
        <span class="font-medium">{{ t('invoices.items.dialog.linkedProcedure') }}</span>
        <span>{{ item?.treatment_plan_item?.procedure_name }}</span>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('invoices.items.dialog.kind')
        }}</label>
        <Select
          v-model="form.kind"
          :options="kindOptions"
          option-label="label"
          option-value="value"
          :disabled="isLinkedToTreatmentPlan"
          :invalid="!!errors.kind"
          fluid
        />
        <Message v-if="errors.kind" severity="error" size="small">{{ errors.kind[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('invoices.items.dialog.description')
        }}</label>
        <InputText v-model="form.description" autofocus :invalid="!!errors.description" fluid />
        <Message v-if="errors.description" severity="error" size="small">{{ errors.description[0] }}</Message>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('invoices.items.quantity')
          }}</label>
          <InputNumber v-model="form.quantity" :min="1" show-buttons :invalid="!!errors.quantity" fluid />
          <Message v-if="errors.quantity" severity="error" size="small">{{ errors.quantity[0] }}</Message>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-sm text-surface-700 dark:text-surface-200">{{
            t('invoices.items.unitAmount')
          }}</label>
          <InputNumber
            v-model="form.unit_amount"
            :min="0"
            :min-fraction-digits="2"
            :max-fraction-digits="2"
            :invalid="!!errors.unit_amount"
            fluid
          />
          <Message v-if="errors.unit_amount" severity="error" size="small">{{
            errors.unit_amount[0]
          }}</Message>
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-sm text-surface-700 dark:text-surface-200">{{
          t('invoices.items.dialog.notes')
        }}</label>
        <Textarea v-model="form.notes" rows="3" auto-resize maxlength="2000" />
        <span v-if="showNotesCounter" class="text-xs text-surface-400">
          {{ t('invoices.dialog.charactersRemaining', { n: notesRemaining }) }}
        </span>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.save')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
