<script setup lang="ts">
import InputNumber from 'primevue/inputnumber'

/** Bounded 5-480 minute stepper, mirroring the backend's duration_minutes validation exactly (design doc §3.9). */
defineProps<{
  modelValue: number
  invalid?: boolean
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number] }>()
</script>

<template>
  <InputNumber
    :model-value="modelValue"
    :min="5"
    :max="480"
    :step="5"
    suffix=" min"
    show-buttons
    :invalid="invalid"
    :disabled="disabled"
    fluid
    @update:model-value="(value) => emit('update:modelValue', value ?? 5)"
  />
</template>
