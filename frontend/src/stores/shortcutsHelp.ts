import { defineStore } from 'pinia'
import { ref } from 'vue'

/** Visibility flag for the global Keyboard Shortcuts help overlay (design doc §5.4/§8) — same
 *  shared-store shape as `commandPalette.ts`, since the `?` shortcut, the Command Palette's own
 *  "Keyboard Shortcuts" entry, and `AppKeyboardShortcutsHelp.vue` all need to read/write it. */
export const useShortcutsHelpStore = defineStore('shortcutsHelp', () => {
  const open = ref(false)

  function show() {
    open.value = true
  }

  function hide() {
    open.value = false
  }

  return { open, show, hide }
})
