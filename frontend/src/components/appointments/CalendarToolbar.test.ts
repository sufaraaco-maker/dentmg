import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import CalendarToolbar from './CalendarToolbar.vue'

describe('CalendarToolbar', () => {
  it('renders the range label and the three view options', () => {
    const wrapper = mount(CalendarToolbar, {
      props: { viewMode: 'timeGridWeek', rangeLabel: 'Jul 12 – Jul 18, 2026' },
    })
    expect(wrapper.text()).toContain('Jul 12 – Jul 18, 2026')
    expect(wrapper.text()).toContain('Day')
    expect(wrapper.text()).toContain('Week')
    expect(wrapper.text()).toContain('Month')
  })

  it('emits navigate("prev"/"next"/"today") from the nav buttons', async () => {
    const wrapper = mount(CalendarToolbar, {
      props: { viewMode: 'timeGridDay', rangeLabel: 'Jul 15, 2026' },
    })

    await wrapper.find('button[aria-label="Previous period"]').trigger('click')
    await wrapper.find('button[aria-label="Next period"]').trigger('click')
    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'Today')
      ?.trigger('click')

    expect(wrapper.emitted('navigate')).toEqual([['prev'], ['next'], ['today']])
  })

  it('emits new-appointment when the New Appointment button is clicked', async () => {
    const wrapper = mount(CalendarToolbar, {
      props: { viewMode: 'timeGridDay', rangeLabel: 'Jul 15, 2026' },
    })

    await wrapper
      .findAll('button')
      .find((b) => b.text().includes('New Appointment'))
      ?.trigger('click')
    expect(wrapper.emitted('new-appointment')).toHaveLength(1)
  })
})
