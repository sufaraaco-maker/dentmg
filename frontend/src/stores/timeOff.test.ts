import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { timeOffApi } from '@/services/appointments'
import { useTimeOffStore } from './timeOff'
import type { DentistTimeOff } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  timeOffApi: {
    listForDentist: vi.fn(),
    create: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(timeOffApi)

function makeEntry(overrides: Partial<DentistTimeOff> = {}): DentistTimeOff {
  return {
    id: 'to-1',
    user_id: 'dentist-1',
    start_at: '2026-08-01T00:00:00+00:00',
    end_at: '2026-08-05T00:00:00+00:00',
    reason: 'Vacation',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useTimeOffStore', () => {
  it('caches per dentist and does not refetch an already-cached dentist', async () => {
    mockedApi.listForDentist.mockResolvedValue([makeEntry()])
    const store = useTimeOffStore()

    await store.fetchForDentist('dentist-1')
    await store.fetchForDentist('dentist-1')

    expect(mockedApi.listForDentist).toHaveBeenCalledTimes(1)
  })

  it('create appends and remove deletes for that dentist only', async () => {
    mockedApi.listForDentist.mockResolvedValueOnce([makeEntry()])
    mockedApi.create.mockResolvedValueOnce(makeEntry({ id: 'to-2', reason: 'Conference' }))
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useTimeOffStore()
    await store.fetchForDentist('dentist-1')
    await store.create('dentist-1', {
      start_at: '2026-09-01T00:00:00+00:00',
      end_at: '2026-09-02T00:00:00+00:00',
    })
    expect(store.byDentist.get('dentist-1')).toHaveLength(2)

    await store.remove('dentist-1', 'to-1')
    expect(store.byDentist.get('dentist-1')?.map((e) => e.id)).toEqual(['to-2'])
  })
})
