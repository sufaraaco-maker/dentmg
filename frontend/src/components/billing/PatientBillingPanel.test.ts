import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import PatientBillingPanel from './PatientBillingPanel.vue'
import BillingSummaryCard from './BillingSummaryCard.vue'
import PatientInvoicesPanel from '@/components/invoices/PatientInvoicesPanel.vue'
import PatientPaymentsPanel from '@/components/payments/PatientPaymentsPanel.vue'

beforeEach(() => {
  setActivePinia(createPinia())
})

function mountPanel() {
  return mount(PatientBillingPanel, {
    props: { patientId: 'patient-1' },
    global: {
      stubs: { BillingSummaryCard: true, PatientInvoicesPanel: true, PatientPaymentsPanel: true },
    },
  })
}

describe('PatientBillingPanel — shell', () => {
  it('always renders the BillingSummaryCard hero regardless of the selected section', () => {
    const wrapper = mountPanel()

    expect(wrapper.findComponent(BillingSummaryCard).props('patientId')).toBe('patient-1')
  })

  it('defaults to the Invoices section', () => {
    const wrapper = mountPanel()

    expect(wrapper.findComponent(PatientInvoicesPanel).exists()).toBe(true)
    expect(wrapper.findComponent(PatientPaymentsPanel).exists()).toBe(false)
  })

  it('switches to the Payments section', async () => {
    const wrapper = mountPanel()
    const switcher = wrapper.findComponent({ name: 'SelectButton' })

    await switcher.vm.$emit('update:modelValue', 'payments')

    expect(wrapper.findComponent(PatientPaymentsPanel).props('patientId')).toBe('patient-1')
    expect(wrapper.findComponent(PatientInvoicesPanel).exists()).toBe(false)
  })

  it('switches to the Payment History placeholder — no fetch, honest "coming soon" state', async () => {
    const wrapper = mountPanel()
    const switcher = wrapper.findComponent({ name: 'SelectButton' })

    await switcher.vm.$emit('update:modelValue', 'paymentHistory')

    expect(wrapper.findComponent(PatientInvoicesPanel).exists()).toBe(false)
    expect(wrapper.findComponent(PatientPaymentsPanel).exists()).toBe(false)
    expect(wrapper.text()).toContain('Payment History')
    expect(wrapper.text()).toContain('Coming soon')
  })
})
