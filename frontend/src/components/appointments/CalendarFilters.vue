<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import MultiSelect from 'primevue/multiselect'
import Button from 'primevue/button'
import { useProvidersStore } from '@/stores/providers'
import { useAppointmentTypesStore } from '@/stores/appointmentTypes'
import { APPOINTMENT_STATUSES } from '@/types/appointment'
import type { CalendarFilters } from '@/stores/calendar'

/**
 * Dentist / Status / Type filters, shared by the Board and (in the next step) the List view.
 * Patient filter is deferred until `PatientSearchSelect.vue` exists (design doc §2.7 reuses it —
 * built alongside AppointmentDialog in the next implementation step), not stubbed out here.
 */
const props = defineProps<{ modelValue: CalendarFilters }>()

const emit = defineEmits<{ (e: 'update:modelValue', value: CalendarFilters): void }>()

const { t } = useI18n()
const providers = useProvidersStore()
const appointmentTypes = useAppointmentTypesStore()

onMounted(() => {
  providers.fetchAll()
  appointmentTypes.fetchAll()
})

function update<K extends keyof CalendarFilters>(key: K, value: CalendarFilters[K]) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}

function clearFilters() {
  emit('update:modelValue', { dentistIds: [], statuses: [], typeIds: [], patientId: null })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-3">
    <MultiSelect
      :model-value="modelValue.dentistIds"
      :options="providers.items"
      option-label="name"
      option-value="id"
      :loading="providers.loading"
      :placeholder="t('appointments.filters.dentist')"
      display="chip"
      class="min-w-48"
      @update:model-value="update('dentistIds', $event)"
    />
    <MultiSelect
      :model-value="modelValue.statuses"
      :options="APPOINTMENT_STATUSES"
      :placeholder="t('appointments.filters.status')"
      display="chip"
      class="min-w-48"
      @update:model-value="update('statuses', $event)"
    >
      <template #option="{ option }">{{ t(`appointments.status.${option}`) }}</template>
      <template #chip="{ value }">{{ t(`appointments.status.${value}`) }}</template>
    </MultiSelect>
    <MultiSelect
      :model-value="modelValue.typeIds"
      :options="appointmentTypes.items"
      option-label="name"
      option-value="id"
      :loading="appointmentTypes.loading"
      :placeholder="t('appointments.filters.type')"
      display="chip"
      class="min-w-48"
      @update:model-value="update('typeIds', $event)"
    />
    <Button
      v-if="modelValue.dentistIds.length || modelValue.statuses.length || modelValue.typeIds.length"
      :label="t('appointments.filters.clearFilters')"
      text
      size="small"
      @click="clearFilters"
    />
  </div>
</template>
