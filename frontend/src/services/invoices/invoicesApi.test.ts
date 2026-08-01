import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { invoicesApi } from './invoicesApi'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

/** Only covers `listAll` (frontend-ux-redesign design doc §5.1/§11) — every other method on
 *  `invoicesApi` predates this pass and has no existing test file to extend. */
describe('invoicesApi.listAll', () => {
  it('requests the clinic-wide, paginated endpoint with the given params', async () => {
    const page = {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    }
    mockedApi.get.mockResolvedValue({ data: page })

    const result = await invoicesApi.listAll({ page: 2, search: 'INV-1', status: 'issued' })

    expect(mockedApi.get).toHaveBeenCalledWith('/invoices', {
      params: { page: 2, search: 'INV-1', status: 'issued' },
    })
    expect(result).toEqual(page)
  })

  it('defaults to no params when called with none', async () => {
    mockedApi.get.mockResolvedValue({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
    })

    await invoicesApi.listAll()

    expect(mockedApi.get).toHaveBeenCalledWith('/invoices', { params: {} })
  })
})
