import type { DentalChartEntryStatus } from '@/types/dentalChart'

/**
 * Maps a `dental_conditions.icon_key` string to one of a small, bounded set of rendering glyphs
 * (rendering design doc §4) — never one icon per condition name. A new catalog condition picks an
 * existing glyph via its `icon_key`; it never requires new frontend code.
 */
export type DentalIconGlyph = 'filled' | 'outline' | 'x' | 'lines' | 'dot'

const ICON_KEY_GLYPH_MAP: Record<string, DentalIconGlyph> = {
  missing: 'x',
  extraction: 'lines',
  fracture: 'outline',
  impacted: 'outline',
  root_canal: 'dot',
  sealant: 'dot',
}

/** Falls back to `'filled'` for `null`/unrecognized keys — forward-compatible by design. */
export function resolveIconGlyph(iconKey: string | null | undefined): DentalIconGlyph {
  if (!iconKey) return 'filled'
  return ICON_KEY_GLYPH_MAP[iconKey] ?? 'filled'
}

/**
 * Status-tone modifier applied on top of a condition's own base color (rendering design doc §4) —
 * shared between `ToothSvg.vue` (applies it per-region) and `ToothLegend.vue` (documents it in the
 * status-tone key) so the two never drift out of sync with each other.
 */
export const OPACITY_BY_STATUS: Record<DentalChartEntryStatus, number> = {
  existing: 1,
  completed: 1,
  active: 0.55,
  planned: 0.55,
  cancelled: 0.15,
}

export const DASHARRAY_BY_STATUS: Record<DentalChartEntryStatus, string | undefined> = {
  existing: undefined,
  completed: undefined,
  active: '4 2',
  planned: '4 2',
  cancelled: '1 2',
}
