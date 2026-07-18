import { onMounted, onUnmounted } from 'vue'

export interface CalendarKeyboardShortcutHandlers {
  onNewAppointment: () => void
  onPrevious: () => void
  onNext: () => void
  onToday: () => void
  onSwitchView: (slot: 1 | 2 | 3 | 4 | 5) => void
  onFocusSearch: () => void
  onShowHelp: () => void
}

function isTypingTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

function isDialogOpen(): boolean {
  return document.querySelector('.p-dialog') !== null
}

/**
 * Appointments Board keyboard shortcuts (docs/modules/appointments-ui-design.md §2.10). Never
 * fires while an input/textarea/select has focus or any PrimeVue `Dialog` is open — checked fresh
 * on every keystroke rather than a single mount-time flag, so it also covers a dialog that opens
 * *after* this composable is already listening.
 */
export function useCalendarKeyboardShortcuts(handlers: CalendarKeyboardShortcutHandlers) {
  function onKeydown(event: KeyboardEvent) {
    if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) return
    if (isTypingTarget(event.target) || isDialogOpen()) return

    switch (event.key) {
      case 'n':
      case 'N':
        event.preventDefault()
        handlers.onNewAppointment()
        break
      case 'ArrowLeft':
        event.preventDefault()
        handlers.onPrevious()
        break
      case 'ArrowRight':
        event.preventDefault()
        handlers.onNext()
        break
      case 't':
      case 'T':
        event.preventDefault()
        handlers.onToday()
        break
      case '1':
      case '2':
      case '3':
      case '4':
      case '5':
        event.preventDefault()
        handlers.onSwitchView(Number(event.key) as 1 | 2 | 3 | 4 | 5)
        break
      case '/':
        event.preventDefault()
        handlers.onFocusSearch()
        break
      case '?':
        event.preventDefault()
        handlers.onShowHelp()
        break
    }
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))
}
