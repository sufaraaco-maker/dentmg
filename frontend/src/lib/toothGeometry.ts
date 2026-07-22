import { isAnteriorTooth, isUpperArch, mesialSide } from './teeth'
import type { ToothSurface as ToothSurfaceCode } from '@/types/dentalChart'

/**
 * The 5-surface diagram's SVG geometry (rendering design doc §3) — extracted out of `ToothSvg.vue`
 * so `ChartEntryDialog.vue`'s multi-select surface picker (implementation plan §2.1) can render the
 * exact same regions without duplicating the path/orientation math. `ToothSvg.vue` itself is the
 * other consumer (single-selection, entry-colored); this module owns only the shared geometry, none
 * of either caller's rendering/selection logic.
 */
export type SurfaceRegionKey = 'center' | 'top' | 'right' | 'bottom' | 'left'

export const SURFACE_REGION_PATHS: Record<SurfaceRegionKey, string> = {
  center: 'M30,30 L70,30 L70,70 L30,70 Z',
  top: 'M0,0 L100,0 L70,30 L30,30 Z',
  right: 'M100,0 L100,100 L70,70 L70,30 Z',
  bottom: 'M100,100 L0,100 L30,70 L70,70 Z',
  left: 'M0,100 L0,0 L30,30 L30,70 Z',
}

export const WHOLE_TOOTH_PATH = 'M0,0 L100,0 L100,100 L0,100 Z'

export const SURFACE_REGION_CENTERS: Record<SurfaceRegionKey, { x: number; y: number }> = {
  center: { x: 50, y: 50 },
  top: { x: 50, y: 15 },
  right: { x: 85, y: 50 },
  bottom: { x: 50, y: 85 },
  left: { x: 15, y: 50 },
}

/** Which FDI surface code a given region resolves to for a specific tooth (never a static map — depends on arch/anterior/mesial side). */
export function surfaceCodeForRegion(tooth: string, region: SurfaceRegionKey): ToothSurfaceCode {
  const upper = isUpperArch(tooth)
  const anterior = isAnteriorTooth(tooth)
  const mesial = mesialSide(tooth)

  switch (region) {
    case 'center':
      return anterior ? 'I' : 'O'
    case 'top':
      return upper ? 'F' : 'L'
    case 'bottom':
      return upper ? 'L' : 'F'
    case 'left':
      return mesial === 'left' ? 'M' : 'D'
    case 'right':
      return mesial === 'right' ? 'M' : 'D'
  }
}

const REGION_ORDER: SurfaceRegionKey[] = ['center', 'top', 'right', 'bottom', 'left']

/** All 5 regions for a tooth, in the fixed center/top/right/bottom/left order both callers share. */
export function surfaceRegionsFor(
  tooth: string,
): { key: SurfaceRegionKey; path: string; center: { x: number; y: number }; surface: ToothSurfaceCode }[] {
  return REGION_ORDER.map((key) => ({
    key,
    path: SURFACE_REGION_PATHS[key],
    center: SURFACE_REGION_CENTERS[key],
    surface: surfaceCodeForRegion(tooth, key),
  }))
}
