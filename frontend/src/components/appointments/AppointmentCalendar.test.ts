import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FullCalendar from '@fullcalendar/vue3'
import AppointmentCalendar from './AppointmentCalendar.vue'
import type { AppointmentCalendarEvent } from './AppointmentCalendar.vue'

function makeEvents(): AppointmentCalendarEvent[] {
  return [
    {
      id: 'a1',
      title: 'Jane Doe',
      start: '2026-07-15T09:00:00',
      end: '2026-07-15T09:30:00',
      backgroundColor: '#0ea5e9',
      extendedProps: { status: 'scheduled', hasNotes: false },
    },
  ]
}

describe('AppointmentCalendar', () => {
  it('mounts and renders the FullCalendar grid with a passed event', async () => {
    const wrapper = mount(AppointmentCalendar, {
      props: {
        view: 'timeGridWeek',
        currentDate: new Date(2026, 6, 15),
        events: makeEvents(),
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        loading: false,
      },
    })

    expect(wrapper.find('.fc').exists()).toBe(true)
  })

  it('forces FullCalendar to timeZone "UTC" so UTC-labeled event digits are never re-expressed through the browser\'s own offset', () => {
    const wrapper = mount(AppointmentCalendar, {
      props: {
        view: 'timeGridWeek',
        currentDate: new Date(2026, 6, 15),
        events: makeEvents(),
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        loading: false,
      },
    })

    expect(wrapper.findComponent(FullCalendar).props('options').timeZone).toBe('UTC')
  })

  it("converts the local `currentDate` prop so FullCalendar's UTC-mode initialDate lands on the same calendar day", () => {
    // Regression test for a confirmed real bug: passing `currentDate` straight through landed
    // FullCalendar on the wrong (previous) day for any positive-UTC-offset host, since
    // `timeZone: 'UTC'` makes it read `initialDate` via UTC getters, not local ones.
    const currentDate = new Date(2026, 6, 17, 0, 0, 0) // July 17 2026, local midnight
    const wrapper = mount(AppointmentCalendar, {
      props: {
        view: 'timeGridWeek',
        currentDate,
        events: [],
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        loading: false,
      },
    })

    const initialDate = wrapper.findComponent(FullCalendar).props('options').initialDate as Date
    expect(initialDate.getUTCFullYear()).toBe(2026)
    expect(initialDate.getUTCMonth()).toBe(6)
    expect(initialDate.getUTCDate()).toBe(17)
  })

  it('shows the loading progress bar only while loading', () => {
    const loading = mount(AppointmentCalendar, {
      props: {
        view: 'timeGridDay',
        currentDate: new Date(2026, 6, 15),
        events: [],
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        loading: true,
      },
    })
    expect(loading.findComponent({ name: 'ProgressBar' }).exists()).toBe(true)

    const notLoading = mount(AppointmentCalendar, {
      props: {
        view: 'timeGridDay',
        currentDate: new Date(2026, 6, 15),
        events: [],
        slotMinTime: '08:00:00',
        slotMaxTime: '18:00:00',
        loading: false,
      },
    })
    expect(notLoading.findComponent({ name: 'ProgressBar' }).exists()).toBe(false)
  })
})
