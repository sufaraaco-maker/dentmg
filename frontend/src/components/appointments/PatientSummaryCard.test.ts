import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PatientSummaryCard from './PatientSummaryCard.vue'
import type { Patient } from '@/types/patient'
import type { AppointmentPatientSummary } from '@/types/appointment'

function makePatient(overrides: Partial<Patient> = {}): Patient {
  return {
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
    ...overrides,
  }
}

describe('PatientSummaryCard', () => {
  it('shows name, code, phone, and age for a full Patient', () => {
    const wrapper = mount(PatientSummaryCard, { props: { patient: makePatient() } })

    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('PT-0001')
    expect(wrapper.text()).toContain('555-1234')
  })

  it('degrades gracefully for the partial AppointmentPatientSummary shape (no phone/age)', () => {
    const summary: AppointmentPatientSummary = { id: 'p1', patient_code: 'PT-0002', full_name: 'John Roe' }
    const wrapper = mount(PatientSummaryCard, { props: { patient: summary } })

    expect(wrapper.text()).toContain('John Roe')
    expect(wrapper.text()).toContain('PT-0002')
    expect(wrapper.text()).not.toContain('·')
  })
})
