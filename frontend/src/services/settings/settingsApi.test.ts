import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import {
  getBillingSettings,
  getClinicSettings,
  getProfile,
  updateBillingSettings,
  updateClinicSettings,
  updateProfile,
  updateProfilePassword,
} from './settingsApi'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), put: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('clinic settings', () => {
  it('fetches the singleton clinic settings row', async () => {
    mockedApi.get.mockResolvedValue({ data: { id: '1', name: 'Bright Smile', updated_at: '2026-07-30T00:00:00Z' } })

    await getClinicSettings()

    expect(mockedApi.get).toHaveBeenCalledWith('/clinic-settings')
  })

  it('updates clinic settings via PUT', async () => {
    mockedApi.put.mockResolvedValue({ data: { id: '1', name: 'Bright Smile', updated_at: '2026-07-30T00:00:00Z' } })

    await updateClinicSettings({ name: 'Bright Smile', phone: '555-0100', address: null, email: null })

    expect(mockedApi.put).toHaveBeenCalledWith('/clinic-settings', {
      name: 'Bright Smile',
      phone: '555-0100',
      address: null,
      email: null,
    })
  })
})

describe('billing settings', () => {
  it('fetches the singleton billing settings row', async () => {
    mockedApi.get.mockResolvedValue({
      data: { id: '1', currency_code: 'USD', tax_rate: null, invoice_number_prefix: 'INV', next_invoice_sequence: 1, updated_at: '2026-07-30T00:00:00Z' },
    })

    await getBillingSettings()

    expect(mockedApi.get).toHaveBeenCalledWith('/billing-settings')
  })

  it('updates billing settings via PUT, never sending next_invoice_sequence', async () => {
    mockedApi.put.mockResolvedValue({
      data: { id: '1', currency_code: 'EUR', tax_rate: 5, invoice_number_prefix: 'INV', next_invoice_sequence: 1, updated_at: '2026-07-30T00:00:00Z' },
    })

    await updateBillingSettings({ currency_code: 'EUR', tax_rate: 5, invoice_number_prefix: 'INV' })

    expect(mockedApi.put).toHaveBeenCalledWith('/billing-settings', {
      currency_code: 'EUR',
      tax_rate: 5,
      invoice_number_prefix: 'INV',
    })
  })
})

describe('profile', () => {
  it('fetches the current user profile', async () => {
    mockedApi.get.mockResolvedValue({ data: { id: '1', name: 'Ada', email: 'ada@example.com', role: 'admin' } })

    await getProfile()

    expect(mockedApi.get).toHaveBeenCalledWith('/profile')
  })

  it('updates the profile via PUT', async () => {
    mockedApi.put.mockResolvedValue({ data: { id: '1', name: 'Ada', email: 'ada@example.com', role: 'admin' } })

    await updateProfile({ name: 'Ada' })

    expect(mockedApi.put).toHaveBeenCalledWith('/profile', { name: 'Ada' })
  })

  it('changes the password via PUT /profile/password', async () => {
    mockedApi.put.mockResolvedValue({ data: undefined })

    await updateProfilePassword({
      current_password: 'old-pw',
      password: 'new-pw',
      password_confirmation: 'new-pw',
    })

    expect(mockedApi.put).toHaveBeenCalledWith('/profile/password', {
      current_password: 'old-pw',
      password: 'new-pw',
      password_confirmation: 'new-pw',
    })
  })
})
