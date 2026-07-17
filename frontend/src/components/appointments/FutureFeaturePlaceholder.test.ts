import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FutureFeaturePlaceholder from './FutureFeaturePlaceholder.vue'

describe('FutureFeaturePlaceholder', () => {
  it('renders the resolved title and a coming-soon caption', () => {
    const wrapper = mount(FutureFeaturePlaceholder, {
      props: { icon: 'pi pi-file', titleKey: 'appointments.detail.placeholders.treatmentPlan' },
    })

    expect(wrapper.text()).toContain('Treatment Plan')
    expect(wrapper.text()).toContain('Coming soon')
  })

  it('renders the given icon class', () => {
    const wrapper = mount(FutureFeaturePlaceholder, {
      props: { icon: 'pi pi-file', titleKey: 'appointments.detail.placeholders.invoices' },
    })

    expect(wrapper.find('i.pi-file').exists()).toBe(true)
  })
})
