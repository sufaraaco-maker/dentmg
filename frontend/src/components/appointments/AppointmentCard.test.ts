import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import AppointmentCard from './AppointmentCard.vue'
import type { Appointment } from '@/types/appointment'

const APPOINTMENT: Appointment = {
  id: 'a1',
  patient_id: 'p1',
  dentist_id: 'd1',
  appointment_type_id: 't1',
  start_at: '2026-07-20T09:00:00+00:00',
  end_at: '2026-07-20T09:30:00+00:00',
  duration_minutes: 30,
  status: 'scheduled',
  reason: 'Checkup',
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
  patient: { id: 'p1', patient_code: 'PT-0001', full_name: 'Jane Doe' },
  dentist: { id: 'd1', name: 'Dr. Smith' },
  appointment_type: {
    id: 't1',
    name: 'Cleaning',
    default_duration_minutes: 30,
    color: '#0ea5e9',
    is_active: true,
    created_at: '',
    updated_at: '',
  },
}

describe('AppointmentCard', () => {
  it('renders type, patient, dentist, reason, and status with no store dependency', () => {
    const wrapper = mount(AppointmentCard, { props: { appointment: APPOINTMENT } })

    expect(wrapper.text()).toContain('Cleaning')
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Dr. Smith')
    expect(wrapper.text()).toContain('Checkup')
    expect(wrapper.text()).toContain('Scheduled')
  })

  it('emits click when the card is clicked', async () => {
    const wrapper = mount(AppointmentCard, { props: { appointment: APPOINTMENT } })
    await wrapper.findComponent({ name: 'Card' }).trigger('click')
    expect(wrapper.emitted('click')).toBeTruthy()
  })

  it('renders the actions slot without triggering the card click', async () => {
    const wrapper = mount(AppointmentCard, {
      props: { appointment: APPOINTMENT },
      slots: { actions: '<button class="edit-btn">Edit</button>' },
    })

    await wrapper.find('.edit-btn').trigger('click')
    expect(wrapper.emitted('click')).toBeFalsy()
  })

  it('falls back to a placeholder name when the appointment type is missing', () => {
    const wrapper = mount(AppointmentCard, {
      props: { appointment: { ...APPOINTMENT, appointment_type: undefined } },
    })

    expect(wrapper.text()).toContain('Untitled appointment')
  })

  it('is not keyboard-focusable by default (a non-interactive summary, e.g. the Detail header)', () => {
    const wrapper = mount(AppointmentCard, { props: { appointment: APPOINTMENT } })
    const root = wrapper.findComponent({ name: 'Card' })

    expect(root.attributes('tabindex')).toBeUndefined()
    expect(root.attributes('role')).toBeUndefined()
  })

  it('is keyboard-operable when clickable (design doc §14 — no mouse-only affordance)', async () => {
    const wrapper = mount(AppointmentCard, { props: { appointment: APPOINTMENT, clickable: true } })
    const root = wrapper.findComponent({ name: 'Card' })

    expect(root.attributes('tabindex')).toBe('0')
    expect(root.attributes('role')).toBe('button')

    await root.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('click')).toHaveLength(1)

    await root.trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('click')).toHaveLength(2)

    await root.trigger('keydown', { key: 'Tab' })
    expect(wrapper.emitted('click')).toHaveLength(2)
  })
})
