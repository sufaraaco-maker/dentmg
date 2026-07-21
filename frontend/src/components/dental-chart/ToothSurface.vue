<script setup lang="ts">
import { computed } from 'vue'
import type { DentalIconGlyph } from '@/lib/dentalIcons'

/**
 * One clickable region of a tooth's 5-surface diagram (or the whole-tooth region) — rendering
 * design doc §5. Purely presentational: takes a precomputed path/color/glyph and emits a single
 * `activate` event on click or Enter/Space keydown, mirroring the existing
 * AppointmentEventContent.vue/AppointmentCard.vue keyboard-activation convention. Knows nothing
 * about which surface code or tooth it represents, or where its own path sits geometrically beyond
 * the `glyphCenter` point ToothSvg hands it — ToothSvg supplies every finished, precomputed value
 * and attaches surface/tooth identity when re-emitting.
 */
const props = withDefaults(
  defineProps<{
    path: string
    fill: string
    fillOpacity?: number
    strokeDasharray?: string
    strokeColor?: string
    glyph?: DentalIconGlyph | null
    glyphCenter?: { x: number; y: number }
    label: string
    interactive?: boolean
    selected?: boolean
  }>(),
  {
    fillOpacity: 1,
    strokeDasharray: undefined,
    strokeColor: undefined,
    glyph: null,
    glyphCenter: () => ({ x: 50, y: 50 }),
    interactive: true,
    selected: false,
  },
)

const emit = defineEmits<{ activate: [] }>()

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    emit('activate')
  }
}

// Only 'x', 'lines', 'dot' render an overlay mark; 'filled' is the plain region fill and
// 'outline' is conveyed by ToothSvg lowering fillOpacity/raising stroke-width on the path itself
// (rendering design doc §4) — no separate mark needed for either.
const glyphSize = 10
const xPoints = computed(() => {
  const { x, y } = props.glyphCenter
  const s = glyphSize
  return { x1a: x - s, y1a: y - s, x2a: x + s, y2a: y + s, x1b: x - s, y1b: y + s, x2b: x + s, y2b: y - s }
})
const linesPoints = computed(() => {
  const { x, y } = props.glyphCenter
  const s = glyphSize
  return [
    { x1: x - s, y1: y - s * 0.3, x2: x + s, y2: y + s * 0.3 },
    { x1: x - s, y1: y + s * 0.6, x2: x + s, y2: y + s * 1.2 },
  ]
})
</script>

<template>
  <g
    :tabindex="interactive ? 0 : undefined"
    :role="interactive ? 'button' : undefined"
    :aria-label="interactive ? label : undefined"
    :class="[
      'transition-opacity',
      interactive ? 'cursor-pointer outline-none focus-visible:opacity-80' : '',
    ]"
    @click="interactive && emit('activate')"
    @keydown="interactive && onKeydown($event)"
  >
    <path
      :d="path"
      :fill="fill"
      :fill-opacity="fillOpacity"
      :stroke="strokeColor ?? 'var(--p-surface-400)'"
      :stroke-dasharray="strokeDasharray"
      stroke-width="1.5"
      vector-effect="non-scaling-stroke"
    />
    <path
      v-if="selected"
      :d="path"
      fill="none"
      stroke="var(--p-primary-500)"
      stroke-width="3"
      vector-effect="non-scaling-stroke"
    />

    <g v-if="glyph === 'x'" data-glyph="x" stroke="var(--p-surface-600)" stroke-width="3" stroke-linecap="round">
      <line :x1="xPoints.x1a" :y1="xPoints.y1a" :x2="xPoints.x2a" :y2="xPoints.y2a" />
      <line :x1="xPoints.x1b" :y1="xPoints.y1b" :x2="xPoints.x2b" :y2="xPoints.y2b" />
    </g>

    <g v-else-if="glyph === 'lines'" data-glyph="lines" stroke="var(--p-surface-600)" stroke-width="2.5" stroke-linecap="round">
      <line v-for="(l, i) in linesPoints" :key="i" :x1="l.x1" :y1="l.y1" :x2="l.x2" :y2="l.y2" />
    </g>

    <circle
      v-else-if="glyph === 'dot'"
      data-glyph="dot"
      :cx="glyphCenter.x"
      :cy="glyphCenter.y"
      r="5"
      fill="var(--p-surface-0)"
      stroke="var(--p-surface-600)"
      stroke-width="1.5"
    />
  </g>
</template>
