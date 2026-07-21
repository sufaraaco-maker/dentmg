<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import Select from 'primevue/select'
import { isPermanentTooth, toothDisplayName, TOOTH_CODES } from '@/lib/teeth'
import { DENTAL_CHART_ENTRY_STATUSES, DENTAL_CONDITION_CATEGORIES } from '@/types/dentalChart'
import type { DentalChartEntryStatus, DentalConditionCategory } from '@/types/dentalChart'

export type DentalChartViewMode = 'chart' | 'list'
export type DentitionFilter = 'all' | 'permanent' | 'primary'

/**
 * Dumb, prop-driven toolbar — mirrors `CalendarToolbar.vue`'s role in the Appointments module
 * exactly: owns no state of its own, every control is a controlled prop + `update:*` emit. The
 * status/category/tooth filters exist here for `ChartEntryListTable.vue` (Step 9) to consume later
 * — wiring them up now means Step 9 plugs straight into an already-complete toolbar instead of
 * reopening this component.
 */
const props = defineProps<{
  viewMode: DentalChartViewMode
  dentitionFilter: DentitionFilter
  statusFilter: DentalChartEntryStatus | null
  categoryFilter: DentalConditionCategory | null
  toothFilter: string | null
}>()

const emit = defineEmits<{
  'update:viewMode': [DentalChartViewMode]
  'update:dentitionFilter': [DentitionFilter]
  'update:statusFilter': [DentalChartEntryStatus | null]
  'update:categoryFilter': [DentalConditionCategory | null]
  'update:toothFilter': [string | null]
  'add-entry': []
}>()

const { t } = useI18n()

const viewOptions = computed(() => [
  { label: t('dentalChart.toolbar.viewChart'), value: 'chart' as DentalChartViewMode },
  { label: t('dentalChart.toolbar.viewList'), value: 'list' as DentalChartViewMode },
])

const dentitionOptions = computed(() => [
  { label: t('dentalChart.toolbar.dentitionAll'), value: 'all' as DentitionFilter },
  { label: t('dentalChart.toolbar.dentitionPermanent'), value: 'permanent' as DentitionFilter },
  { label: t('dentalChart.toolbar.dentitionPrimary'), value: 'primary' as DentitionFilter },
])

const statusOptions = computed(() => [
  { label: t('dentalChart.toolbar.allStatuses'), value: null },
  ...DENTAL_CHART_ENTRY_STATUSES.map((status) => ({ label: t(`dentalChart.status.${status}`), value: status })),
])

const categoryOptions = computed(() => [
  { label: t('dentalChart.toolbar.allCategories'), value: null },
  ...DENTAL_CONDITION_CATEGORIES.map((category) => ({ label: t(`dentalChart.category.${category}`), value: category })),
])

// Scoped to the active dentition filter so the tooth dropdown only ever lists relevant codes.
const toothOptions = computed(() => {
  const codes = TOOTH_CODES.filter((code) => {
    if (props.dentitionFilter === 'permanent') return isPermanentTooth(code)
    if (props.dentitionFilter === 'primary') return !isPermanentTooth(code)
    return true
  })
  return [
    { label: t('dentalChart.toolbar.allTeeth'), value: null },
    ...codes.map((code) => ({ label: `${code} — ${toothDisplayName(code)}`, value: code })),
  ]
})
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div class="flex flex-wrap items-center gap-3">
      <SelectButton
        :model-value="viewMode"
        :options="viewOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        :aria-label="t('dentalChart.toolbar.viewToggle')"
        @update:model-value="emit('update:viewMode', $event)"
      />
      <SelectButton
        :model-value="dentitionFilter"
        :options="dentitionOptions"
        option-label="label"
        option-value="value"
        :allow-empty="false"
        :aria-label="t('dentalChart.toolbar.dentitionFilter')"
        @update:model-value="emit('update:dentitionFilter', $event)"
      />
    </div>

    <div class="flex flex-wrap items-center gap-3">
      <Select
        :model-value="statusFilter"
        :options="statusOptions"
        option-label="label"
        option-value="value"
        :aria-label="t('dentalChart.toolbar.statusFilter')"
        class="w-40"
        @update:model-value="emit('update:statusFilter', $event)"
      />
      <Select
        :model-value="categoryFilter"
        :options="categoryOptions"
        option-label="label"
        option-value="value"
        :aria-label="t('dentalChart.toolbar.categoryFilter')"
        class="w-40"
        @update:model-value="emit('update:categoryFilter', $event)"
      />
      <Select
        :model-value="toothFilter"
        :options="toothOptions"
        option-label="label"
        option-value="value"
        filter
        :aria-label="t('dentalChart.toolbar.toothFilter')"
        class="w-56"
        @update:model-value="emit('update:toothFilter', $event)"
      />
      <Button :label="t('dentalChart.toolbar.addEntry')" icon="pi pi-plus" @click="emit('add-entry')" />
    </div>
  </div>
</template>
