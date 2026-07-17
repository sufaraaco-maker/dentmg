import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DentistScheduleView from './DentistScheduleView.vue'
import WorkingHoursEditor from '@/components/appointments/WorkingHoursEditor.vue'
import TimeOffCalendar from '@/components/appointments/TimeOffCalendar.vue'
import TimeOffFormDialog from '@/components/appointments/TimeOffFormDialog.vue'
import { workingHoursApi, timeOffApi, providersApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/appointments', () => ({
  workingHoursApi: { listForDentist: vi.fn().mockResolvedValue([]), create: vi.fn(), remove: vi.fn() },
  timeOffApi: { listForDentist: vi.fn().mockResolvedValue([]), create: vi.fn(), remove: vi.fn() },
  providersApi: { listAll: vi.fn() },
  appointmentsApi: { list: vi.fn().mockResolvedValue([]) },
}))

const mockedWorkingHoursApi = vi.mocked(workingHoursApi)
const mockedTimeOffApi = vi.mocked(timeOffApi)
const mockedProvidersApi = vi.mocked(providersApi)

const admin: AuthUser = { id: 'a1', name: 'Admin', email: 'a@example.com', role: 'admin' }
const dentist: AuthUser = { id: 'd1', name: 'Dr. Smith', email: 'd1@example.com', role: 'dentist' }

function setUser(user: AuthUser) {
  useAuthStore().user = user
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedWorkingHoursApi.listForDentist.mockResolvedValue([])
  mockedTimeOffApi.listForDentist.mockResolvedValue([])
})

describe('DentistScheduleView', () => {
  it('shows a dentist selector for admin and auto-selects the first dentist once loaded', async () => {
    setUser(admin)
    mockedProvidersApi.listAll.mockResolvedValue([dentist])
    const wrapper = mount(DentistScheduleView)
    await flushPromises()

    expect(wrapper.findComponent({ name: 'DentistSelect' }).exists()).toBe(true)
    expect(wrapper.findComponent(WorkingHoursEditor).props('dentistId')).toBe('d1')
    expect(mockedWorkingHoursApi.listForDentist).toHaveBeenCalledWith('d1')
    wrapper.unmount()
  })

  it('shows the select-a-dentist prompt for admin when there are no dentists yet', async () => {
    setUser(admin)
    mockedProvidersApi.listAll.mockResolvedValue([])
    const wrapper = mount(DentistScheduleView)
    await flushPromises()

    expect(wrapper.text()).toContain('Select a dentist to view their schedule')
    expect(wrapper.findComponent(WorkingHoursEditor).exists()).toBe(false)
    wrapper.unmount()
  })

  it('hides the dentist selector for a dentist and shows only their own schedule', async () => {
    setUser(dentist)
    const wrapper = mount(DentistScheduleView)
    await flushPromises()

    expect(wrapper.findComponent({ name: 'DentistSelect' }).exists()).toBe(false)
    expect(wrapper.findComponent(WorkingHoursEditor).props('dentistId')).toBe('d1')
    expect(mockedProvidersApi.listAll).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('opens the Time Off dialog when TimeOffCalendar emits add-clicked', async () => {
    setUser(dentist)
    const wrapper = mount(DentistScheduleView)
    await flushPromises()

    expect(wrapper.findComponent(TimeOffFormDialog).props('visible')).toBe(false)

    await wrapper.findComponent(TimeOffCalendar).vm.$emit('add-clicked')

    expect(wrapper.findComponent(TimeOffFormDialog).props('visible')).toBe(true)
    wrapper.unmount()
  })
})
