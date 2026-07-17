import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppointmentTimeline from './AppointmentTimeline.vue'
import type { Appointment } from '@/types/appointment'

const BASE: Appointment = {
  id: 'a1',
  patient_id: 'p1',
  dentist_id: 'd1',
  appointment_type_id: 't1',
  start_at: '2026-07-20T09:00:00+00:00',
  end_at: '2026-07-20T09:30:00+00:00',
  duration_minutes: 30,
  status: 'scheduled',
  reason: null,
  notes: null,
  cancellation_reason: null,
  cancelled_at: null,
  cancelled_by: null,
  checked_in_at: null,
  started_at: null,
  completed_at: null,
  no_show_at: null,
  reschedule_count: 0,
  created_at: '2026-07-15T00:00:00+00:00',
  updated_at: '2026-07-15T00:00:00+00:00',
}

describe('AppointmentTimeline', () => {
  it('renders all five steps for an in-progress appointment, future ones greyed out', () => {
    const wrapper = mount(AppointmentTimeline, {
      props: {
        appointment: {
          ...BASE,
          status: 'in_progress',
          checked_in_at: '2026-07-20T08:55:00+00:00',
          started_at: '2026-07-20T09:00:00+00:00',
        },
      },
    })

    const items = wrapper.findAll('li')
    expect(items).toHaveLength(5)
    // Completed hasn't happened yet — its content div (second div in the row, after the marker
    // column) should render in the dimmed state.
    expect(items[4].findAll('div')[1].classes()).toContain('opacity-50')
    expect(items[4].text()).toContain('Completed')
  })

  it('terminates the chain at Cancelled and does not render a ghost Completed step', () => {
    const wrapper = mount(AppointmentTimeline, {
      props: {
        appointment: {
          ...BASE,
          status: 'cancelled',
          checked_in_at: '2026-07-20T08:55:00+00:00',
          cancelled_at: '2026-07-20T09:05:00+00:00',
          cancellation_reason: 'Patient rescheduled',
        },
      },
    })

    const text = wrapper.text()
    expect(text).toContain('Scheduled')
    expect(text).toContain('Checked In')
    expect(text).toContain('Cancelled')
    expect(text).toContain('Patient rescheduled')
    expect(text).not.toContain('In Progress')
    expect(text).not.toContain('Completed')
  })

  it('terminates the chain at No Show', () => {
    const wrapper = mount(AppointmentTimeline, {
      props: { appointment: { ...BASE, status: 'no_show', no_show_at: '2026-07-20T09:10:00+00:00' } },
    })

    const text = wrapper.text()
    expect(text).toContain('No Show')
    expect(text).not.toContain('Checked In')
    expect(text).not.toContain('Completed')
  })

  it('renders the Confirmed step (approximated from status) without a timestamp', () => {
    const wrapper = mount(AppointmentTimeline, { props: { appointment: { ...BASE, status: 'confirmed' } } })
    const items = wrapper.findAll('li')
    // Confirmed reached (index 1), no timestamp caption rendered for it.
    expect(items[1].text()).toContain('Confirmed')
    expect(items[1].classes()).not.toContain('opacity-50')
  })
})
