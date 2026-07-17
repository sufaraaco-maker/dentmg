import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import TimeOffCalendar from './TimeOffCalendar.vue'
import { timeOffApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { DentistTimeOff } from '@/types/appointment'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/appointments', () => ({
  timeOffApi: {
    listForDentist: vi.fn(),
    create: vi.fn(),
    remove: vi.fn(),
  },
}))

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

const mockedApi = vi.mocked(timeOffApi)

const admin: AuthUser = { id: 'a1', name: 'Admin', email: 'a@example.com', role: 'admin' }
const owner: AuthUser = { id: 'd1', name: 'Dr. X', email: 'd1@example.com', role: 'dentist' }
const otherDentist: AuthUser = { id: 'd2', name: 'Dr. Y', email: 'd2@example.com', role: 'dentist' }

const entry: DentistTimeOff = {
  id: 't1',
  user_id: 'd1',
  start_at: '2026-08-01T09:00:00+00:00',
  end_at: '2026-08-05T17:00:00+00:00',
  reason: 'Vacation: Summer trip',
}

function setUser(user: AuthUser) {
  useAuthStore().user = user
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedApi.listForDentist.mockResolvedValue([entry])
})

describe('TimeOffCalendar', () => {
  it('fetches and renders entries for the dentist', async () => {
    setUser(admin)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(mockedApi.listForDentist).toHaveBeenCalledWith('d1')
    expect(wrapper.text()).toContain('Vacation: Summer trip')
  })

  it('shows the empty state when there are no entries', async () => {
    mockedApi.listForDentist.mockResolvedValue([])
    setUser(admin)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('No time off scheduled')
  })

  it('emits add-clicked when the Add Time Off button is clicked (admin)', async () => {
    setUser(admin)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('add-clicked')).toBeTruthy()
  })

  it('lets a dentist manage their own time-off', async () => {
    setUser(owner)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('Add Time Off')
    expect(wrapper.findAll('button').length).toBeGreaterThan(0)
  })

  it("hides manage actions for a dentist viewing another dentist's schedule", async () => {
    setUser(otherDentist)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('deletes an entry once the confirm popup is accepted', async () => {
    setUser(admin)
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)
    mockedApi.remove.mockResolvedValue(undefined)
    const wrapper = mount(TimeOffCalendar, { props: { dentistId: 'd1' } })
    await flushPromises()

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    await requireMock.mock.calls[0][0].accept()

    expect(mockedApi.remove).toHaveBeenCalledWith('d1', 't1')
  })
})
