<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { isInventoryError } from '@/services/inventory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import type { PurchaseOrder, Supply } from '@/types/inventory'

/**
 * Adds an item to a draft Purchase Order (design doc §3/§6/§9). Description/unit cost are
 * optional — `PurchaseOrderService::addItem()` defaults them from the picked Supply's own
 * name/SKU/unit_cost when left blank, so those fields start empty here rather than
 * pre-guessed client-side.
 */
const props = defineProps<{ visible: boolean; purchaseOrder: PurchaseOrder }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  added: [order: PurchaseOrder]
}>()

const { t } = useI18n()
const toast = useToast()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})
const supplies = ref<Supply[]>([])
const loadingSupplies = ref(false)

const supplyOptions = computed(() => supplies.value.map((s) => ({ value: s.id, label: s.name, supply: s })))
const selectedSupply = computed(() => supplies.value.find((s) => s.id === form.supply_id) ?? null)

function emptyForm() {
  return {
    supply_id: null as string | null,
    quantity_ordered: 1,
    unit_cost: null as number | null,
    description: '',
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

onMounted(async () => {
  loadingSupplies.value = true
  try {
    const { data } = await api.get<{ data: Supply[] }>('/supplies', { params: { per_page: 100 } })
    supplies.value = data.data.filter((s) => s.is_active)
  } finally {
    loadingSupplies.value = false
  }
})

useDialogFocusRestore(() => props.visible)

function validate(): boolean {
  const nextErrors: Record<string, string[]> = {}

  if (!form.supply_id) nextErrors.supply_id = [t('inventory.purchaseOrders.items.fieldRequired')]
  if (!form.quantity_ordered || form.quantity_ordered <= 0) {
    nextErrors.quantity_ordered = [t('inventory.purchaseOrders.items.fieldRequired')]
  }

  errors.value = nextErrors
  return Object.keys(nextErrors).length === 0
}

async function submit() {
  if (!validate()) return

  saving.value = true
  errors.value = {}

  try {
    const payload = {
      supply_id: form.supply_id,
      quantity_ordered: form.quantity_ordered,
      unit_cost: form.unit_cost,
      description: form.description.trim() || null,
    }

    const { data } = await api.post<PurchaseOrder>(`/purchase-orders/${props.purchaseOrder.id}/items`, payload)

    toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.items.added'), life: 3000 })
    emit('added', data)
    emit('update:visible', false)
  } catch (err: unknown) {
    if (isInventoryError(err)) {
      toast.add({ severity: 'error', summary: err.message, life: 4000 })
      return
    }

    const response = (err as { response?: { status?: number; data?: { errors?: Record<string, string[]> } } })
      ?.response

    if (response?.status === 422) {
      errors.value = response.data?.errors ?? {}
    } else {
      toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.items.addError'), life: 3000 })
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
    :header="t('inventory.purchaseOrders.items.addTitle')"
    class="w-full max-w-lg"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <div class="flex flex-col gap-2">
        <label for="item-supply" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.items.supply') }}
        </label>
        <Select
          id="item-supply"
          v-model="form.supply_id"
          :options="supplyOptions"
          option-label="label"
          option-value="value"
          filter
          :loading="loadingSupplies"
          :placeholder="t('inventory.purchaseOrders.items.selectSupply')"
          :invalid="!!errors.supply_id"
          fluid
        />
        <Message v-if="errors.supply_id" severity="error" size="small">{{ errors.supply_id[0] }}</Message>
        <p v-if="selectedSupply?.unit_cost" class="text-xs text-surface-500 dark:text-surface-400">
          {{ t('inventory.purchaseOrders.items.defaultCostHint', { cost: selectedSupply.unit_cost }) }}
        </p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-2">
          <label for="item-quantity" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.purchaseOrders.items.quantityOrdered') }}
          </label>
          <InputNumber
            id="item-quantity"
            v-model="form.quantity_ordered"
            :min="1"
            :invalid="!!errors.quantity_ordered"
            fluid
          />
          <Message v-if="errors.quantity_ordered" severity="error" size="small">
            {{ errors.quantity_ordered[0] }}
          </Message>
        </div>
        <div class="flex flex-col gap-2">
          <label for="item-unit-cost" class="text-sm text-surface-700 dark:text-surface-200">
            {{ t('inventory.purchaseOrders.items.unitCostOverride') }}
          </label>
          <InputNumber id="item-unit-cost" v-model="form.unit_cost" :min="0" :max-fraction-digits="2" fluid />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label for="item-description" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.items.descriptionOverride') }}
        </label>
        <InputText id="item-description" v-model="form.description" fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('common.add')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
