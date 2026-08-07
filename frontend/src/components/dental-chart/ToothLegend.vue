<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import ToothSurface from './ToothSurface.vue'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import { DASHARRAY_BY_STATUS, OPACITY_BY_STATUS, resolveIconGlyph } from '@/lib/dentalIcons'
import { DENTAL_CHART_ENTRY_STATUSES, DENTAL_CONDITION_CATEGORIES } from '@/types/dentalChart'

/**
 * Persistent condition-color/icon + status-tone key (design draft §16, rendering design doc §4) —
 * always visible alongside the chart, not a separate help dialog. Reads the `dentalConditions`
 * catalog store directly (the one explicitly-approved exception to "no store access" among the
 * dental-chart rendering components) so it can never drift out of sync with the real catalog.
 *
 * Each swatch is a single non-interactive `ToothSurface` "whole" region — the exact same path/
 * fill/glyph rendering a whole-tooth entry uses on the chart itself, so the legend is always a
 * true visual match for what a condition actually looks like on a tooth, never a hand-approximated
 * copy that could drift from `ToothSvg.vue`'s own rendering rules.
 */
const WHOLE_PATH = 'M0,0 L100,0 L100,100 L0,100 Z'
const GLYPH_CENTER = { x: 50, y: 50 }

/** Neutral reference color for the status-tone swatches — the tone modifier is condition-color-agnostic. */
const NEUTRAL_SWATCH_COLOR = 'var(--p-surface-500)'

const { t } = useI18n()
const store = useDentalConditionsStore()
const loadError = ref(false)

const groupedConditions = computed(() =>
  DENTAL_CONDITION_CATEGORIES.map((category) => ({
    category,
    conditions: store.items
      .filter((c) => c.is_active && c.category === category)
      .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)),
  })).filter((group) => group.conditions.length > 0),
)

onMounted(() => store.fetchAll())

// `fetchAll()` now catches and stores the error itself (docs/PROJECT_STATUS.md Phase 1 audit —
// standardizing on the store-owns-error-state pattern) rather than rejecting, so this watches the
// store's own error state instead of a local try/catch.
watch(
  () => store.error,
  (error) => {
    loadError.value = error !== null
  },
)
</script>

<template>
  <div class="flex flex-col gap-4 rounded-border border border-surface-200 p-4 dark:border-surface-700">
    <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
      {{ t('dentalChart.legend.title') }}
    </h3>

    <p v-if="loadError" class="text-sm text-red-500">{{ t('dentalChart.conditions.loadError') }}</p>
    <p v-else-if="groupedConditions.length === 0" class="text-sm text-surface-500 dark:text-surface-400">
      {{ t('dentalChart.legend.empty') }}
    </p>

    <div v-for="group in groupedConditions" :key="group.category" class="flex flex-col gap-2">
      <h4 class="text-xs font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
        {{ t(`dentalChart.category.${group.category}`) }}
      </h4>
      <div class="flex flex-wrap gap-x-4 gap-y-2">
        <div v-for="condition in group.conditions" :key="condition.id" class="flex items-center gap-2">
          <svg width="20" height="20" viewBox="0 0 100 100" class="shrink-0">
            <ToothSurface
              :path="WHOLE_PATH"
              :fill="condition.default_color"
              :glyph="resolveIconGlyph(condition.icon_key)"
              :glyph-center="GLYPH_CENTER"
              :label="condition.name"
              :interactive="false"
            />
          </svg>
          <span class="text-sm text-surface-700 dark:text-surface-200">{{ condition.name }}</span>
        </div>
      </div>
    </div>

    <div class="flex flex-col gap-2 border-t border-surface-200 pt-3 dark:border-surface-700">
      <h4 class="text-xs font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
        {{ t('dentalChart.legend.statusTone') }}
      </h4>
      <div class="flex flex-wrap gap-x-4 gap-y-2">
        <div v-for="status in DENTAL_CHART_ENTRY_STATUSES" :key="status" class="flex items-center gap-2">
          <svg width="20" height="20" viewBox="0 0 100 100" class="shrink-0">
            <ToothSurface
              :path="WHOLE_PATH"
              :fill="NEUTRAL_SWATCH_COLOR"
              :fill-opacity="OPACITY_BY_STATUS[status]"
              :stroke-dasharray="DASHARRAY_BY_STATUS[status]"
              :label="t(`dentalChart.status.${status}`)"
              :interactive="false"
            />
          </svg>
          <span class="text-sm text-surface-700 dark:text-surface-200">{{
            t(`dentalChart.status.${status}`)
          }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
