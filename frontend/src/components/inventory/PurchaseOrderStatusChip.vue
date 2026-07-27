<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { PurchaseOrderStatus } from '@/types/inventory'

/** Purchase Order status badge — `Tag` + severity map, mirroring `InvoiceStatusChip.vue`'s
 *  identical convention (design doc §5/§11). */
const STATUS_SEVERITY: Record<PurchaseOrderStatus, 'secondary' | 'info' | 'warn' | 'success' | 'danger'> = {
  draft: 'secondary',
  placed: 'info',
  partially_received: 'warn',
  received: 'success',
  cancelled: 'danger',
}

const props = withDefaults(defineProps<{ status: PurchaseOrderStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`inventory.purchaseOrders.status.${props.status}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="STATUS_SEVERITY[status]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
