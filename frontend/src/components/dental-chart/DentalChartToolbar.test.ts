import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import DentalChartToolbar from './DentalChartToolbar.vue'

function mountToolbar(overrides: Partial<InstanceType<typeof DentalChartToolbar>['$props']> = {}) {
  return mount(DentalChartToolbar, {
    props: {
      viewMode: 'chart',
      dentitionFilter: 'permanent',
      statusFilter: null,
      categoryFilter: null,
      toothFilter: null,
      ...overrides,
    },
  })
}

describe('DentalChartToolbar — controlled props', () => {
  it('emits update:viewMode when the view SelectButton changes', async () => {
    const wrapper = mountToolbar()
    const viewButtons = wrapper.findAllComponents({ name: 'SelectButton' })
    await viewButtons[0].vm.$emit('update:modelValue', 'list')
    expect(wrapper.emitted('update:viewMode')).toEqual([['list']])
  })

  it('emits update:dentitionFilter when the dentition SelectButton changes', async () => {
    const wrapper = mountToolbar()
    const dentitionButtons = wrapper.findAllComponents({ name: 'SelectButton' })
    await dentitionButtons[1].vm.$emit('update:modelValue', 'primary')
    expect(wrapper.emitted('update:dentitionFilter')).toEqual([['primary']])
  })

  it('emits update:statusFilter/update:categoryFilter/update:toothFilter from their selects', async () => {
    const wrapper = mountToolbar()
    const selects = wrapper.findAllComponents({ name: 'Select' })

    await selects[0].vm.$emit('update:modelValue', 'active')
    expect(wrapper.emitted('update:statusFilter')).toEqual([['active']])

    await selects[1].vm.$emit('update:modelValue', 'finding')
    expect(wrapper.emitted('update:categoryFilter')).toEqual([['finding']])

    await selects[2].vm.$emit('update:modelValue', '16')
    expect(wrapper.emitted('update:toothFilter')).toEqual([['16']])
  })

  it('emits add-entry when the Add Entry button is clicked', async () => {
    const wrapper = mountToolbar()
    const addButton = wrapper.findAll('button').find((b) => b.text().includes('Add Entry'))
    await addButton?.trigger('click')
    expect(wrapper.emitted('add-entry')).toHaveLength(1)
  })
})

describe('DentalChartToolbar — tooth options scoped by dentition', () => {
  it('only lists permanent tooth codes when dentitionFilter is permanent', () => {
    const wrapper = mountToolbar({ dentitionFilter: 'permanent' })
    const toothSelect = wrapper.findAllComponents({ name: 'Select' })[2]
    const options = toothSelect.props('options') as { value: string | null }[]
    const codes = options.map((o) => o.value).filter((v): v is string => v !== null)
    expect(codes).toHaveLength(32)
    expect(codes.every((code) => Number(code[0]) <= 4)).toBe(true)
  })

  it('only lists primary tooth codes when dentitionFilter is primary', () => {
    const wrapper = mountToolbar({ dentitionFilter: 'primary' })
    const toothSelect = wrapper.findAllComponents({ name: 'Select' })[2]
    const options = toothSelect.props('options') as { value: string | null }[]
    const codes = options.map((o) => o.value).filter((v): v is string => v !== null)
    expect(codes).toHaveLength(20)
    expect(codes.every((code) => Number(code[0]) >= 5)).toBe(true)
  })

  it('lists all 52 tooth codes when dentitionFilter is all', () => {
    const wrapper = mountToolbar({ dentitionFilter: 'all' })
    const toothSelect = wrapper.findAllComponents({ name: 'Select' })[2]
    const options = toothSelect.props('options') as { value: string | null }[]
    expect(options.filter((o) => o.value !== null)).toHaveLength(52)
  })
})
