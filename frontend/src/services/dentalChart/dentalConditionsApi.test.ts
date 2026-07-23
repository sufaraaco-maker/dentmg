import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { dentalConditionsApi } from './dentalConditionsApi'
import type { DentalCondition } from '@/types/dentalChart'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeCondition(overrides: Partial<DentalCondition> = {}): DentalCondition {
  return {
    id: 'condition-1',
    name: 'Caries',
    category: 'finding',
    applies_to_surface: true,
    default_color: '#DC2626',
    icon_key: 'caries',
    default_cost: null,
    description: null,
    is_active: true,
    sort_order: 1,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('dentalConditionsApi', () => {
  it('list() returns the plain array (not paginated)', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: [makeCondition()] })

    const result = await dentalConditionsApi.list()

    expect(result).toEqual([makeCondition()])
    expect(mockedApi.get).toHaveBeenCalledWith('/dental-conditions')
  })

  it('create() posts the payload and returns the created condition', async () => {
    const created = makeCondition({ id: 'condition-2', name: 'Composite Filling', category: 'procedure' })
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await dentalConditionsApi.create({
      name: 'Composite Filling',
      category: 'procedure',
      applies_to_surface: true,
      default_color: '#2563EB',
    })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/dental-conditions', {
      name: 'Composite Filling',
      category: 'procedure',
      applies_to_surface: true,
      default_color: '#2563EB',
    })
  })

  it('update() puts to the correct id', async () => {
    const updated = makeCondition({ name: 'Caries (Updated)' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await dentalConditionsApi.update('condition-1', { name: 'Caries (Updated)' })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith('/dental-conditions/condition-1', { name: 'Caries (Updated)' })
  })

  it('remove() deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await dentalConditionsApi.remove('condition-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/dental-conditions/condition-1')
  })
})
