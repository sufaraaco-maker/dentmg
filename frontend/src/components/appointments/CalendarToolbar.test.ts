import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import CalendarToolbar from './CalendarToolbar.vue'
import { i18n } from '@/test/setup'

describe('CalendarToolbar', () => {
  it('renders the range label and the four view options', () => {
    const wrapper = mount(CalendarToolbar, {
      props: { viewMode: 'timeGridWeek', rangeLabel: 'Jul 12 – Jul 18, 2026' },
    })
    expect(wrapper.text()).toContain('Jul 12 – Jul 18, 2026')
    expect(wrapper.text()).toContain('Day')
    expect(wrapper.text()).toContain('Week')
    expect(wrapper.text()).toContain('Month')
    expect(wrapper.text()).toContain('List')
  })

  it('emits update:viewMode when the List option is selected', async () => {
    const wrapper = mount(CalendarToolbar, {
      props: { viewMode: 'timeGridWeek', rangeLabel: 'Jul 12 – Jul 18, 2026' },
    })

    await wrapper
      .findAll('button')
      .find((b) => b.text() === 'List')
      ?.trigger('click')

    expect(wrapper.emitted('update:viewMode')).toEqual([['list']])
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

  describe('RTL', () => {
    afterEach(() => {
      i18n.global.locale.value = 'en'
    })

    it('swaps the previous/next chevron icons so they still point the same way the mirrored calendar grid reads', async () => {
      // Only 'en' messages are registered on the shared test i18n instance (src/test/setup.ts),
      // so aria-label text stays in English even at locale 'ar' — only `isRtl` (locale === 'ar')
      // needs to flip here, not the translated strings.
      i18n.global.locale.value = 'ar'
      const wrapper = mount(CalendarToolbar, {
        props: { viewMode: 'timeGridDay', rangeLabel: '15 يوليو، 2026' },
      })

      const prevIcon = wrapper.find('button[aria-label="Previous period"] .p-button-icon')
      const nextIcon = wrapper.find('button[aria-label="Next period"] .p-button-icon')

      // LTR: previous points left, next points right. RTL must swap both, or "next" would point
      // the opposite way from how AppointmentCalendar's own mirrored day grid reads forward.
      expect(prevIcon.classes()).toContain('pi-chevron-right')
      expect(nextIcon.classes()).toContain('pi-chevron-left')
    })
  })
})
