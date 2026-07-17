import { mount, flushPromises } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PatientSearchSelect from './PatientSearchSelect.vue'
import { api } from '@/lib/api'
import type { Patient } from '@/types/patient'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const JANE: Patient = {
  id: 'p1',
  patient_code: 'PT-0001',
  first_name: 'Jane',
  last_name: 'Doe',
  full_name: 'Jane Doe',
  date_of_birth: '1990-05-10',
  gender: 'female',
  phone: '555-1234',
  email: null,
  address: null,
  national_id: null,
  emergency_contact_name: null,
  emergency_contact_phone: null,
  blood_type: null,
  allergies: null,
  medical_history: null,
  insurance_provider: null,
  insurance_number: null,
  notes: null,
  created_at: '',
  updated_at: '',
}

describe('PatientSearchSelect', () => {
  it('searches after debounce and lets the user pick a result', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { data: [JANE] } })
    vi.useFakeTimers()

    const wrapper = mount(PatientSearchSelect, { props: { modelValue: null } })
    await wrapper.find('input').setValue('Jane')

    vi.advanceTimersByTime(300)
    vi.useRealTimers()
    await flushPromises()

    expect(api.get).toHaveBeenCalledWith('/patients', { params: { search: 'Jane' } })

    const item = wrapper.find('li')
    expect(item.text()).toContain('Jane Doe')

    await item.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBe('p1')
    expect(wrapper.emitted('patient-selected')?.[0][0]).toEqual(JANE)
  })

  it('shows a read-only summary and hides the change button when disabled', () => {
    const wrapper = mount(PatientSearchSelect, {
      props: { modelValue: 'p1', selectedPatient: JANE, disabled: true },
    })

    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.findAll('button').some((b) => b.text().includes('Change'))).toBe(false)
  })

  it('clears the selection when the change button is clicked', async () => {
    const wrapper = mount(PatientSearchSelect, {
      props: { modelValue: 'p1', selectedPatient: JANE },
    })

    const changeButton = wrapper.findAll('button').find((b) => b.text().includes('Change'))
    await changeButton?.trigger('click')

    expect(wrapper.emitted('update:modelValue')?.[0][0]).toBeNull()
  })
})
