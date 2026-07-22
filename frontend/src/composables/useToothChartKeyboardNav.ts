import { onMounted, onUnmounted, type Ref } from 'vue'

const ARROW_KEYS = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'] as const

function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max)
}

/**
 * Arrow-key navigation between teeth on the odontogram (design draft §18) — analogous in pattern
 * to `useCalendarKeyboardShortcuts()` but scoped to one container (the chart body) rather than the
 * whole window's global shortcut list, and operating over a spatial tooth grid rather than a fixed
 * command set. Moves focus to the first focusable surface/whole-tooth region of the adjacent
 * tooth; it never fires unless focus already sits inside a `[data-tooth]` element that belongs to
 * this chart, so it can't hijack arrow keys used elsewhere (toolbar selects, filters, dialogs).
 *
 * Listens on `window` rather than the container element itself — `container.value` is read fresh
 * on every keystroke instead of being captured once at mount, so this keeps working correctly
 * across the chart/list `v-if` toggle in `ToothChart.vue`, which tears down and recreates the
 * container element each time the view switches.
 *
 * `rows` mirrors the chart's actual rendered layout: each entry is one visual row of tooth codes,
 * left-to-right exactly as displayed (already reversed/arranged by `ToothChart.vue`), so Up/Down
 * moves between arch rows and Left/Right moves within one, with no wraparound at either edge.
 */
export function useToothChartKeyboardNav(container: Ref<HTMLElement | null>, rows: Ref<string[][]>) {
  function findPosition(code: string): { row: number; col: number } | null {
    for (let row = 0; row < rows.value.length; row += 1) {
      const col = rows.value[row].indexOf(code)
      if (col !== -1) return { row, col }
    }
    return null
  }

  function focusTooth(code: string) {
    const target = container.value?.querySelector<HTMLElement>(`[data-tooth="${code}"] [tabindex]`)
    target?.focus()
  }

  function onKeydown(event: KeyboardEvent) {
    if (!(ARROW_KEYS as readonly string[]).includes(event.key)) return
    if (event.metaKey || event.ctrlKey || event.altKey || event.shiftKey) return

    const active = document.activeElement
    const toothEl = active instanceof Element ? active.closest('[data-tooth]') : null
    if (!toothEl || !container.value?.contains(toothEl)) return

    const code = toothEl.getAttribute('data-tooth')
    const position = code ? findPosition(code) : null
    if (!position) return

    event.preventDefault()

    let { row, col } = position
    if (event.key === 'ArrowLeft') col -= 1
    else if (event.key === 'ArrowRight') col += 1
    else if (event.key === 'ArrowUp') row -= 1
    else if (event.key === 'ArrowDown') row += 1

    row = clamp(row, 0, rows.value.length - 1)
    const targetRow = rows.value[row]
    if (!targetRow || targetRow.length === 0) return
    col = clamp(col, 0, targetRow.length - 1)

    focusTooth(targetRow[col])
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))
}
