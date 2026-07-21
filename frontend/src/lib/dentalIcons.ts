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
