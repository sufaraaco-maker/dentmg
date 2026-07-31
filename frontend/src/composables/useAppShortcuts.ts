import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useCommandPaletteStore } from '@/stores/commandPalette'
import { useShortcutsHelpStore } from '@/stores/shortcutsHelp'

/** `g` then one of these within `CHORD_TIMEOUT_MS` navigates straight there (Linear-style
 *  go-to chords, design doc §5.4) — reuses the exact route names already in `router/index.ts`,
 *  so an unauthorized target simply hits the normal role guard and redirects to `/forbidden`,
 *  same as clicking a sidebar link would. */
const GO_TO_ROUTES: Record<string, string> = {
  d: 'dashboard',
  p: 'patients',
  a: 'appointments',
  r: 'reports',
  s: 'settings',
  i: 'supplies',
  l: 'lab-cases',
}

const CHORD_TIMEOUT_MS = 800

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

function isDialogOpen(): boolean {
  return document.querySelector('.p-dialog') !== null
}

/**
 * App-wide keyboard shortcuts (frontend-ux-redesign design doc §5.4) — generalizes the exact
 * typing/dialog guard already proven in `useCalendarKeyboardShortcuts.ts` into a global,
 * always-mounted listener (from `DefaultLayout.vue`, once). Additive only: the Appointments
 * board's own scoped shortcuts are untouched and keep working exactly as before, since this
 * listener defers to the same "ignore while a dialog is open" rule.
 *
 * `Ctrl+K`/`Cmd+K` is the one exception to every guard below — it must open the Command Palette
 * even while another dialog is open or a field has focus, matching Linear/Notion/Vercel.
 */
export function useAppShortcuts() {
  const router = useRouter()
  const commandPalette = useCommandPaletteStore()
  const shortcutsHelp = useShortcutsHelpStore()

  let awaitingGoTo = false
  let chordTimer: ReturnType<typeof setTimeout> | undefined

  function resetChord() {
    awaitingGoTo = false
    clearTimeout(chordTimer)
  }

  function onKeydown(event: KeyboardEvent) {
    if (event.defaultPrevented) return

    const hasModifier = event.ctrlKey || event.metaKey
    if (hasModifier && event.key.toLowerCase() === 'k') {
      event.preventDefault()
      commandPalette.toggle()
      resetChord()
      return
    }

    if (hasModifier || event.altKey) return
    if (isTypingTarget(event.target) || isDialogOpen()) return

    if (awaitingGoTo) {
      resetChord()
      const target = GO_TO_ROUTES[event.key.toLowerCase()]
      if (target) {
        event.preventDefault()
        router.push({ name: target }).catch(() => {})
      }
      return
    }

    if (event.key.toLowerCase() === 'g') {
      event.preventDefault()
      awaitingGoTo = true
      chordTimer = setTimeout(resetChord, CHORD_TIMEOUT_MS)
      return
    }

    if (event.key === '?') {
      event.preventDefault()
      shortcutsHelp.show()
    }
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
    resetChord()
  })
}

export { GO_TO_ROUTES }
