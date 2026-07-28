<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import DatePicker from 'primevue/datepicker'
import Button from 'primevue/button'
import { parseLocalDate, toLocalDateString } from '@/lib/date'

/**
 * Shared date-range filter used by every date-ranged report view (design doc §7) — a `date_from`/
 * `date_to` pair via `frontend/src/lib/date.ts` (never raw `Date`/ISO handling, per the project's
 * Datetime Policy) plus an `#extra-filters` slot for report-specific fields (dentist, payment
 * method).
 */
const dateFrom = defineModel<string>('dateFrom', { required: true })
const dateTo = defineModel<string>('dateTo', { required: true })

const props = defineProps<{ loading?: boolean }>()
const emit = defineEmits<{ apply: [] }>()

const { t } = useI18n()

const dateFromValue = computed({
  get: () => parseLocalDate(dateFrom.value),
  set: (value: Date) => (dateFrom.value = toLocalDateString(value)),
})
const dateToValue = computed({
  get: () => parseLocalDate(dateTo.value),
  set: (value: Date) => (dateTo.value = toLocalDateString(value)),
})
</script>

<template>
  <div class="flex flex-wrap items-end gap-3">
    <div class="flex flex-col gap-1">
      <label for="report-date-from" class="text-sm text-surface-600 dark:text-surface-400">
        {{ t('reports.filters.dateFrom') }}
      </label>
      <DatePicker
        id="report-date-from"
        v-model="dateFromValue"
        date-format="yy-mm-dd"
        :max-date="dateToValue"
        show-icon
        class="w-44"
      />
    </div>
    <div class="flex flex-col gap-1">
      <label for="report-date-to" class="text-sm text-surface-600 dark:text-surface-400">
        {{ t('reports.filters.dateTo') }}
      </label>
      <DatePicker
        id="report-date-to"
        v-model="dateToValue"
        date-format="yy-mm-dd"
        :min-date="dateFromValue"
        show-icon
        class="w-44"
      />
    </div>

    <slot name="extra-filters" />

    <Button
      :label="t('reports.filters.apply')"
      icon="pi pi-filter"
      :loading="props.loading"
      @click="emit('apply')"
    />
  </div>
</template>
