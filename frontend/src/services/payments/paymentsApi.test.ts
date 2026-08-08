import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { paymentsApi } from './paymentsApi'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('paymentsApi.list', () => {
  it('requests the patient-scoped, paginated endpoint with the given page', async () => {
    const page = {
      data: [],
      meta: { current_page: 2, last_page: 3, per_page: 15, total: 40 },
    }
    mockedApi.get.mockResolvedValue({ data: page })

    const result = await paymentsApi.list('patient-1', 2)

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/payments', { params: { page: 2 } })
    expect(result).toEqual(page)
  })
})

describe('paymentsApi.listForInvoice', () => {
  it('requests the invoice-scoped, paginated endpoint with the given page', async () => {
    const page = {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 3 },
    }
    mockedApi.get.mockResolvedValue({ data: page })

    const result = await paymentsApi.listForInvoice('invoice-1', 1)

    expect(mockedApi.get).toHaveBeenCalledWith('/invoices/invoice-1/payments', { params: { page: 1 } })
    expect(result).toEqual(page)
  })
})
