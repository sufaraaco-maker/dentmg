import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { patientsApi } from './patientsApi'
import type { Patient, PatientAuditLog } from '@/types/patient'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makePatient(overrides: Partial<Patient> = {}): Patient {
  return {
    id: 'patient-1',
    patient_code: 'P-00001',
    first_name: 'Ahmed',
    last_name: 'Ali',
    full_name: 'Ahmed Ali',
    date_of_birth: '1990-01-01',
    gender: 'male',
    phone: '0100000000',
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
    created_at: '2026-08-01T09:00:00+00:00',
    updated_at: '2026-08-01T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('patientsApi.list', () => {
  it('requests the paginated, searchable endpoint with the given params', async () => {
    const page = {
      data: [makePatient()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    }
    mockedApi.get.mockResolvedValueOnce({ data: page })

    const result = await patientsApi.list({ search: 'Ahmed', page: 2 })

    expect(mockedApi.get).toHaveBeenCalledWith('/patients', { params: { search: 'Ahmed', page: 2 } })
    expect(result).toEqual(page)
  })

  it('omits an empty search string rather than sending it literally', async () => {
    mockedApi.get.mockResolvedValueOnce({
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
    })

    await patientsApi.list({ search: '' })

    expect(mockedApi.get).toHaveBeenCalledWith('/patients', {
      params: { search: undefined, page: undefined },
    })
  })
})

describe('patientsApi.get', () => {
  it('fetches the single-resource route', async () => {
    const patient = makePatient()
    mockedApi.get.mockResolvedValueOnce({ data: patient })

    const result = await patientsApi.get('patient-1')

    expect(result).toEqual(patient)
    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1')
  })
})

describe('patientsApi.create', () => {
  it('posts to the collection route', async () => {
    const created = makePatient()
    mockedApi.post.mockResolvedValueOnce({ data: created })

    const result = await patientsApi.create({
      first_name: 'Ahmed',
      last_name: 'Ali',
      date_of_birth: '1990-01-01',
      gender: 'male',
      phone: '0100000000',
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
    })

    expect(result).toEqual(created)
    expect(mockedApi.post).toHaveBeenCalledWith('/patients', expect.objectContaining({ first_name: 'Ahmed' }))
  })
})

describe('patientsApi.update', () => {
  it('puts to the resource route', async () => {
    const updated = makePatient({ first_name: 'Updated' })
    mockedApi.put.mockResolvedValueOnce({ data: updated })

    const result = await patientsApi.update('patient-1', {
      first_name: 'Updated',
      last_name: 'Ali',
      date_of_birth: '1990-01-01',
      gender: 'male',
      phone: '0100000000',
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
    })

    expect(result).toEqual(updated)
    expect(mockedApi.put).toHaveBeenCalledWith(
      '/patients/patient-1',
      expect.objectContaining({ first_name: 'Updated' }),
    )
  })
})

describe('patientsApi.remove', () => {
  it('deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await patientsApi.remove('patient-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/patients/patient-1')
  })
})

describe('patientsApi.auditLogs', () => {
  it('fetches and unwraps the paginated audit-log response', async () => {
    const logs: PatientAuditLog[] = [
      { id: 'log-1', action: 'created', changes: null, user: null, created_at: '2026-08-01T09:00:00+00:00' },
    ]
    mockedApi.get.mockResolvedValueOnce({ data: { data: logs } })

    const result = await patientsApi.auditLogs('patient-1')

    expect(result).toEqual(logs)
    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/audit-logs')
  })
})
