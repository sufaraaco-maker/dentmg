<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import ToothSurface from './ToothSurface.vue'
import { isAnteriorTooth, isUpperArch, mesialSide, toothDisplayName } from '@/lib/teeth'
import { DASHARRAY_BY_STATUS, OPACITY_BY_STATUS, resolveIconGlyph, type DentalIconGlyph } from '@/lib/dentalIcons'
import type { DentalChartEntry, ToothSurface as ToothSurfaceCode } from '@/types/dentalChart'

/**
 * One tooth's schematic 5-surface diagram (or a single whole-tooth region), rendering design doc
 * §3/§5. Pure/presentational — computes its own arch/side orientation and display color/icon
 * purely from `tooth` + `entries`, no store access, no knowledge of the surrounding chart layout.
 * Root forced `dir="ltr"` unconditionally (design draft §17/§2.5) — Mesial/Distal placement inside
 * one tooth is anatomically fixed and must never mirror under Arabic RTL.
 */
const props = withDefaults(
  defineProps<{
    tooth: string
    entries?: DentalChartEntry[]
    size?: number
    interactive?: boolean
    selectedSurface?: ToothSurfaceCode | 'whole' | null
  }>(),
  { entries: () => [], size: 64, interactive: true, selectedSurface: null },
)

const emit = defineEmits<{
  'surface-click': [payload: { tooth: string; surface: ToothSurfaceCode }]
  'tooth-click': [payload: { tooth: string }]
}>()

const { t } = useI18n()

/** Most clinically relevant first (rendering design doc §4) — picks which one entry a shared region shows. */
const STATUS_PRECEDENCE: Record<DentalChartEntry['status'], number> = {
  active: 0,
  planned: 1,
  existing: 2,
  completed: 3,
  cancelled: 4,
}

function pickWinner(candidates: DentalChartEntry[]): DentalChartEntry | null {
  if (candidates.length === 0) return null
  return [...candidates].sort((a, b) => STATUS_PRECEDENCE[a.status] - STATUS_PRECEDENCE[b.status])[0]
}

const wholeToothEntries = computed(() => props.entries.filter((e) => !e.surfaces || e.surfaces.length === 0))
const surfaceEntries = computed(() => props.entries.filter((e) => e.surfaces && e.surfaces.length > 0))
const wholeToothWinner = computed(() => pickWinner(wholeToothEntries.value))
const wholeRegionMode = computed(() => wholeToothWinner.value !== null)

function entriesForSurface(surface: ToothSurfaceCode): DentalChartEntry[] {
  return surfaceEntries.value.filter((e) => e.surfaces?.includes(surface))
}

const upperArch = computed(() => isUpperArch(props.tooth))
const anterior = computed(() => isAnteriorTooth(props.tooth))
const mesial = computed(() => mesialSide(props.tooth))

const centerSurface = computed<ToothSurfaceCode>(() => (anterior.value ? 'I' : 'O'))
const topSurface = computed<ToothSurfaceCode>(() => (upperArch.value ? 'F' : 'L'))
const bottomSurface = computed<ToothSurfaceCode>(() => (upperArch.value ? 'L' : 'F'))
const leftSurface = computed<ToothSurfaceCode>(() => (mesial.value === 'left' ? 'M' : 'D'))
const rightSurface = computed<ToothSurfaceCode>(() => (mesial.value === 'right' ? 'M' : 'D'))

// Rendering design doc §3 — outer square + inner square, 4 trapezoids + center.
const PATHS: Record<'whole' | 'center' | 'top' | 'right' | 'bottom' | 'left', string> = {
  whole: 'M0,0 L100,0 L100,100 L0,100 Z',
  center: 'M30,30 L70,30 L70,70 L30,70 Z',
  top: 'M0,0 L100,0 L70,30 L30,30 Z',
  right: 'M100,0 L100,100 L70,70 L70,30 Z',
  bottom: 'M100,100 L0,100 L30,70 L70,70 Z',
  left: 'M0,100 L0,0 L30,30 L30,70 Z',
}

const CENTERS: Record<keyof typeof PATHS, { x: number; y: number }> = {
  whole: { x: 50, y: 50 },
  center: { x: 50, y: 50 },
  top: { x: 50, y: 15 },
  right: { x: 85, y: 50 },
  bottom: { x: 50, y: 85 },
  left: { x: 15, y: 50 },
}

function statusLabel(status: DentalChartEntry['status']) {
  return t(`dentalChart.status.${status}`)
}

