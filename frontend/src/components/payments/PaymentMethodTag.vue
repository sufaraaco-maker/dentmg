<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { PaymentMethod } from '@/types/payment'

/** Payment method badge — `Tag` + severity map, mirroring `InvoiceStatusChip.vue`'s convention. */
const METHOD_SEVERITY: Record<PaymentMethod, 'success' | 'info' | 'warn' | 'secondary'> = {
  cash: 'success',
  card: 'info',
  bank_transfer: 'warn',
  other: 'secondary',
}

const props = withDefaults(defineProps<{ method: PaymentMethod; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`payments.method.${props.method}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="METHOD_SEVERITY[method]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
