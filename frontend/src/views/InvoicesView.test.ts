import { mount, flushPromises } from '@vue/test-utils'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import InvoicesView from './InvoicesView.vue'
import { invoicesApi } from '@/services/invoices'
import type { Invoice } from '@/types/invoice'

vi.mock('@/services/invoices', () => ({
  invoicesApi: { listAll: vi.fn() },
}))

const mockedApi = vi.mocked(invoicesApi)

function makeInvoice(overrides: Partial<Invoice> = {}): Invoice {
  return {
    id: 'inv-1',
    patient_id: 'p1',
    created_by_id: 'u1',
    sequence_number: 1,
    invoice_number: 'INV-000001',
    currency_code: 'USD',
    status: 'issued',
    notes: null,
    issue_date: '2026-07-01',
    due_date: null,
    issued_at: '2026-07-01T00:00:00+00:00',
    voided_at: null,
    created_at: '2026-07-01T00:00:00+00:00',
    updated_at: '2026-07-01T00:00:00+00:00',
    total: '150.00',
    patient: { id: 'p1', first_name: 'Jane', last_name: 'Doe' },
    ...overrides,
  }
}

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/invoices', name: 'invoices', component: InvoicesView },
      {
        path: '/patients/:id/invoices/:invoiceId',
        name: 'invoice-detail',
        component: { template: '<div />' },
      },
    ],
  })
}

async function mountView() {
  const router = makeRouter()
  await router.push('/invoices')
  await router.isReady()
  const wrapper = mount(InvoicesView, { global: { plugins: [router] } })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('InvoicesView', () => {
  it('fetches and renders the clinic-wide invoice list, including the patient name', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [makeInvoice()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })

    const { wrapper } = await mountView()

    expect(mockedApi.listAll).toHaveBeenCalled()
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('INV-000001')
  })

  it('re-fetches with a status filter when the status Select changes', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })
    const { wrapper } = await mountView()
    mockedApi.listAll.mockClear()

    // Directly exercise the component's own status ref via its Select v-model rather than
    // simulating a full PrimeVue Select click sequence (its overlay is teleported/portal-based
    // and brittle to drive in jsdom) — this asserts the same re-fetch wiring `watch(status, ...)`
    // provides.
    await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'issued')
    await flushPromises()

    expect(mockedApi.listAll).toHaveBeenCalledWith(expect.objectContaining({ status: 'issued' }))
  })

  it('navigates to the patient-scoped invoice detail route on row click', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [makeInvoice()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    const { wrapper, router } = await mountView()

    await wrapper.get('tbody tr').trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('invoice-detail')
    expect(router.currentRoute.value.params).toEqual({ id: 'p1', invoiceId: 'inv-1' })
  })

  it('shows the empty state when no invoices match', async () => {
    mockedApi.listAll.mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('No invoices found')
  })
})
