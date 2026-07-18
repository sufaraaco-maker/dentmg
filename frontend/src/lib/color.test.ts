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

  it('picks whichever of black/white actually scores the higher WCAG contrast ratio, not a flat 0.5 luminance split', () => {
    // #3b82f6 (a plausible clinic-picked appointment color) has luminance ~0.235 — inside the
    // ~[0.179, 0.5) band where black-on-bg (~5.7:1) beats white-on-bg (~3.7:1) even though a
    // naive ">0.5 is dark" split would call this background "dark" and wrongly return white.
    expect(getContrastTextColor('#3b82f6')).toBe('#000000')
  })
})
