<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import ToothSvg from './ToothSvg.vue'
import ToothLegend from './ToothLegend.vue'
import DentalChartToolbar from './DentalChartToolbar.vue'
import ChartEntryListTable from './ChartEntryListTable.vue'
import type { DentalChartViewMode, DentitionFilter } from './DentalChartToolbar.vue'
import { isPermanentTooth, quadrantCodes } from '@/lib/teeth'
import type { DentalChartEntry, DentalChartEntryStatus, DentalConditionCategory, ToothSurface as ToothSurfaceCode } from '@/types/dentalChart'

/**
 * Layout/orchestration only (implementation plan §2.1) — arranges the permanent/primary arches,
 * owns the selected-tooth/surface state and the toolbar's filter state, and renders `ToothSvg.vue`
 * per tooth plus `ToothLegend.vue`/`DentalChartToolbar.vue`/`ChartEntryListTable.vue`. No SVG
 * geometry and no entry-status color logic of its own — both stay delegated to
 * `ToothSvg.vue`/`ToothSurface.vue` untouched.
 *
 * `viewMode`/filter state lives here rather than in a page (unlike Appointments' Board/List, whose
 * page owns `viewMode` — see `AppointmentsView.vue`) because there is no other host for it: this
 * component *is* the "Dental Chart" tab's body. The status/category/tooth/dentition filters apply
 * only to the list view (`filteredEntries`) — the chart view already scopes itself to one tooth at
 * a time visually, so filtering it the same way would just hide teeth rather than clarify anything.
 */
const props = withDefaults(
  defineProps<{
    entries: DentalChartEntry[]
    loading?: boolean
    /** Passed straight through to `DentalChartToolbar` (hides "Add Entry") and disables
     *  click-to-create on every tooth — receptionists get read access, not charting rights. */
    canWrite?: boolean
  }>(),
  { canWrite: true },
)

const emit = defineEmits<{
  'surface-click': [payload: { tooth: string; surface: ToothSurfaceCode }]
  'tooth-click': [payload: { tooth: string }]
  'edit-entry': [entry: DentalChartEntry]
  'add-entry': []
}>()

const { t } = useI18n()

const viewMode = ref<DentalChartViewMode>('chart')
const dentitionFilter = ref<DentitionFilter>('permanent')
const statusFilter = ref<DentalChartEntryStatus | null>(null)
const categoryFilter = ref<DentalConditionCategory | null>(null)
const toothFilter = ref<string | null>(null)

const selectedTooth = ref<string | null>(null)
const selectedSurface = ref<ToothSurfaceCode | 'whole' | null>(null)

const entriesByTooth = computed(() => {
  const map = new Map<string, DentalChartEntry[]>()
  for (const entry of props.entries) {
    const list = map.get(entry.tooth_number)
    if (list) {
      list.push(entry)
    } else {
      map.set(entry.tooth_number, [entry])
    }
  }
  return map
})

function entriesFor(code: string): DentalChartEntry[] {
  return entriesByTooth.value.get(code) ?? []
}

function selectedSurfaceFor(code: string): ToothSurfaceCode | 'whole' | null {
  return selectedTooth.value === code ? selectedSurface.value : null
}

function onSurfaceClick(payload: { tooth: string; surface: ToothSurfaceCode }) {
  selectedTooth.value = payload.tooth
  selectedSurface.value = payload.surface
  emit('surface-click', payload)
}

function onToothClick(payload: { tooth: string }) {
  selectedTooth.value = payload.tooth
  selectedSurface.value = 'whole'
  emit('tooth-click', payload)
}

// Position ascending within each quadrant, arranged left-to-right per the standard "patient's
// right on the viewer's left" layout (design draft §17) — reversed halves read high-to-low toward
// the midline exactly like every reference chart in §0's competitive research.
type ArchHalves = [left: string[], right: string[]]