function surfaceLabel(surface: ToothSurfaceCode) {
  return t(`dentalChart.chart.surface.${surface}`)
}

function entryDescription(entry: DentalChartEntry, includeSurfaces: boolean): string {
  const base = t('dentalChart.chart.entryLine', {
    status: statusLabel(entry.status),
    condition: entry.dental_condition?.name ?? '',
  })
  if (includeSurfaces && entry.surfaces && entry.surfaces.length > 0) {
    const list = entry.surfaces.map(surfaceLabel).join(', ')
    return `${base} ${t('dentalChart.chart.onSurfaces', { surfaces: list })}`
  }
  return base
}

const toothName = computed(() => toothDisplayName(props.tooth))

const groupDetail = computed(() => {
  if (props.entries.length === 0) return t('dentalChart.chart.noEntries')
  const details = props.entries.map((e) => entryDescription(e, true)).join('; ')
  return t('dentalChart.chart.entriesSummary', { count: props.entries.length, details })
})

const rootLabel = computed(() =>
  t('dentalChart.chart.toothAriaLabel', { code: props.tooth, name: toothName.value, detail: groupDetail.value }),
)

interface RegionVisual {
  fill: string
  fillOpacity: number
  strokeDasharray?: string
  strokeColor?: string
  glyph: DentalIconGlyph | null
}

const EMPTY_FILL = 'var(--p-surface-100)'

function visualFor(entry: DentalChartEntry | null): RegionVisual {
  if (!entry) {
    return { fill: EMPTY_FILL, fillOpacity: 1, glyph: null }
  }

  const color = entry.dental_condition?.default_color ?? 'var(--p-surface-400)'
  const glyph = resolveIconGlyph(entry.dental_condition?.icon_key ?? null)
  const dasharray = DASHARRAY_BY_STATUS[entry.status]

  if (glyph === 'outline') {
    return { fill: 'none', fillOpacity: 1, strokeColor: color, strokeDasharray: dasharray, glyph }
  }

  return {
    fill: color,
    fillOpacity: OPACITY_BY_STATUS[entry.status],
    strokeDasharray: dasharray,
    strokeColor: entry.status === 'cancelled' ? 'var(--p-surface-400)' : undefined,
    glyph,
  }
}

interface RenderedRegion {
  key: string
  path: string
  glyphCenter: { x: number; y: number }
  visual: RegionVisual
  label: string
  selectedKey: ToothSurfaceCode | 'whole'
  onActivate: () => void
}

const regions = computed<RenderedRegion[]>(() => {
  if (wholeRegionMode.value) {
    return [
      {
        key: 'whole',
        path: PATHS.whole,
        glyphCenter: CENTERS.whole,
        visual: visualFor(wholeToothWinner.value),
        label: rootLabel.value,
        selectedKey: 'whole',
        onActivate: () => emit('tooth-click', { tooth: props.tooth }),
      },
    ]
  }

  const specs: { key: 'center' | 'top' | 'right' | 'bottom' | 'left'; surface: ToothSurfaceCode }[] = [
    { key: 'center', surface: centerSurface.value },
    { key: 'top', surface: topSurface.value },
    { key: 'right', surface: rightSurface.value },
    { key: 'bottom', surface: bottomSurface.value },
    { key: 'left', surface: leftSurface.value },
  ]

  return specs.map(({ key, surface }) => {
    const winner = pickWinner(entriesForSurface(surface))
    const detail = winner ? entryDescription(winner, false) : t('dentalChart.chart.noEntries')
    return {
      key,
      path: PATHS[key],
      glyphCenter: CENTERS[key],
      visual: visualFor(winner),
      label: t('dentalChart.chart.surfaceAriaLabel', { surface: surfaceLabel(surface), code: props.tooth, detail }),
      selectedKey: surface,
      onActivate: () => emit('surface-click', { tooth: props.tooth, surface }),
    }
  })
})
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 100 100"
    dir="ltr"
    role="group"
    :aria-label="rootLabel"
    class="overflow-visible"
  >
    <ToothSurface
      v-for="region in regions"
      :key="region.key"
      :path="region.path"
      :fill="region.visual.fill"
      :fill-opacity="region.visual.fillOpacity"
      :stroke-dasharray="region.visual.strokeDasharray"
      :stroke-color="region.visual.strokeColor"
      :glyph="region.visual.glyph"
      :glyph-center="region.glyphCenter"
      :label="region.label"
      :interactive="interactive"
      :selected="selectedSurface === region.selectedKey"
      @activate="region.onActivate"
    />
  </svg>
</template>
