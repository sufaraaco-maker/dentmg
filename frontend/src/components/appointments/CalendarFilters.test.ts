import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import CalendarFilters from './CalendarFilters.vue'
import { providersApi, appointmentTypesApi } from '@/services/appointments'
import type { CalendarFilters as CalendarFiltersState } from '@/stores/calendar'

vi.mock('@/services/appointments', () => ({
  providersApi: { listAll: vi.fn() },
  appointmentTypesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

function emptyFilters(): CalendarFiltersState {
  return { dentistIds: [], statuses: [], typeIds: [], patientId: null }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(providersApi.listAll).mockResolvedValue([
    { id: 'd1', name: 'Dr. Smith', email: 'smith@example.com', role: 'dentist' },
  ])
  vi.mocked(appointmentTypesApi.list).mockResolvedValue([
    {
      id: 't1',
      name: 'Cleaning',
      default_duration_minutes: 30,
      color: '#0ea5e9',
      is_active: true,
      created_at: '',
      updated_at: '',
    },
  ])
})

describe('CalendarFilters', () => {
  it('fetches dentists and appointment types on mount', async () => {
    mount(CalendarFilters, { props: { modelValue: emptyFilters() } })
    await flushPromises()

    expect(providersApi.listAll).toHaveBeenCalledTimes(1)
    expect(appointmentTypesApi.list).toHaveBeenCalledTimes(1)
  })

  it('does not show the Clear filters button when nothing is filtered', () => {
    const wrapper = mount(CalendarFilters, { props: { modelValue: emptyFilters() } })
    expect(wrapper.text()).not.toContain('Clear filters')
  })

  it('shows Clear filters and resets everything when clicked', async () => {
    const wrapper = mount(CalendarFilters, {
      props: { modelValue: { ...emptyFilters(), dentistIds: ['d1'] } },
    })
    await flushPromises()

    const clearButton = wrapper.findAll('button').find((b) => b.text().includes('Clear filters'))
    expect(clearButton).toBeTruthy()
    await clearButton?.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toEqual(emptyFilters())
  })
})
