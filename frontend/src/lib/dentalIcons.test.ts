import { describe, expect, it } from 'vitest'
import { resolveIconGlyph } from './dentalIcons'

describe('resolveIconGlyph', () => {
  it('maps known icon keys to their documented glyph', () => {
    expect(resolveIconGlyph('missing')).toBe('x')
    expect(resolveIconGlyph('extraction')).toBe('lines')
    expect(resolveIconGlyph('fracture')).toBe('outline')
    expect(resolveIconGlyph('impacted')).toBe('outline')
    expect(resolveIconGlyph('root_canal')).toBe('dot')
    expect(resolveIconGlyph('sealant')).toBe('dot')
  })

  it('defaults unmapped seed keys to filled', () => {
    expect(resolveIconGlyph('caries')).toBe('filled')
    expect(resolveIconGlyph('filling')).toBe('filled')
    expect(resolveIconGlyph('crown')).toBe('filled')
    expect(resolveIconGlyph('implant')).toBe('filled')
    expect(resolveIconGlyph('bridge')).toBe('filled')
    expect(resolveIconGlyph('veneer')).toBe('filled')
  })

  it('falls back to filled for null, undefined, and unrecognized keys', () => {
    expect(resolveIconGlyph(null)).toBe('filled')
    expect(resolveIconGlyph(undefined)).toBe('filled')
    expect(resolveIconGlyph('some_future_condition')).toBe('filled')
  })
})
