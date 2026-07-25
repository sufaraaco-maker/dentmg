<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { InvoiceStatus } from '@/types/invoice'

/** Invoice-level status badge — `Tag` + severity map, mirroring `TreatmentPlanStatusChip.vue`'s
 *  identical convention. `void` maps to `danger`, the same severity `TreatmentPlanStatusChip`
 *  gives `rejected`: both mark the document as invalidated, not merely inactive. */
const STATUS_SEVERITY: Record<InvoiceStatus, 'secondary' | 'success' | 'danger'> = {
  draft: 'secondary',
  issued: 'success',
  void: 'danger',
}

const props = withDefaults(defineProps<{ status: InvoiceStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`invoices.status.${props.status}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="STATUS_SEVERITY[status]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
