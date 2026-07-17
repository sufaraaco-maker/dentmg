import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DurationInput from './DurationInput.vue'

describe('DurationInput', () => {
  it('renders the bound value', () => {
    const wrapper = mount(DurationInput, { props: { modelValue: 30 } })
    const input = wrapper.find('input')
    expect(input.element.value).toContain('30')
  })

  it('emits update:modelValue when changed', async () => {
    const wrapper = mount(DurationInput, { props: { modelValue: 30 } })
    const input = wrapper.find('input')
    await input.setValue('45')
    await input.trigger('blur')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBe(45)
  })

  it('falls back to 5 when cleared', async () => {
    const wrapper = mount(DurationInput, { props: { modelValue: 30 } })
    const input = wrapper.find('input')
    await input.setValue('')
    await input.trigger('blur')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBe(5)
  })
})
