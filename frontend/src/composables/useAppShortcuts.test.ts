import { mount, flushPromises, type VueWrapper } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { useAppShortcuts } from './useAppShortcuts'
import { useCommandPaletteStore } from '@/stores/commandPalette'
import { useShortcutsHelpStore } from '@/stores/shortcutsHelp'

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/patients', name: 'patients', component: { template: '<div />' } },
      { path: '/reports', name: 'reports', component: { template: '<div />' } },
    ],
  })
}

// Each `useAppShortcuts()` instance registers its own `window`-level `keydown` listener
// (onMounted). Vue Test Utils never auto-unmounts between `it()` blocks, so without this an
// earlier test's stale listener would still be attached, see the dispatched event first, call
// `event.preventDefault()` for the exact same matching keys this file exercises (Ctrl+K, "?", "g"
// chords), and starve every later test's own listener via the composable's own
// `if (event.defaultPrevented) return` guard — the calendar-shortcuts test this composable
// generalizes never hit this because its later tests only exercise *guarded* (no-op) paths.
let activeWrapper: VueWrapper | undefined

afterEach(() => {
  activeWrapper?.unmount()
  activeWrapper = undefined
})

async function mountWithShortcuts() {
  const router = makeRouter()
  await router.push('/')
  await router.isReady()
  const wrapper = mount(
    defineComponent({
      setup() {
        useAppShortcuts()
        return () => null
      },
    }),
    { global: { plugins: [router] } },
  )
  activeWrapper = wrapper
  return { wrapper, router }
}

function fireKey(key: string, options: Partial<KeyboardEventInit> = {}, target: EventTarget = window) {
  target.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...options }))
}

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useAppShortcuts', () => {
  it('toggles the command palette on Ctrl+K', async () => {
    await mountWithShortcuts()
    const commandPalette = useCommandPaletteStore()
    expect(commandPalette.open).toBe(false)

    fireKey('k', { ctrlKey: true })
    expect(commandPalette.open).toBe(true)

    fireKey('k', { ctrlKey: true })
    expect(commandPalette.open).toBe(false)
  })

  it('toggles the command palette on Cmd+K (metaKey, for macOS)', async () => {
    await mountWithShortcuts()
    const commandPalette = useCommandPaletteStore()

    fireKey('k', { metaKey: true })
    expect(commandPalette.open).toBe(true)
  })

  it('opens the shortcuts help dialog on "?"', async () => {
    await mountWithShortcuts()
    const shortcutsHelp = useShortcutsHelpStore()

    fireKey('?')
    expect(shortcutsHelp.open).toBe(true)
  })

  it('navigates via a "g" then "p" chord', async () => {
    const { router } = await mountWithShortcuts()

    fireKey('g')
    fireKey('p')
    // `router.push()` inside the composable's keydown handler is fire-and-forget (not awaited by
    // the handler itself) — `router.isReady()` resolves as soon as the *initial* navigation from
    // `mountWithShortcuts()` settles, which by this point already happened, so it would resolve
    // immediately without waiting for this second, later `push()`. Flushing pending promises is
    // what actually waits for it.
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('patients')
  })

  it('does nothing for an unmapped chord target', async () => {
    const { router } = await mountWithShortcuts()

    fireKey('g')
    fireKey('z')

    expect(router.currentRoute.value.name).toBe('dashboard')
  })

  it('never fires "?" or chords while an input has focus', async () => {
    await mountWithShortcuts()
    const shortcutsHelp = useShortcutsHelpStore()
    const input = document.createElement('input')
    document.body.appendChild(input)

    fireKey('?', {}, input)

    expect(shortcutsHelp.open).toBe(false)
    input.remove()
  })

  it('still toggles the command palette even while an input has focus', async () => {
    await mountWithShortcuts()
    const commandPalette = useCommandPaletteStore()
    const input = document.createElement('input')
    document.body.appendChild(input)

    fireKey('k', { ctrlKey: true }, input)

    expect(commandPalette.open).toBe(true)
    input.remove()
  })

  it('removes its listener on unmount', async () => {
    const { wrapper } = await mountWithShortcuts()
    const shortcutsHelp = useShortcutsHelpStore()

    wrapper.unmount()
    fireKey('?')

    expect(shortcutsHelp.open).toBe(false)
  })
})
