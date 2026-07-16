import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { appointmentTypesApi } from '@/services/appointments'
import { useAppointmentTypesStore } from './appointmentTypes'
import type { AppointmentType } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentTypesApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(appointmentTypesApi)

function makeType(overrides: Partial<AppointmentType> = {}): AppointmentType {
  return {
    id: 'type-1',
    name: 'Consultation',
    default_duration_minutes: 30,
    color: '#3B82F6',
    is_active: true,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useAppointmentTypesStore.fetchAll', () => {
  it('fetches once and caches indefinitely', async () => {
    mockedApi.list.mockResolvedValue([makeType()])
    const store = useAppointmentTypesStore()

    await store.fetchAll()
    await store.fetchAll()

    expect(mockedApi.list).toHaveBeenCalledTimes(1)
    expect(store.items).toHaveLength(1)
    expect(store.loaded).toBe(true)
  })

  it('refetches when force is true', async () => {
    mockedApi.list.mockResolvedValue([makeType()])
    const store = useAppointmentTypesStore()

    await store.fetchAll()
    await store.fetchAll(true)

    expect(mockedApi.list).toHaveBeenCalledTimes(2)
  })
})

describe('useAppointmentTypesStore mutations', () => {
  it('create appends the new type to the cached list', async () => {
    mockedApi.list.mockResolvedValueOnce([makeType()])
    mockedApi.create.mockResolvedValueOnce(makeType({ id: 'type-2', name: 'Cleaning' }))

    const store = useAppointmentTypesStore()
    await store.fetchAll()
    await store.create({ name: 'Cleaning', default_duration_minutes: 45, color: '#10B981' })

    expect(store.items.map((t) => t.name)).toEqual(['Consultation', 'Cleaning'])
  })

  it('update replaces the matching item in place', async () => {
    mockedApi.list.mockResolvedValueOnce([makeType()])
    mockedApi.update.mockResolvedValueOnce(makeType({ name: 'Consultation (Updated)' }))

    const store = useAppointmentTypesStore()
    await store.fetchAll()
    await store.update('type-1', { name: 'Consultation (Updated)' })

    expect(store.items[0].name).toBe('Consultation (Updated)')
  })

  it('remove filters the deleted item out of the cached list', async () => {
    mockedApi.list.mockResolvedValueOnce([makeType()])
    mockedApi.remove.mockResolvedValueOnce(undefined)

    const store = useAppointmentTypesStore()
    await store.fetchAll()
    await store.remove('type-1')

    expect(store.items).toHaveLength(0)
  })
})
