<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import AppointmentStatusChip from './AppointmentStatusChip.vue'
import type { AppointmentStatus } from '@/types/appointment'

export interface AppointmentEventData {
  id: string
  patientName: string
  dentistName?: string
  status: AppointmentStatus
  timeText: string
  hasNotes: boolean
}

defineProps<{ event: AppointmentEventData }>()

const emit = defineEmits<{ (e: 'activate'): void }>()

const { t } = useI18n()

function onKeydown(domEvent: KeyboardEvent) {
  if (domEvent.key === 'Enter' || domEvent.key === ' ') {
    domEvent.preventDefault()
    emit('activate')
  }
}
</script>

<template>
  <div
    class="flex h-full w-full flex-col gap-0.5 overflow-hidden px-1 py-0.5 text-xs"
    tabindex="0"
    role="button"
    @keydown="onKeydown"
    @click="emit('activate')"
  >
    <div class="flex items-center gap-1 truncate font-medium">
      <i v-if="event.hasNotes" class="pi pi-file-edit shrink-0" :title="t('appointments.hasNotes')" />
      <span class="truncate">{{ event.patientName }}</span>
    </div>
    <span class="truncate opacity-90">{{ event.timeText }}</span>
    <span v-if="event.dentistName" class="truncate opacity-90">{{ event.dentistName }}</span>
    <AppointmentStatusChip :status="event.status" size="small" />
  </div>
</template>