const permanentRows: ArchHalves[] = [
  [[...quadrantCodes(1)].reverse(), quadrantCodes(2)],
  [[...quadrantCodes(4)].reverse(), quadrantCodes(3)],
]
const primaryRows: ArchHalves[] = [
  [[...quadrantCodes(5)].reverse(), quadrantCodes(6)],
  [[...quadrantCodes(8)].reverse(), quadrantCodes(7)],
]

const showPermanent = computed(() => dentitionFilter.value !== 'primary')
const showPrimary = computed(() => dentitionFilter.value !== 'permanent')

const filteredEntries = computed(() =>
  props.entries.filter((entry) => {
    if (statusFilter.value && entry.status !== statusFilter.value) return false
    if (categoryFilter.value && entry.dental_condition?.category !== categoryFilter.value) return false
    if (toothFilter.value && entry.tooth_number !== toothFilter.value) return false
    if (dentitionFilter.value === 'permanent' && !isPermanentTooth(entry.tooth_number)) return false
    if (dentitionFilter.value === 'primary' && isPermanentTooth(entry.tooth_number)) return false
    return true
  }),
)

const TOOTH_SIZE = 56
</script>

<template>
  <div class="flex flex-col gap-4">
    <DentalChartToolbar
      v-model:view-mode="viewMode"
      v-model:dentition-filter="dentitionFilter"
      v-model:status-filter="statusFilter"
      v-model:category-filter="categoryFilter"
      v-model:tooth-filter="toothFilter"
      :can-write="canWrite"
      @add-entry="emit('add-entry')"
    />

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start">
      <ToothLegend class="lg:w-72 lg:shrink-0" />

      <div v-if="viewMode === 'chart'" dir="ltr" class="min-w-0 flex-1 overflow-x-auto">
        <div class="flex w-max flex-col gap-8 p-2">
          <section v-if="showPermanent" class="flex flex-col gap-2">
            <h3 class="text-sm font-medium text-surface-500 dark:text-surface-400">
              {{ t('dentalChart.toolbar.dentitionPermanent') }}
            </h3>
            <div v-for="(row, i) in permanentRows" :key="i" class="flex items-center gap-4">
              <div v-for="(half, j) in row" :key="j" class="flex gap-1">
                <div v-for="code in half" :key="code" class="flex flex-col items-center gap-1">
                  <ToothSvg
                    :tooth="code"
                    :entries="entriesFor(code)"
                    :size="TOOTH_SIZE"
                    :interactive="canWrite"
                    :selected-surface="selectedSurfaceFor(code)"
                    @surface-click="onSurfaceClick"
                    @tooth-click="onToothClick"
                  />
                  <span class="text-xs text-surface-500 dark:text-surface-400">{{ code }}</span>
                </div>
              </div>
            </div>
          </section>

          <section v-if="showPrimary" class="flex flex-col gap-2">
            <h3 class="text-sm font-medium text-surface-500 dark:text-surface-400">
              {{ t('dentalChart.toolbar.dentitionPrimary') }}
            </h3>
            <div v-for="(row, i) in primaryRows" :key="i" class="flex items-center gap-4">
              <div v-for="(half, j) in row" :key="j" class="flex gap-1">
                <div v-for="code in half" :key="code" class="flex flex-col items-center gap-1">
                  <ToothSvg
                    :tooth="code"
                    :entries="entriesFor(code)"
                    :size="TOOTH_SIZE"
                    :interactive="canWrite"
                    :selected-surface="selectedSurfaceFor(code)"
                    @surface-click="onSurfaceClick"
                    @tooth-click="onToothClick"
                  />
                  <span class="text-xs text-surface-500 dark:text-surface-400">{{ code }}</span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>

      <ChartEntryListTable
        v-else
        class="min-w-0 flex-1"
        :entries="filteredEntries"
        :loading="loading ?? false"
        @edit-entry="emit('edit-entry', $event)"
      />
    </div>
  </div>
</template>
