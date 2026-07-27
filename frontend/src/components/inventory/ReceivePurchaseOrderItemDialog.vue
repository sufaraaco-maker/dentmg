<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import Message from 'primevue/message'
import { api } from '@/lib/api'
import { isInventoryError } from '@/services/inventory'
import { useDialogFocusRestore } from '@/composables/useDialogFocusRestore'
import { toLocalDateString } from '@/lib/date'
import type { PurchaseOrder, PurchaseOrderItem } from '@/types/inventory'

/**
 * Receives quantity against one Purchase Order item (design doc §3/§8/§15 Decision 5) — hard
 * capped server-side at `quantity_remaining`; the optional expiration date is the lightweight
 * per-receipt tracking approved in Decision 2, stored on the `received` Stock Movement this
 * generates.
 */
const props = defineProps<{ visible: boolean; item: PurchaseOrderItem }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  received: [order: PurchaseOrder]
}>()

const { t } = useI18n()
const toast = useToast()

const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

function emptyForm() {
  return { quantity: props.item.quantity_remaining, expiration_date: null as Date | null }
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

  if (!form.quantity || form.quantity <= 0) {
    nextErrors.quantity = [t('inventory.purchaseOrders.items.fieldRequired')]
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
      quantity: form.quantity,
      expiration_date: form.expiration_date ? toLocalDateString(form.expiration_date) : null,
    }

    const { data } = await api.post<PurchaseOrder>(`/purchase-order-items/${props.item.id}/receive`, payload)

    toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.items.received'), life: 3000 })
    emit('received', data)
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
      toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.items.receiveError'), life: 3000 })
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
    :header="t('inventory.purchaseOrders.items.receiveTitle', { name: item.description })"
    class="w-full max-w-sm"
    @update:visible="(v) => emit('update:visible', v)"
  >
    <form class="flex flex-col gap-4" @submit.prevent="submit">
      <p class="text-sm text-surface-500 dark:text-surface-400">
        {{ t('inventory.purchaseOrders.items.remainingHint', { n: item.quantity_remaining }) }}
      </p>

      <div class="flex flex-col gap-2">
        <label for="receive-quantity" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.purchaseOrders.items.quantityReceivedNow') }}
        </label>
        <InputNumber
          v-model="form.quantity"
          input-id="receive-quantity"
          :min="1"
          :max="item.quantity_remaining"
          :invalid="!!errors.quantity"
          fluid
        />
        <Message v-if="errors.quantity" severity="error" size="small">{{ errors.quantity[0] }}</Message>
      </div>

      <div class="flex flex-col gap-2">
        <label for="receive-expiration" class="text-sm text-surface-700 dark:text-surface-200">
          {{ t('inventory.movements.expirationDate') }}
        </label>
        <DatePicker v-model="form.expiration_date" input-id="receive-expiration" show-icon fluid />
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button type="button" :label="t('common.cancel')" text @click="emit('update:visible', false)" />
        <Button type="submit" :label="t('inventory.purchaseOrders.items.receive')" :loading="saving" />
      </div>
    </form>
  </Dialog>
</template>
