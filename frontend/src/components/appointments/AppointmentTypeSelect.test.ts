import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentTypeSelect from './AppointmentTypeSelect.vue'
import { appointmentTypesApi } from '@/services/appointments'
import type { AppointmentType } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentTypesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

const CLEANING: AppointmentType = {
  id: 't1',
  name: 'Cleaning',
  default_duration_minutes: 30,
  color: '#0ea5e9',
  is_active: true,
  created_at: '',
  updated_at: '',
}

const RETIRED: AppointmentType = {
  id: 't2',
  name: 'Retired Type',
  default_duration_minutes: 15,
  color: '#94a3b8',
  is_active: false,
  created_at: '',
  updated_at: '',
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.mocked(appointmentTypesApi.list).mockResolvedValue([CLEANING, RETIRED])
})

describe('AppointmentTypeSelect', () => {
  it('emits update:modelValue and type-selected with the full type', async () => {
    const wrapper = mount(AppointmentTypeSelect, { props: { modelValue: null } })
    await flushPromises()

    await wrapper.findComponent({ name: 'Select' }).vm.$emit('update:modelValue', 't1')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBe('t1')
    expect(wrapper.emitted('type-selected')?.[0][0]).toEqual(CLEANING)
  })

  it('still resolves an inactive type when it is the current selection', async () => {
    const wrapper = mount(AppointmentTypeSelect, { props: { modelValue: 't2' } })
    await flushPromises()

    const select = wrapper.findComponent({ name: 'Select' })
    const options = select.props('options') as AppointmentType[]

    expect(options.some((option) => option.id === 't2')).toBe(true)
  })

  it('excludes inactive types when not currently selected', async () => {
    const wrapper = mount(AppointmentTypeSelect, { props: { modelValue: null } })
    await flushPromises()

    const select = wrapper.findComponent({ name: 'Select' })
    const options = select.props('options') as AppointmentType[]

    expect(options.some((option) => option.id === 't2')).toBe(false)
  })
})
