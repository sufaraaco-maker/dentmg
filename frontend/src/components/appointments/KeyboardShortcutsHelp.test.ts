import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import KeyboardShortcutsHelp from './KeyboardShortcutsHelp.vue'

describe('KeyboardShortcutsHelp', () => {
  it('lists every documented shortcut (design doc §2.10) when open', async () => {
    const wrapper = mount(KeyboardShortcutsHelp, { props: { visible: true } })
    await flushPromises()

    expect(wrapper.text()).toContain('New appointment')
    expect(wrapper.text()).toContain('Previous / next period')
    expect(wrapper.text()).toContain('Jump to today')
    expect(wrapper.text()).toContain('Switch view')
    expect(wrapper.text()).toContain('Focus the patient filter')
    expect(wrapper.text()).toContain('Close the open dialog')
    expect(wrapper.text()).toContain('Show this help')
  })

  it('emits update:visible when dismissed', async () => {
    const wrapper = mount(KeyboardShortcutsHelp, { props: { visible: true } })

    await wrapper.findComponent({ name: 'Dialog' }).vm.$emit('update:visible', false)

    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })
})
