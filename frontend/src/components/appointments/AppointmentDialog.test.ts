import { mount, flushPromises } from '@vue/test-utils'
import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentDialog from './AppointmentDialog.vue'
import { appointmentsApi, appointmentTypesApi, providersApi } from '@/services/appointments'
import { api } from '@/lib/api'
import type { Appointment } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentsApi: {
    create: vi.fn(),
    update: vi.fn(),
    get: vi.fn(),
    availableSlots: vi.fn().mockResolvedValue([]),
  },
  appointmentTypesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
  providersApi: { listAll: vi.fn() },
  isAppointmentConflictError: (data: unknown): data is { code: string } =>
    typeof data === 'object' && data !== null && 'code' in data,
}))

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const DENTIST = { id: 'd1', name: 'Dr. Smith', email: 'smith@example.com', role: 'dentist' as const }
const TYPE = {
  id: 't1',
  name: 'Cleaning',
  default_duration_minutes: 30,
  color: '#0ea5e9',
  is_active: true,
  created_at: '',
  updated_at: '',
}

const APPOINTMENT: Appointment = {
  id: 'a1',
  patient_id: 'p1',
  dentist_id: 'd1',
  appointment_type_id: 't1',
  start_at: '2026-07-20T09:00:00.000Z',
  end_at: '2026-07-20T09:30:00.000Z',
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
  created_at: '',
  updated_at: '',
  patient: { id: 'p1', patient_code: 'PT-0001', full_name: 'Jane Doe' },
  dentist: { id: 'd1', name: 'Dr. Smith' },
  appointment_type: TYPE,
}

beforeEach(() => {
  setActivePinia(createPinia())
  // These mocks are shared module-level vi.fn()s across every test in this file (the
  // vi.mock factory above only runs once) — clear call history so each test's absolute
  // toHaveBeenCalledTimes assertions aren't polluted by a previous test's calls.
  vi.clearAllMocks()
  vi.mocked(providersApi.listAll).mockResolvedValue([DENTIST])
  vi.mocked(appointmentTypesApi.list).mockResolvedValue([TYPE])
  vi.mocked(api.get).mockResolvedValue({ data: { data: [] } })
  // The appointments store re-hydrates via `GET /appointments/{id}` after every mutation
  // (design doc §11.4) — every create/update path in this file goes through it.
  vi.mocked(appointmentsApi.get).mockResolvedValue(APPOINTMENT)
})

describe('AppointmentDialog', () => {
  it('shows the create title and an empty form when opened without an appointment', async () => {
    const wrapper = mount(AppointmentDialog, { props: { visible: true } })
    await flushPromises()

    expect(wrapper.findComponent({ name: 'Dialog' }).props('header')).toBe('New Appointment')
  })

  it('shows the edit title, prefills the form, and locks the patient tab', async () => {
    const wrapper = mount(AppointmentDialog, { props: { visible: true, appointment: APPOINTMENT } })
    await flushPromises()

    expect(wrapper.findComponent({ name: 'Dialog' }).props('header')).toBe('Edit Appointment')
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('cancel this appointment and create a new one')
  })

  it('applies a prefill (dentist + start time) in create mode', async () => {
    const wrapper = mount(AppointmentDialog, {
      props: {
        visible: true,
        prefill: { dentist_id: 'd1', start_at: '2026-07-20T09:00:00.000Z' },
      },
    })
    await flushPromises()

    const dentistSelect = wrapper.findComponent({ name: 'DentistSelect' })
    expect(dentistSelect.props('modelValue')).toBe('d1')
  })

  it('blocks submit and surfaces field errors when required fields are missing', async () => {
    const wrapper = mount(AppointmentDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(appointmentsApi.create).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('This field is required')
  })

  it('creates an appointment and emits saved on success', async () => {
    vi.mocked(appointmentsApi.create).mockResolvedValue(APPOINTMENT)

    const wrapper = mount(AppointmentDialog, {
      props: {
        visible: true,
        prefill: { dentist_id: 'd1', patient_id: 'p1', start_at: '2026-07-20T09:00:00.000Z' },
      },
    })
    await flushPromises()

    await wrapper.findComponent({ name: 'AppointmentTypeSelect' }).vm.$emit('update:modelValue', 't1')
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(appointmentsApi.create).toHaveBeenCalledTimes(1)
    expect(wrapper.emitted('saved')?.[0][0]).toEqual(APPOINTMENT)
    expect(wrapper.emitted('update:visible')?.[0][0]).toBe(false)
  })

  it('renders a ConflictAlert and lets the user Book Anyway on an overridable conflict', async () => {
    vi.mocked(appointmentsApi.create)
      .mockRejectedValueOnce({
        message: 'Patient already has an appointment at this time.',
        code: 'patient_conflict',
        overridable: true,
        override_field: 'override_patient_conflict',
      })
      .mockResolvedValueOnce(APPOINTMENT)

    const wrapper = mount(AppointmentDialog, {
      props: {
        visible: true,
        prefill: { dentist_id: 'd1', patient_id: 'p1', start_at: '2026-07-20T09:00:00.000Z' },
      },
    })
    await flushPromises()

    await wrapper.findComponent({ name: 'AppointmentTypeSelect' }).vm.$emit('update:modelValue', 't1')
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Patient already has an appointment at this time.')

    const bookAnyway = wrapper.findAll('button').find((b) => b.text().includes('Book Anyway'))
    expect(bookAnyway).toBeTruthy()

    await bookAnyway?.trigger('click')
    await flushPromises()

    expect(appointmentsApi.create).toHaveBeenCalledTimes(2)
    expect(appointmentsApi.create).toHaveBeenLastCalledWith(
      expect.objectContaining({ override_patient_conflict: true }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
  })

  it('moves focus to the patient search field when opened, and restores it on close (design doc §14)', async () => {
    const trigger = document.createElement('button')
    document.body.appendChild(trigger)
    trigger.focus()

    const wrapper = mount(AppointmentDialog, { props: { visible: false }, attachTo: document.body })
    await flushPromises()

    await wrapper.setProps({ visible: true })
    await flushPromises()
    await nextTick()

    const searchInput = wrapper.find('input[placeholder="Search by name, code, or phone"]')
    expect(document.activeElement).toBe(searchInput.element)

    await wrapper.setProps({ visible: false })
    await flushPromises()

    expect(document.activeElement).toBe(trigger)

    wrapper.unmount()
    trigger.remove()
  })
})
