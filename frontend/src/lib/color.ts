/**
 * Relative-luminance check (WCAG-style) so clinic-entered appointment-type colors (any hex an
 * admin picks via ColorPicker) always render legible text on top, instead of assuming white text
 * always works. See docs/modules/appointments-ui-design.md §14 (Color Contrast).
 */
export function getContrastTextColor(hexColor: string): '#000000' | '#ffffff' {
  const hex = hexColor.replace('#', '')
  const normalized =
    hex.length === 3
      ? hex
          .split('')
          .map((char) => char + char)
          .join('')
      : hex

  const r = parseInt(normalized.slice(0, 2), 16) / 255
  const g = parseInt(normalized.slice(2, 4), 16) / 255
  const b = parseInt(normalized.slice(4, 6), 16) / 255

  const linearize = (channel: number) =>
    channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4

  const luminance = 0.2126 * linearize(r) + 0.7152 * linearize(g) + 0.0722 * linearize(b)

  return luminance > 0.5 ? '#000000' : '#ffffff'
}
