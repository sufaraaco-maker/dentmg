<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { LabCaseStatus } from '@/types/laboratory'

/** Lab Case status badge — `Tag` + severity map, mirroring `PurchaseOrderStatusChip.vue`'s
 *  identical convention (design doc §4/§6). */
const STATUS_SEVERITY: Record<LabCaseStatus, 'secondary' | 'info' | 'warn' | 'success' | 'danger'> = {
  draft: 'secondary',
  sent: 'info',
  received: 'warn',
  quality_checked: 'success',
  cancelled: 'danger',
}

const props = withDefaults(defineProps<{ status: LabCaseStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`laboratory.labCases.status.${props.status}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="STATUS_SEVERITY[status]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
