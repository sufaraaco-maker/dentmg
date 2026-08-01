import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useShortcutsHelpStore } from './shortcutsHelp'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useShortcutsHelpStore', () => {
  it('starts closed and responds to show/hide', () => {
    const store = useShortcutsHelpStore()
    expect(store.open).toBe(false)

    store.show()
    expect(store.open).toBe(true)

    store.hide()
    expect(store.open).toBe(false)
  })
})
