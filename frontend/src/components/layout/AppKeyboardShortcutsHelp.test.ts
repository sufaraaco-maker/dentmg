import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import AppKeyboardShortcutsHelp from './AppKeyboardShortcutsHelp.vue'
import { useShortcutsHelpStore } from '@/stores/shortcutsHelp'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('AppKeyboardShortcutsHelp', () => {
  it('renders every documented shortcut once the store opens it', async () => {
    const shortcutsHelp = useShortcutsHelpStore()
    shortcutsHelp.show()

    const wrapper = mount(AppKeyboardShortcutsHelp)
    await flushPromises()

    expect(wrapper.text()).toContain('Open Command Palette')
    expect(wrapper.text()).toContain('Ctrl / ⌘ + K')
    expect(wrapper.text()).toContain('Go to Dashboard')
  })
})
