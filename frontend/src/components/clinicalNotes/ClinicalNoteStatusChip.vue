<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import type { ClinicalNoteStatus } from '@/types/clinicalNote'

/** Note-level status badge — PrimeVue `Tag` + severity map, mirroring
 *  `TreatmentPlanStatusChip.vue`'s identical convention. Only two statuses (design doc §5), so the
 *  map is deliberately small: `draft` reads as "not final yet," `signed` as a settled, permanent record. */
const STATUS_SEVERITY: Record<ClinicalNoteStatus, 'secondary' | 'success'> = {
  draft: 'secondary',
  signed: 'success',
}

const props = withDefaults(defineProps<{ status: ClinicalNoteStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const label = computed(() => t(`clinicalNotes.status.${props.status}`))
</script>

<template>
  <Tag
    :value="label"
    :severity="STATUS_SEVERITY[status]"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
