import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import BillingSummaryCard from './BillingSummaryCard.vue'
import { billingSummaryApi } from '@/services/billing'
import type { BillingSummary } from '@/types/billing'

vi.mock('@/services/billing', () => ({
  billingSummaryApi: { get: vi.fn() },
}))

const mockedApi = vi.mocked(billingSummaryApi)

function makeSummary(overrides: Partial<BillingSummary> = {}): BillingSummary {
  return {
    total_invoiced: '500.00',
    total_paid: '300.00',
    invoice_count: 2,
    last_payment_date: '2026-08-01',
    outstanding_balance: '200.00',
    status: 'partial',
    currency_code: 'USD',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

async function mountCard(patientId = 'patient-1') {
  const wrapper = mount(BillingSummaryCard, { props: { patientId } })
  await flushPromises()
  return wrapper
}

describe('BillingSummaryCard — data loading', () => {
  it("fetches this patient's billing summary on mount", async () => {
    mockedApi.get.mockResolvedValueOnce(makeSummary())
    await mountCard('patient-1')

    expect(mockedApi.get).toHaveBeenCalledWith('patient-1')
  })

  it('re-fetches when patientId changes', async () => {
    mockedApi.get.mockResolvedValue(makeSummary())
    const wrapper = await mountCard('patient-1')

    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedApi.get).toHaveBeenCalledWith('patient-2')
  })
})

describe('BillingSummaryCard — rendering', () => {
  it('renders the outstanding balance and summary stats', async () => {
    mockedApi.get.mockResolvedValueOnce(makeSummary())
    const wrapper = await mountCard()

    expect(wrapper.text()).toContain('200.00')
    expect(wrapper.text()).toContain('500.00')
    expect(wrapper.text()).toContain('300.00')
    expect(wrapper.text()).toContain('2')
    expect(wrapper.text()).toContain('Partial')
  })

  it('renders "Never" for a null last payment date', async () => {
    mockedApi.get.mockResolvedValueOnce(makeSummary({ last_payment_date: null }))
    const wrapper = await mountCard()

    expect(wrapper.text()).toContain('Never')
  })

  it('renders "No Activity" status for a patient with no invoices', async () => {
    mockedApi.get.mockResolvedValueOnce(
      makeSummary({
        status: 'no_activity',
        invoice_count: 0,
        total_invoiced: '0.00',
        outstanding_balance: '0.00',
      }),
    )
    const wrapper = await mountCard()

    expect(wrapper.text()).toContain('No Activity')
  })
})
