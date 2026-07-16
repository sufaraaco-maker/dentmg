import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { workingHoursApi } from './workingHoursApi'
import type { DentistWorkingHour } from '@/types/appointment'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

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
  vi.clearAllMocks()
})

describe('workingHoursApi', () => {
  it('listForDentist() requests the correct sub-resource', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeShift()] })

    const result = await workingHoursApi.listForDentist('dentist-1')

    expect(result).toEqual([makeShift()])
    expect(mockedApi.get).toHaveBeenCalledWith('/dentists/dentist-1/working-hours')
  })

  it('create() posts to the dentist-scoped endpoint', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: makeShift() })

    await workingHoursApi.create('dentist-1', { day_of_week: 1, start_time: '09:00', end_time: '17:00' })

    expect(mockedApi.post).toHaveBeenCalledWith('/dentists/dentist-1/working-hours', {
      day_of_week: 1,
      start_time: '09:00',
      end_time: '17:00',
    })
  })

  it('remove() deletes the specific shift for that dentist', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await workingHoursApi.remove('dentist-1', 'wh-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/dentists/dentist-1/working-hours/wh-1')
  })
})
