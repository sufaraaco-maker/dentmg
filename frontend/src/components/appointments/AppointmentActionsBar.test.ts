import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentActionsBar from './AppointmentActionsBar.vue'
import { appointmentsApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: {
    confirm: vi.fn(),
    checkIn: vi.fn(),
    start: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    noShow: vi.fn(),
    get: vi.fn(),
  },
  isAppointmentConflictError: (data: unknown): data is { code: string } =>
    typeof data === 'object' && data !== null && 'code' in data,
}))

const StatusActionButtonStub = {
  props: ['action', 'requiresReason', 'loading'],
  emits: ['confirmed'],
  template: '<button :data-action="action" @click="$emit(\'confirmed\')">{{ action }}</button>',
}

function baseAppointment(overrides: Partial<Appointment> = {}): Appointment {
  return {
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
    created_at: '',
    updated_at: '',
    ...overrides,
  }
}

function mountBar(appointment: Appointment) {
  return mount(AppointmentActionsBar, {
    props: { appointment },
    global: { stubs: { StatusActionButton: StatusActionButtonStub } },
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(appointmentsApi.get).mockResolvedValue(baseAppointment())
})

describe('AppointmentActionsBar', () => {
  it('shows Confirm/Check In/Cancel/No Show but not Start/Complete for a scheduled appointment (receptionist)', () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    const wrapper = mountBar(baseAppointment({ status: 'scheduled' }))

    const actions = wrapper.findAll('button').map((b) => b.attributes('data-action'))
    expect(actions).toEqual(expect.arrayContaining(['confirm', 'checkIn', 'cancel', 'noShow']))
    expect(actions).not.toContain('start')
    expect(actions).not.toContain('complete')
  })

  it('shows a "no actions available" message for a completed (terminal) appointment', () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    const wrapper = mountBar(baseAppointment({ status: 'completed' }))

    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.text()).toContain('No actions available for this appointment')
  })

  it('hides Start for a dentist who does not own the appointment', () => {
    useAuthStore().user = { id: 'other-dentist', name: 'Dr. Other', email: 'o@example.com', role: 'dentist' }
    const wrapper = mountBar(baseAppointment({ status: 'checked_in', dentist_id: 'd1' }))

    expect(wrapper.find('button[data-action="start"]').exists()).toBe(false)
  })

  it('shows Start for the treating dentist even without canManageAppointments', () => {
    useAuthStore().user = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }
    const wrapper = mountBar(baseAppointment({ status: 'checked_in', dentist_id: 'd1' }))

    expect(wrapper.find('button[data-action="start"]').exists()).toBe(true)
  })

  it('calls the store action and emits action-completed on success', async () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    const updated = baseAppointment({ status: 'confirmed' })
    vi.mocked(appointmentsApi.confirm).mockResolvedValue(undefined)
    vi.mocked(appointmentsApi.get).mockResolvedValue(updated)

    const wrapper = mountBar(baseAppointment({ status: 'scheduled' }))
    await wrapper.find('button[data-action="confirm"]').trigger('click')
    await flushPromises()

    expect(appointmentsApi.confirm).toHaveBeenCalledWith('a1')
    expect(wrapper.emitted('action-completed')?.[0]).toEqual([updated])
  })

  it('re-syncs to the backend state on a 422 (stale visibility assumption)', async () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    const fresh = baseAppointment({ status: 'checked_in' })
    vi.mocked(appointmentsApi.confirm).mockRejectedValue({ response: { status: 422 } })
    vi.mocked(appointmentsApi.get).mockResolvedValue(fresh)

    const wrapper = mountBar(baseAppointment({ status: 'scheduled' }))
    await wrapper.find('button[data-action="confirm"]').trigger('click')
    await flushPromises()

    expect(appointmentsApi.get).toHaveBeenCalledWith('a1')
    expect(wrapper.emitted('action-completed')?.[0]).toEqual([fresh])
  })

  it('shows a ConflictAlert for an early no-show conflict and retries with the override on Book Anyway', async () => {
    useAuthStore().user = { id: 'u1', name: 'Front Desk', email: 'fd@example.com', role: 'receptionist' }
    const conflict = {
      message: 'Too early to mark no-show',
      code: 'early_no_show',
      overridable: true,
      override_field: 'override_early_no_show',
    }
    const updated = baseAppointment({ status: 'no_show' })
    vi.mocked(appointmentsApi.noShow).mockRejectedValueOnce(conflict).mockResolvedValueOnce(undefined)
    vi.mocked(appointmentsApi.get).mockResolvedValue(updated)

    const wrapper = mountBar(baseAppointment({ status: 'scheduled' }))
    await wrapper.find('button[data-action="noShow"]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('Too early to mark no-show')

    const overrideButton = wrapper
      .findAllComponents({ name: 'Button' })
      .find((b) => b.text() === 'Book Anyway')
    await overrideButton!.trigger('click')
    await flushPromises()

    expect(appointmentsApi.noShow).toHaveBeenLastCalledWith('a1', { override_early_no_show: true })
    // The first (unconfirmed) attempt only surfaced the conflict — no action-completed emit yet.
    expect(wrapper.emitted('action-completed')?.[0]).toEqual([updated])
  })
})
