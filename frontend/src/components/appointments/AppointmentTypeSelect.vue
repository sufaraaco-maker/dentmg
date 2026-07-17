<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Select from 'primevue/select'
import { useAppointmentTypesStore } from '@/stores/appointmentTypes'
import type { AppointmentType } from '@/types/appointment'

/**
 * Appointment type dropdown with a color swatch, sourced from `appointmentTypes.ts` (design
 * doc §3.4). Inactive types are excluded from the option list, except the currently-selected
 * one (an existing appointment may reference a since-deactivated type — it must still resolve).
 */
const props = defineProps<{
  modelValue: string | null
  disabled?: boolean
  invalid?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  'type-selected': [type: AppointmentType | undefined]
}>()

const { t } = useI18n()
const store = useAppointmentTypesStore()

onMounted(() => store.fetchAll())

const options = computed<AppointmentType[]>(() => {
  const active = store.items.filter((type) => type.is_active)

  if (props.modelValue && !active.some((type) => type.id === props.modelValue)) {
    const current = store.items.find((type) => type.id === props.modelValue)
    if (current) return [...active, current]
  }

  return active
})

function colorFor(id: string | null): string | undefined {
  return store.items.find((type) => type.id === id)?.color
}

function nameFor(id: string | null): string | undefined {
  return store.items.find((type) => type.id === id)?.name
}

function onChange(id: string | null) {
  emit('update:modelValue', id)
  emit(
    'type-selected',
    store.items.find((type) => type.id === id),
  )
}
</script>

<template>
  <Select
    :model-value="modelValue"
    :options="options"
    option-label="name"
    option-value="id"
    :loading="store.loading"
    :disabled="disabled"
    :invalid="invalid"
    :placeholder="t('appointments.dialog.selectType')"
    fluid
    @update:model-value="onChange"
  >
    <template #option="{ option }">
      <span class="flex items-center gap-2">
        <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: option.color }" />
        {{ option.name }}
      </span>
    </template>
    <template #value="{ value }">
      <span v-if="value" class="flex items-center gap-2">
        <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: colorFor(value) }" />
        {{ nameFor(value) }}
      </span>
      <span v-else class="text-surface-500 dark:text-surface-400">{{
        t('appointments.dialog.selectType')
      }}</span>
    </template>
  </Select>
</template>
