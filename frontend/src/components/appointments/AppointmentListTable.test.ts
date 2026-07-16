import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppointmentListTable from './AppointmentListTable.vue'
import type { Appointment } from '@/types/appointment'

function makeAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
    id: 'appt-1',
    patient_id: 'p1',
    dentist_id: 'd1',
    appointment_type_id: 't1',
    start_at: '2026-07-16T10:00:00+00:00',
    end_at: '2026-07-16T10:30:00+00:00',
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
    created_at: '2026-07-01T00:00:00+00:00',
    updated_at: '2026-07-01T00:00:00+00:00',
    patient: { id: 'p1', patient_code: 'P-0001', full_name: 'Jane Doe' },
    dentist: { id: 'd1', name: 'Dr. Smith' },
    appointment_type: {
      id: 't1',
      name: 'Cleaning',
      default_duration_minutes: 30,
      color: '#22c55e',
      is_active: true,
      created_at: '',
      updated_at: '',
    },
    ...overrides,
  }
}

describe('AppointmentListTable', () => {
  it('renders a row with patient, dentist, type, duration, and status', () => {
    const wrapper = mount(AppointmentListTable, {
      props: { appointments: [makeAppointment()], loading: false },
    })

    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Dr. Smith')
    expect(wrapper.text()).toContain('Cleaning')
    expect(wrapper.text()).toContain('30 min')
    expect(wrapper.text()).toContain('Scheduled')
  })

  it('falls back to a placeholder patient name when none is loaded', () => {
    const wrapper = mount(AppointmentListTable, {
      props: { appointments: [makeAppointment({ patient: undefined })], loading: false },
    })

    expect(wrapper.text()).toContain('Untitled appointment')
  })

  it('shows the localized empty state when there are no appointments', () => {
    const wrapper = mount(AppointmentListTable, { props: { appointments: [], loading: false } })
    expect(wrapper.text()).toContain('No appointments found')
  })

  it('emits row-click with the appointment id when a row is clicked', async () => {
    const wrapper = mount(AppointmentListTable, {
      props: { appointments: [makeAppointment()], loading: false },
    })

    await wrapper.find('tbody tr').trigger('click')

    expect(wrapper.emitted('row-click')).toEqual([['appt-1']])
  })
})
