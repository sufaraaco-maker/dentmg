import { defineStore } from 'pinia'
import { ref } from 'vue'

/**
 * A single shared `open` flag (frontend-ux-redesign design doc §5.3) — the Header's search button,
 * `useAppShortcuts`' `Ctrl+K` handler, and `CommandPalette.vue` itself all read/write the same
 * state via this store rather than prop-drilling through `DefaultLayout.vue`.
 */
export const useCommandPaletteStore = defineStore('commandPalette', () => {
  const open = ref(false)

  function show() {
    open.value = true
  }

  function hide() {
    open.value = false
  }

  function toggle() {
    open.value = !open.value
  }

  return { open, show, hide, toggle }
})
