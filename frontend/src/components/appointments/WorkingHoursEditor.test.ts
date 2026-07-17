import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WorkingHoursEditor from './WorkingHoursEditor.vue'
import WorkingHoursDayRow from './WorkingHoursDayRow.vue'
import { workingHoursApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { DentistWorkingHour } from '@/types/appointment'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/appointments', () => ({
  workingHoursApi: {
    listForDentist: vi.fn(),
    create: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(workingHoursApi)

const admin: AuthUser = { id: 'a1', name: 'Admin', email: 'a@example.com', role: 'admin' }
const dentist: AuthUser = { id: 'd1', name: 'Dr. X', email: 'd@example.com', role: 'dentist' }

const monday: DentistWorkingHour = {
  id: 's1',
  user_id: 'd1',
  day_of_week: 1,
  start_time: '09:00',
  end_time: '17:00',
  is_active: true,
}

function setUser(user: AuthUser) {
  const auth = useAuthStore()
  auth.user = user
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedApi.listForDentist.mockResolvedValue([monday])
})

describe('WorkingHoursEditor', () => {
  it('fetches the dentist working hours on mount and renders all 7 days', async () => {
    setUser(admin)
    const wrapper = mount(WorkingHoursEditor, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(mockedApi.listForDentist).toHaveBeenCalledWith('d1')
    expect(wrapper.findAllComponents(WorkingHoursDayRow)).toHaveLength(7)
  })

  it('shows the view-only note and non-editable rows for a non-admin', async () => {
    setUser(dentist)
    const wrapper = mount(WorkingHoursEditor, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('Only an admin can edit')
    const mondayRow = wrapper.findAllComponents(WorkingHoursDayRow)[1]
    expect(mondayRow.props('editable')).toBe(false)
  })

  it('deletes-then-creates when a day row commits a changed shift', async () => {
    setUser(admin)
    mockedApi.create.mockResolvedValue({ ...monday, id: 's2', start_time: '10:00' })
    const wrapper = mount(WorkingHoursEditor, { props: { dentistId: 'd1' } })
    await flushPromises()

    const mondayRow = wrapper.findAllComponents(WorkingHoursDayRow)[1]
    await mondayRow.vm.$emit('update:shifts', [
      { id: 's1', day_of_week: 1, start_time: '10:00', end_time: '17:00', is_active: true },
    ])
    await flushPromises()

    expect(mockedApi.remove).toHaveBeenCalledWith('d1', 's1')
    expect(mockedApi.create).toHaveBeenCalledWith('d1', {
      day_of_week: 1,
      start_time: '10:00',
      end_time: '17:00',
      is_active: true,
    })
  })

  it('only creates (no delete) for a brand-new shift with no id', async () => {
    setUser(admin)
    mockedApi.create.mockResolvedValue({ ...monday, id: 's3', start_time: '18:00', end_time: '19:00' })
    const wrapper = mount(WorkingHoursEditor, { props: { dentistId: 'd1' } })
    await flushPromises()

    const mondayRow = wrapper.findAllComponents(WorkingHoursDayRow)[1]
    await mondayRow.vm.$emit('update:shifts', [
      { id: 's1', day_of_week: 1, start_time: '09:00', end_time: '17:00', is_active: true },
      { id: null, day_of_week: 1, start_time: '18:00', end_time: '19:00', is_active: true },
    ])
    await flushPromises()

    expect(mockedApi.remove).not.toHaveBeenCalled()
    expect(mockedApi.create).toHaveBeenCalledWith('d1', {
      day_of_week: 1,
      start_time: '18:00',
      end_time: '19:00',
      is_active: true,
    })
  })

  it("copies a day's shifts to the target days via create calls", async () => {
    setUser(admin)
    mockedApi.create.mockResolvedValue({ ...monday, id: 's4', day_of_week: 2 })
    const wrapper = mount(WorkingHoursEditor, { props: { dentistId: 'd1' } })
    await flushPromises()

    const mondayRow = wrapper.findAllComponents(WorkingHoursDayRow)[1]
    await mondayRow.vm.$emit('copy-to', [2, 3])
    await flushPromises()

    expect(mockedApi.create).toHaveBeenCalledWith('d1', {
      day_of_week: 2,
      start_time: '09:00',
      end_time: '17:00',
      is_active: true,
    })
    expect(mockedApi.create).toHaveBeenCalledWith('d1', {
      day_of_week: 3,
      start_time: '09:00',
      end_time: '17:00',
      is_active: true,
    })
  })
})
