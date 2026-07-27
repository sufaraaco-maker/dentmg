<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import Button from 'primevue/button'
import { api } from '@/lib/api'
import { isInventoryError } from '@/services/inventory'
import { useAuthStore } from '@/stores/auth'
import type { PurchaseOrder } from '@/types/inventory'

/**
 * Purchase Order status-transition buttons (design doc §5/§8/§10/§11) — Place (draft only, at
 * least one item) and Cancel (draft/placed, only while nothing has been received yet). Mirrors
 * `InvoiceActionsBar.vue`'s exact shape, but emits the updated order rather than reading from a
 * Pinia store cache — Purchase Orders is a paginated list (design doc §13), not a small cache.
 */
const props = defineProps<{ purchaseOrder: PurchaseOrder }>()

const emit = defineEmits<{ updated: [order: PurchaseOrder] }>()

const { t } = useI18n()
const toast = useToast()
const confirm = useConfirm()
const auth = useAuthStore()

// Procurement is front-desk/administrative work (design doc §10): admin + receptionist only.
const canManage = computed(() => auth.isAdmin || auth.isReceptionist)

const busy = ref(false)

const canPlace = computed(() => canManage.value && props.purchaseOrder.status === 'draft')
// `partially_received` by definition means at least one item already has quantity_received > 0,
// so the backend's "cancel only while nothing has been received" guard (design doc §8) would
// always reject it anyway — never shown as an option once that far along.
const canCancel = computed(() => canManage.value && ['draft', 'placed'].includes(props.purchaseOrder.status))

async function handleError(err: unknown): Promise<void> {
  if (isInventoryError(err)) {
    toast.add({ severity: 'error', summary: err.message, life: 4000 })
    return
  }

  const status = (err as { response?: { status?: number } })?.response?.status

  if (status === 403) {
    toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.forbidden'), life: 3000 })
  } else {
    toast.add({ severity: 'error', summary: t('inventory.purchaseOrders.actionError'), life: 3000 })
  }
}

async function place(): Promise<void> {
  busy.value = true
  try {
    const { data } = await api.post<PurchaseOrder>(`/purchase-orders/${props.purchaseOrder.id}/place`)
    toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.actionSuccess'), life: 3000 })
    emit('updated', data)
  } catch (err) {
    await handleError(err)
  } finally {
    busy.value = false
  }
}

function confirmCancel(): void {
  confirm.require({
    message: t('inventory.purchaseOrders.confirmCancelMessage'),
    header: t('inventory.purchaseOrders.actions.cancel'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      busy.value = true
      try {
        const { data } = await api.post<PurchaseOrder>(`/purchase-orders/${props.purchaseOrder.id}/cancel`)
        toast.add({ severity: 'success', summary: t('inventory.purchaseOrders.actionSuccess'), life: 3000 })
        emit('updated', data)
      } catch (err) {
        await handleError(err)
      } finally {
        busy.value = false
      }
    },
  })
}
</script>

<template>
  <div v-if="canPlace || canCancel" class="flex flex-wrap gap-2">
    <Button
      v-if="canPlace"
      :label="t('inventory.purchaseOrders.actions.place')"
      icon="pi pi-send"
      severity="success"
      :loading="busy"
      size="small"
      outlined
      @click="place"
    />
    <Button
      v-if="canCancel"
      :label="t('inventory.purchaseOrders.actions.cancel')"
      icon="pi pi-ban"
      severity="danger"
      :loading="busy"
      size="small"
      outlined
      @click="confirmCancel"
    />
  </div>
</template>
