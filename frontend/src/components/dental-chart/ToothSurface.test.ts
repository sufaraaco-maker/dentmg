import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ToothSurface from './ToothSurface.vue'

const BASE_PROPS = { path: 'M0,0 L100,0 L100,100 L0,100 Z', fill: '#DC2626', label: 'Mesial surface' }

describe('ToothSurface', () => {
  it('renders the path with the given fill', () => {
    const wrapper = mount(ToothSurface, { props: BASE_PROPS })
    const path = wrapper.find('path')
    expect(path.attributes('d')).toBe(BASE_PROPS.path)
    expect(path.attributes('fill')).toBe('#DC2626')
  })

  it('is keyboard-focusable and labeled when interactive', () => {
    const wrapper = mount(ToothSurface, { props: BASE_PROPS })
    const g = wrapper.find('g[role="button"]')
    expect(g.attributes('tabindex')).toBe('0')
    expect(g.attributes('aria-label')).toBe('Mesial surface')
  })

  it('is not focusable or labeled when interactive is false', () => {
    const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, interactive: false } })
    const g = wrapper.find('g')
    expect(g.attributes('role')).toBeUndefined()
    expect(g.attributes('tabindex')).toBeUndefined()
    expect(g.attributes('aria-label')).toBeUndefined()
  })

  it('emits activate on click', async () => {
    const wrapper = mount(ToothSurface, { props: BASE_PROPS })
    await wrapper.find('g').trigger('click')
    expect(wrapper.emitted('activate')).toHaveLength(1)
  })

  it('emits activate on Enter and Space keydown, and not on other keys', async () => {
    const wrapper = mount(ToothSurface, { props: BASE_PROPS })
    await wrapper.find('g').trigger('keydown', { key: 'Enter' })
    await wrapper.find('g').trigger('keydown', { key: ' ' })
    await wrapper.find('g').trigger('keydown', { key: 'Tab' })
    expect(wrapper.emitted('activate')).toHaveLength(2)
  })

  it('does not emit activate on click or keydown when not interactive', async () => {
    const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, interactive: false } })
    await wrapper.find('g').trigger('click')
    await wrapper.find('g').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('activate')).toBeUndefined()
  })

  it('renders an X glyph when glyph is "x"', () => {
    const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, glyph: 'x' } })
    expect(wrapper.find('[data-glyph="x"]').exists()).toBe(true)
    expect(wrapper.find('[data-glyph="x"]').findAll('line')).toHaveLength(2)
  })

  it('renders parallel lines when glyph is "lines"', () => {
    const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, glyph: 'lines' } })
    expect(wrapper.find('[data-glyph="lines"]').exists()).toBe(true)
  })

  it('renders a dot when glyph is "dot"', () => {
    const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, glyph: 'dot' } })
    expect(wrapper.find('[data-glyph="dot"]').exists()).toBe(true)
  })

  it('renders no glyph overlay for "filled", "outline", or null', () => {
    for (const glyph of ['filled', 'outline', null] as const) {
      const wrapper = mount(ToothSurface, { props: { ...BASE_PROPS, glyph } })
      expect(wrapper.find('[data-glyph]').exists()).toBe(false)
    }
  })

  it('renders a selection ring only when selected', () => {
    const unselected = mount(ToothSurface, { props: BASE_PROPS })
    expect(unselected.findAll('path')).toHaveLength(1)

    const selected = mount(ToothSurface, { props: { ...BASE_PROPS, selected: true } })
    expect(selected.findAll('path')).toHaveLength(2)
  })
})
