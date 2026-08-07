import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { patientsApi } from '@/services/patients/patientsApi'
import { usePatientsStore } from './patients'
import type { Patient, PatientAuditLog } from '@/types/patient'

vi.mock('@/services/patients/patientsApi', () => ({
  patientsApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    remove: vi.fn(),
    auditLogs: vi.fn(),
  },
}))

const mockedApi = vi.mocked(patientsApi)

function makePatient(overrides: Partial<Patient> = {}): Patient {
  return {
    id: overrides.id ?? 'patient-1',
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

function makeAuditLog(overrides: Partial<PatientAuditLog> = {}): PatientAuditLog {
  return {
    id: 'log-1',
    action: 'created',
    changes: null,
    user: { id: 'user-1', name: 'Admin' },
    created_at: '2026-08-01T09:00:00+00:00',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('usePatientsStore.fetchList', () => {
  it('populates listItems and listMeta from the paginated response, and warms the cache', async () => {
    mockedApi.list.mockResolvedValueOnce({
      data: [makePatient()],
      meta: { current_page: 1, last_page: 2, per_page: 15, total: 16 },
    })
    const store = usePatientsStore()

    await store.fetchList({ search: 'Ahmed', page: 1 })

    expect(store.listItems).toHaveLength(1)
    expect(store.listMeta).toEqual({ currentPage: 1, lastPage: 2, perPage: 15, total: 16 })
    expect(store.cache.get('patient-1')?.full_name).toBe('Ahmed Ali')
    expect(mockedApi.list).toHaveBeenCalledWith({ search: 'Ahmed', page: 1 })
  })

  it('sets a translation-key error on failure', async () => {
    mockedApi.list.mockRejectedValueOnce(new Error('network error'))
    const store = usePatientsStore()

    await store.fetchList()

    expect(store.listError).toBe('patients.loadError')
    expect(store.listLoading).toBe(false)
  })
})

describe('usePatientsStore.fetchOne', () => {
  it('always fetches fresh and upserts into the cache', async () => {
    mockedApi.get.mockResolvedValueOnce(makePatient())
    const store = usePatientsStore()

    const patient = await store.fetchOne('patient-1')

    expect(patient.id).toBe('patient-1')
    expect(store.cache.get('patient-1')).toEqual(patient)
  })
})

describe('usePatientsStore mutations', () => {
  it('create upserts the created patient', async () => {
    mockedApi.create.mockResolvedValueOnce(makePatient())
    const store = usePatientsStore()

    const created = await store.create({
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

    expect(created.id).toBe('patient-1')
    expect(store.cache.get('patient-1')).toEqual(created)
  })

  it('update upserts the response directly', async () => {
    mockedApi.update.mockResolvedValueOnce(makePatient({ first_name: 'Updated' }))
    const store = usePatientsStore()

    const updated = await store.update('patient-1', {
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

    expect(updated.first_name).toBe('Updated')
    expect(store.cache.get('patient-1')?.first_name).toBe('Updated')
  })

  it('remove deletes the patient from the cache and its audit logs', async () => {
    mockedApi.get.mockResolvedValueOnce(makePatient())
    mockedApi.auditLogs.mockResolvedValueOnce([makeAuditLog()])
    mockedApi.remove.mockResolvedValueOnce(undefined)
    const store = usePatientsStore()
    await store.fetchOne('patient-1')
    await store.fetchAuditLogs('patient-1')

    await store.remove('patient-1')

    expect(store.cache.has('patient-1')).toBe(false)
    expect(store.auditLogsForPatient('patient-1')).toHaveLength(0)
  })
})

describe('usePatientsStore.fetchAuditLogs', () => {
  it('fetches and caches audit logs per patient', async () => {
    mockedApi.auditLogs.mockResolvedValueOnce([makeAuditLog()])
    const store = usePatientsStore()

    await store.fetchAuditLogs('patient-1')

    expect(store.auditLogsForPatient('patient-1')).toHaveLength(1)
    expect(store.auditLoading).toBe(false)
  })

  it('auditLogsForPatient returns an empty array before any fetch', () => {
    const store = usePatientsStore()

    expect(store.auditLogsForPatient('patient-1')).toEqual([])
  })
})

describe('usePatientsStore.$reset', () => {
  it('clears the cache, list state, and audit logs', async () => {
    mockedApi.list.mockResolvedValueOnce({
      data: [makePatient()],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    mockedApi.auditLogs.mockResolvedValueOnce([makeAuditLog()])
    const store = usePatientsStore()
    await store.fetchList()
    await store.fetchAuditLogs('patient-1')

    store.$reset()

    expect(store.cache.size).toBe(0)
    expect(store.listItems).toHaveLength(0)
    expect(store.listMeta).toEqual({ currentPage: 1, lastPage: 1, perPage: 15, total: 0 })
    expect(store.auditLogsForPatient('patient-1')).toEqual([])
  })
})
