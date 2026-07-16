import { describe, expect, it } from 'vitest'
import { getContrastTextColor } from './color'

describe('getContrastTextColor', () => {
  it('returns black text for a light background', () => {
    expect(getContrastTextColor('#ffffff')).toBe('#000000')
    expect(getContrastTextColor('#fef3c7')).toBe('#000000')
  })

  it('returns white text for a dark background', () => {
    expect(getContrastTextColor('#000000')).toBe('#ffffff')
    expect(getContrastTextColor('#1e293b')).toBe('#ffffff')
  })

  it('supports 3-digit shorthand hex', () => {
    expect(getContrastTextColor('#fff')).toBe('#000000')
    expect(getContrastTextColor('#000')).toBe('#ffffff')
  })
})
