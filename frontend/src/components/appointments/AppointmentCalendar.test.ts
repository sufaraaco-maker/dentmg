import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
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
