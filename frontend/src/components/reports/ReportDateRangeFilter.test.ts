import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ReportDateRangeFilter from './ReportDateRangeFilter.vue'

describe('ReportDateRangeFilter', () => {
  it('renders the given date range and emits apply when the button is clicked', async () => {
    const wrapper = mount(ReportDateRangeFilter, {
      props: { dateFrom: '2026-06-01', dateTo: '2026-06-30' },
    })

    const applyButton = wrapper.findAll('button').find((button) => button.text() === 'Apply')
    await applyButton?.trigger('click')

    expect(wrapper.emitted('apply')).toHaveLength(1)
  })

  it('updates the dateFrom/dateTo models when the pickers change', async () => {
    const wrapper = mount(ReportDateRangeFilter, {
      props: { dateFrom: '2026-06-01', dateTo: '2026-06-30' },
    })

    const pickers = wrapper.findAllComponents({ name: 'DatePicker' })
    await pickers[0].vm.$emit('update:modelValue', new Date(2026, 5, 5))

    expect(wrapper.emitted('update:dateFrom')).toEqual([['2026-06-05']])
  })

  it('renders report-specific extra filters via the slot', () => {
    const wrapper = mount(ReportDateRangeFilter, {
      props: { dateFrom: '2026-06-01', dateTo: '2026-06-30' },
      slots: { 'extra-filters': '<div data-testid="extra">Dentist filter</div>' },
    })

    expect(wrapper.find('[data-testid="extra"]').exists()).toBe(true)
  })
})
