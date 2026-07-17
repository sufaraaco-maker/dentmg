import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TimeOffFormDialog from './TimeOffFormDialog.vue'
import { timeOffApi, appointmentsApi } from '@/services/appointments'
import type { Appointment, DentistTimeOff } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  timeOffApi: { listForDentist: vi.fn(), create: vi.fn(), remove: vi.fn() },
  appointmentsApi: { list: vi.fn() },
}))

const mockedTimeOffApi = vi.mocked(timeOffApi)
const mockedAppointmentsApi = vi.mocked(appointmentsApi)

const created: DentistTimeOff = {
  id: 't1',
  user_id: 'd1',
  start_at: '2026-08-01T09:00:00+00:00',
  end_at: '2026-08-05T17:00:00+00:00',
  reason: 'Vacation: Summer trip',
}

async function setDateRange(wrapper: ReturnType<typeof mount>, start: Date, end: Date) {
  const pickers = wrapper.findAllComponents({ name: 'DatePicker' })
  await pickers[0].vm.$emit('update:modelValue', start)
  await pickers[1].vm.$emit('update:modelValue', end)
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedAppointmentsApi.list.mockResolvedValue([])
})

describe('TimeOffFormDialog', () => {
  it('shows validation errors when submitted with no dates', async () => {
    const wrapper = mount(TimeOffFormDialog, { props: { visible: true, dentistId: 'd1' } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedTimeOffApi.create).not.toHaveBeenCalled()
  })

  it('rejects an end date that is not after the start date', async () => {
    const wrapper = mount(TimeOffFormDialog, { props: { visible: true, dentistId: 'd1' } })
    await flushPromises()

    await setDateRange(wrapper, new Date(2026, 7, 5, 9, 0), new Date(2026, 7, 1, 9, 0))
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('End must be after start')
    expect(mockedTimeOffApi.create).not.toHaveBeenCalled()
  })

  it('combines the category and free-text reason on submit', async () => {
    mockedTimeOffApi.create.mockResolvedValue(created)
    const wrapper = mount(TimeOffFormDialog, { props: { visible: true, dentistId: 'd1' } })
    await flushPromises()

    await setDateRange(wrapper, new Date(2026, 7, 1, 9, 0), new Date(2026, 7, 5, 17, 0))
    await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'conference')
    await wrapper.find('textarea').setValue('Dental Association Summit')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedTimeOffApi.create).toHaveBeenCalledWith(
      'd1',
      expect.objectContaining({ reason: 'Conference: Dental Association Summit' }),
    )
    expect(wrapper.emitted('saved')?.[0]).toEqual([created])
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('shows a warning listing appointments that overlap the proposed range', async () => {
    const overlapping: Appointment = {
      id: 'a1',
      patient_id: 'p1',
      dentist_id: 'd1',
      appointment_type_id: 'ty1',
      start_at: '2026-08-02T10:00:00+00:00',
      end_at: '2026-08-02T10:30:00+00:00',
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
      patient: { id: 'p1', patient_code: 'P-1', full_name: 'Jane Doe' },
    }
    mockedAppointmentsApi.list.mockResolvedValue([overlapping])

    const wrapper = mount(TimeOffFormDialog, { props: { visible: true, dentistId: 'd1' } })
    await flushPromises()
    await setDateRange(wrapper, new Date(2026, 7, 1, 9, 0), new Date(2026, 7, 5, 17, 0))
    await flushPromises()

    expect(wrapper.text()).toContain('Jane Doe')
  })

  it('does not warn about a cancelled appointment inside the range', async () => {
    const cancelled: Appointment = {
      id: 'a2',
      patient_id: 'p1',
      dentist_id: 'd1',
      appointment_type_id: 'ty1',
      start_at: '2026-08-02T10:00:00+00:00',
      end_at: '2026-08-02T10:30:00+00:00',
      duration_minutes: 30,
      status: 'cancelled',
      reason: null,
      notes: null,
      cancellation_reason: null,
      cancelled_at: '2026-07-15T00:00:00+00:00',
      cancelled_by: null,
      checked_in_at: null,
      started_at: null,
      completed_at: null,
      no_show_at: null,
      reschedule_count: 0,
      created_at: '2026-07-01T00:00:00+00:00',
      updated_at: '2026-07-01T00:00:00+00:00',
      patient: { id: 'p1', patient_code: 'P-1', full_name: 'Jane Doe' },
    }
    mockedAppointmentsApi.list.mockResolvedValue([cancelled])

    const wrapper = mount(TimeOffFormDialog, { props: { visible: true, dentistId: 'd1' } })
    await flushPromises()
    await setDateRange(wrapper, new Date(2026, 7, 1, 9, 0), new Date(2026, 7, 5, 17, 0))
    await flushPromises()

    expect(wrapper.text()).not.toContain('Jane Doe')
  })
})
