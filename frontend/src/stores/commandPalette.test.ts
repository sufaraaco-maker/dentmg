import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useCommandPaletteStore } from './commandPalette'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useCommandPaletteStore', () => {
  it('starts closed', () => {
    expect(useCommandPaletteStore().open).toBe(false)
  })

  it('show/hide/toggle control the open flag', () => {
    const store = useCommandPaletteStore()

    store.show()
    expect(store.open).toBe(true)

    store.hide()
    expect(store.open).toBe(false)

    store.toggle()
    expect(store.open).toBe(true)
    store.toggle()
    expect(store.open).toBe(false)
  })
})
