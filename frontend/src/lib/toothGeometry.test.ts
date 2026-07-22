import { describe, expect, it } from 'vitest'
import { surfaceCodeForRegion, surfaceRegionsFor } from './toothGeometry'

describe('surfaceCodeForRegion', () => {
  it('resolves an upper-right posterior tooth (16): O center, F top, M right, L bottom, D left', () => {
    expect(surfaceCodeForRegion('16', 'center')).toBe('O')
    expect(surfaceCodeForRegion('16', 'top')).toBe('F')
    expect(surfaceCodeForRegion('16', 'right')).toBe('M')
    expect(surfaceCodeForRegion('16', 'bottom')).toBe('L')
    expect(surfaceCodeForRegion('16', 'left')).toBe('D')
  })

  it('uses Incisal for the center region on an anterior tooth (11)', () => {
    expect(surfaceCodeForRegion('11', 'center')).toBe('I')
  })

  it('flips mesial/distal for an upper-left tooth (24)', () => {
    expect(surfaceCodeForRegion('24', 'right')).toBe('D')
    expect(surfaceCodeForRegion('24', 'left')).toBe('M')
  })

  it('flips facial/lingual for a lower-arch tooth (46)', () => {
    expect(surfaceCodeForRegion('46', 'top')).toBe('L')
    expect(surfaceCodeForRegion('46', 'bottom')).toBe('F')
  })
})

describe('surfaceRegionsFor', () => {
  it('returns all 5 regions in a fixed center/top/right/bottom/left order', () => {
    const regions = surfaceRegionsFor('16')
    expect(regions.map((r) => r.key)).toEqual(['center', 'top', 'right', 'bottom', 'left'])
    expect(regions.map((r) => r.surface)).toEqual(['O', 'F', 'M', 'L', 'D'])
  })
})
