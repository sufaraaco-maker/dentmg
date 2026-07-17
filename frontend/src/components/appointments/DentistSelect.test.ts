import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DentistSelect from './DentistSelect.vue'
import { providersApi } from '@/services/appointments'

vi.mock('@/services/appointments', () => ({
  providersApi: { listAll: vi.fn() },
}))

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(providersApi.listAll).mockResolvedValue([
    { id: 'd1', name: 'Dr. Smith', email: 'smith@example.com', role: 'dentist' },
  ])
})

describe('DentistSelect', () => {
  it('fetches providers on mount', async () => {
    mount(DentistSelect, { props: { modelValue: null } })
    await flushPromises()

    expect(providersApi.listAll).toHaveBeenCalledTimes(1)
  })

  it('emits update:modelValue when a dentist is picked', async () => {
    const wrapper = mount(DentistSelect, { props: { modelValue: null } })
    await flushPromises()

    await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 'd1')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBe('d1')
  })
})
