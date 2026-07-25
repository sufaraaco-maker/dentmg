<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { TreatmentPlanStatus } from '@/types/treatmentPlan'

/** Plan-level status badge (design doc §5/§11) — PrimeVue `Tag` + severity map, mirroring
 *  `ChartEntryListTable.vue`'s `STATUS_SEVERITY` convention rather than `AppointmentStatusChip`'s
 *  hex-color map: simpler, and this module introduces no new color palette. */
const STATUS_SEVERITY: Record<TreatmentPlanStatus, 'secondary' | 'info' | 'success' | 'warn' | 'danger'> = {
  draft: 'secondary',
  presented: 'info',
  accepted: 'success',
  in_progress: 'warn',
  completed: 'success',
  rejected: 'danger',
  cancelled: 'secondary',
}

const props = withDefaults(defineProps<{ status: TreatmentPlanStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`treatmentPlans.status.${props.status}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="STATUS_SEVERITY[status]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
