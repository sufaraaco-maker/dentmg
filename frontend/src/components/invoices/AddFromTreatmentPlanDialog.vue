<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import { useInvoicesStore } from '@/stores/invoices'
import { isInvoiceError } from '@/services/invoices'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toothDisplayName } from '@/lib/teeth'
import type { Invoice } from '@/types/invoice'
import type { TreatmentPlanItem } from '@/types/treatmentPlan'

/**
 * The "Add completed items" picker (backend design doc §3 step 1/§9/§11) — lists the patient's
 * `completed` Treatment Plan items not already on a non-void invoice
 * (`GET /patients/{patient}/treatment-plan-items/billable`, a derived read, never a stored flag —
 * §7) and adds each selection as a `charge`-kind line via
 * `POST /invoices/{invoice}/items` (one request per item; the backend has no bulk-add endpoint).
 * Sequential, not parallel, so the invoice total shown mid-batch and the store's cache stay
 * consistent with the last-committed response rather than racing.
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

const loading = ref(false)
const adding = ref(false)
const items = ref<TreatmentPlanItem[]>([])
const selected = ref<TreatmentPlanItem[]>([])

watch(
  () => props.visible,
  async (visible) => {
    if (!visible) return

    selected.value = []
    loading.value = true

    try {
      items.value = await invoicesStore.billableTreatmentPlanItems(props.invoice.patient_id)
    } catch {
      toast.add({ severity: 'error', summary: t('invoices.items.picker.loadError'), life: 3000 })
      items.value = []
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

useDialogFocusRestore(() => props.visible)

async function submit() {
  if (!selected.value.length) return

  adding.value = true
  let saved: Invoice = props.invoice
  let failures = 0

  for (const item of selected.value) {
    try {
      saved = await invoicesStore.addItem(props.invoice.id, {
        kind: 'charge',
        treatment_plan_item_id: item.id,
      })
    } catch (err) {
      failures += 1
      if (isInvoiceError(err)) {
        toast.add({ severity: 'error', summary: err.message, life: 4000 })
      }
    }
  }

  adding.value = false

  if (failures === 0) {
    toast.add({ severity: 'success', summary: t('invoices.items.picker.added'), life: 3000 })
  } else if (failures < selected.value.length) {
    toast.add({ severity: 'warn', summary: t('invoices.items.picker.partialError'), life: 4000 })
  } else {
    toast.add({ severity: 'error', summary: t('invoices.items.picker.addError'), life: 3000 })
    return
  }

  emit('saved', saved)
  emit('update:visible', false)
}
</script>

<template>
  <Dialog
    :visible="visible"
    modal
    :header="t('invoices.items.picker.title')"
    class="w-full max-w-3xl"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <p v-if="!loading && !items.length" class="text-sm text-surface-500">
      {{ t('invoices.items.picker.empty') }}
    </p>

    <DataTable
      v-else
      v-model:selection="selected"
      :value="items"
      :loading="loading"
      data-key="id"
      paginator
      :rows="10"
    >
      <Column selection-mode="multiple" header-style="width: 3rem" />

      <Column :header="t('invoices.items.picker.procedure')">
        <template #body="{ data }">{{ (data as TreatmentPlanItem).procedure_name }}</template>
      </Column>

      <Column :header="t('invoices.items.picker.tooth')">
        <template #body="{ data }">
          <span v-if="(data as TreatmentPlanItem).tooth_number">
            {{ (data as TreatmentPlanItem).tooth_number }} —
            {{ toothDisplayName((data as TreatmentPlanItem).tooth_number!) }}
          </span>
          <span v-else>—</span>
        </template>
      </Column>

      <Column :header="t('invoices.items.picker.quantity')">
        <template #body="{ data }">{{ (data as TreatmentPlanItem).quantity }}</template>
      </Column>

      <Column :header="t('invoices.items.picker.cost')">
        <template #body="{ data }"><span dir="ltr">{{ (data as TreatmentPlanItem).estimated_cost }}</span></template>
      </Column>
    </DataTable>

    <template #footer>
      <div class="flex flex-wrap items-center justify-end gap-2">
        <span class="me-auto text-sm text-surface-500">
          {{ t('invoices.items.picker.selectedCount', { n: selected.length }) }}
        </span>
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button
          type="button"
          :label="t('invoices.items.picker.add')"
          :disabled="!selected.length"
          :loading="adding"
          @click="submit"
        />
      </div>
    </template>
  </Dialog>
</template>
