<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import Select from 'primevue/select'
import { useProvidersStore } from '@/stores/providers'

/** Dentist dropdown, sourced from the `providers.ts` workaround store (design doc §3.5/§10.2). */
defineProps<{
  modelValue: string | null
  disabled?: boolean
  invalid?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>()

const { t } = useI18n()
const providers = useProvidersStore()

onMounted(() => providers.fetchAll())
</script>

<template>
  <Select
    :model-value="modelValue"
    :options="providers.items"
    option-label="name"
    option-value="id"
    :loading="providers.loading"
    :disabled="disabled"
    :invalid="invalid"
    :placeholder="t('appointments.dialog.selectDentist')"
    fluid
    @update:model-value="emit('update:modelValue', $event)"
  />
</template>
