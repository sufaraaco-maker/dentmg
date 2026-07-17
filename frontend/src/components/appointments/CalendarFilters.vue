<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import MultiSelect from 'primevue/multiselect'
import Button from 'primevue/button'
import PatientSearchSelect from './PatientSearchSelect.vue'
import { useProvidersStore } from '@/stores/providers'
import { useAppointmentTypesStore } from '@/stores/appointmentTypes'
import { APPOINTMENT_STATUSES } from '@/types/appointment'
import type { CalendarFilters } from '@/stores/calendar'

/**
 * Dentist / Status / Type / Patient filters, shared by the Board and the List view (§2.7).
 *
 * Deliberately not passing `:loading` to the Dentist/Type `MultiSelect`s: with `display="chip"`,
 * an empty `modelValue` and empty `options` (the state on first mount, before `fetchAll()`
 * resolves), PrimeVue 4.5.5's MultiSelect renders its placeholder from two independent branches
 * that are both true at once in exactly that combination, printing it twice (e.g. "DentistDentist")
 * for the ~1-2s the fetch is in flight. `loading` is what makes that combination reachable; the
 * Status filter (no `loading` prop, static enum options) never hits it. Removing the prop is a
 * clean, permanent fix — no per-filter loading spinner was ever required by design doc §1.7.
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
      :placeholder="t('appointments.filters.type')"
      display="chip"
      class="min-w-48"
      @update:model-value="update('typeIds', $event)"
    />
    <PatientSearchSelect
      :model-value="modelValue.patientId"
      class="min-w-48"
      @update:model-value="update('patientId', $event)"
    />
    <Button
      v-if="
        modelValue.dentistIds.length ||
        modelValue.statuses.length ||
        modelValue.typeIds.length ||
        modelValue.patientId
      "
      :label="t('appointments.filters.clearFilters')"
      text
      size="small"
      @click="clearFilters"
    />
  </div>
</template>
