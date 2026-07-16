<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Tag from 'primevue/tag'
import { getContrastTextColor } from '@/lib/color'
import { APPOINTMENT_STATUS_COLORS, type AppointmentStatus } from '@/types/appointment'

const props = withDefaults(defineProps<{ status: AppointmentStatus; size?: 'small' | 'normal' }>(), {
  size: 'normal',
})

const { t } = useI18n()

const color = computed(() => APPOINTMENT_STATUS_COLORS[props.status])
const label = computed(() => t(`appointments.status.${props.status}`))

const style = computed(() => ({
  backgroundColor: color.value,
  color: getContrastTextColor(color.value),
}))
</script>

<template>
  <Tag
    :value="label"
    :style="style"
    :class="size === 'small' ? 'text-xs px-2 py-0.5' : undefined"
    :aria-label="label"
  />
</template>
