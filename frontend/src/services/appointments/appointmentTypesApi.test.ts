import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { appointmentTypesApi } from './appointmentTypesApi'
import type { AppointmentType } from '@/types/appointment'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

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
  vi.clearAllMocks()
})

describe('appointmentTypesApi', () => {
  it('list() returns the plain array (not paginated)', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeType()] })

    const result = await appointmentTypesApi.list()

    expect(result).toEqual([makeType()])
    expect(mockedApi.get).toHaveBeenCalledWith('/appointment-types')
  })

  it('create() posts the payload and returns the created type', async () => {
    const created = makeType({ id: 'type-2', name: 'Cleaning' })
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await appointmentTypesApi.create({
      name: 'Cleaning',
      default_duration_minutes: 45,
      color: '#10B981',
    })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/appointment-types', {
      name: 'Cleaning',
      default_duration_minutes: 45,
      color: '#10B981',
    })
  })

  it('update() puts to the correct id', async () => {
    const updated = makeType({ name: 'Consultation (Updated)' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await appointmentTypesApi.update('type-1', { name: 'Consultation (Updated)' })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith('/appointment-types/type-1', {
      name: 'Consultation (Updated)',
    })
  })

  it('remove() deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await appointmentTypesApi.remove('type-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/appointment-types/type-1')
  })
})
