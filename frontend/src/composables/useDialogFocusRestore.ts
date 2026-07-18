import { watch } from 'vue'

/**
 * Focus-return pattern for modal dialogs (docs/modules/appointments-ui-design.md §14): captures
 * whatever had focus right before the dialog opened, and restores it once the dialog closes,
 * whether that's Cancel, a successful save, or a backdrop click — all of which just flip the same
 * `visible` prop back to false.
 *
 * Deliberately does *not* also move focus onto a dialog's first field on open — PrimeVue's own
 * `Dialog` already does that itself on its `onAfterEnter` transition hook, which runs after this
 * composable's own open-time work, so a `.focus()` call made here would just get silently
 * overridden once PrimeVue's runs (confirmed directly). The correct way to get PrimeVue to focus a
 * specific field is a real HTML `autofocus` attribute on that field — PrimeVue's `Dialog.focus()`
 * looks for `[autofocus]` first and only falls back to its own Close button when nothing has it.
 */
export function useDialogFocusRestore(getVisible: () => boolean) {
  let previouslyFocused: HTMLElement | null = null

  watch(getVisible, (visible) => {
    if (visible) {
      previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null
    } else if (previouslyFocused) {
      previouslyFocused.focus()
      previouslyFocused = null
    }
  })
}
