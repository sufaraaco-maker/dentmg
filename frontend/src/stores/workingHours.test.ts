import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { workingHoursApi } from '@/services/appointments'
import { useWorkingHoursStore } from './workingHours'
import type { DentistWorkingHour } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  workingHoursApi: {
    listForDentist: vi.fn(),
    create: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(workingHoursApi)

function makeShift(overrides: Partial<DentistWorkingHour> = {}): DentistWorkingHour {
  return {
    id: 'wh-1',
    user_id: 'dentist-1',
    day_of_week: 1,
    start_time: '09:00',
    end_time: '17:00',
    is_active: true,
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useWorkingHoursStore', () => {
  it('caches per dentist and does not refetch an already-cached dentist', async () => {
    mockedApi.listForDentist.mockResolvedValue([makeShift()])
    const store = useWorkingHoursStore()

    await store.fetchForDentist('dentist-1')
    await store.fetchForDentist('dentist-1')

    expect(mockedApi.listForDentist).toHaveBeenCalledTimes(1)
    expect(store.byDentist.get('dentist-1')).toHaveLength(1)
  })

  it('fetches independently per dentist', async () => {
    mockedApi.listForDentist
      .mockResolvedValueOnce([makeShift({ user_id: 'dentist-1' })])
      .mockResolvedValueOnce([makeShift({ id: 'wh-2', user_id: 'dentist-2' })])

    const store = useWorkingHoursStore()
    await store.fetchForDentist('dentist-1')
    await store.fetchForDentist('dentist-2')

    expect(store.byDentist.get('dentist-1')).toHaveLength(1)
    expect(store.byDentist.get('dentist-2')).toHaveLength(1)
  })

  it('create appends only to that dentist entry', async () => {
    mockedApi.listForDentist.mockResolvedValueOnce([makeShift()])
    mockedApi.create.mockResolvedValueOnce(makeShift({ id: 'wh-2', day_of_week: 2 }))

    const store = useWorkingHoursStore()
    await store.fetchForDentist('dentist-1')
    await store.create('dentist-1', { day_of_week: 2, start_time: '09:00', end_time: '17:00' })

    expect(store.byDentist.get('dentist-1')).toHaveLength(2)
  })

  it('remove deletes only the matching entry for that dentist', async () => {
    mockedApi.listForDentist.mockResolvedValueOnce([makeShift(), makeShift({ id: 'wh-2', day_of_week: 2 })])
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useWorkingHoursStore()
    await store.fetchForDentist('dentist-1')
    await store.remove('dentist-1', 'wh-1')

    expect(store.byDentist.get('dentist-1')).toEqual([makeShift({ id: 'wh-2', day_of_week: 2 })])
  })
})
