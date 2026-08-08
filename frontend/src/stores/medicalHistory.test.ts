import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { medicalHistoryApi } from '@/services/medicalHistory'
import { useMedicalHistoryStore } from './medicalHistory'
import type { PatientAllergy, PatientMedicalCondition, PatientMedication } from '@/types/medicalHistory'

vi.mock('@/services/medicalHistory', () => ({
  medicalHistoryApi: {
    listAllergies: vi.fn(),
    createAllergy: vi.fn(),
    updateAllergy: vi.fn(),
    removeAllergy: vi.fn(),
    listConditions: vi.fn(),
    createCondition: vi.fn(),
    updateCondition: vi.fn(),
    removeCondition: vi.fn(),
    listMedications: vi.fn(),
    createMedication: vi.fn(),
    updateMedication: vi.fn(),
    removeMedication: vi.fn(),
  },
}))

const mockedApi = vi.mocked(medicalHistoryApi)

function makeAllergy(overrides: Partial<PatientAllergy> = {}): PatientAllergy {
  return {
    id: overrides.id ?? 'allergy-1',
    patient_id: 'patient-1',
    allergen: 'Penicillin',
    severity: 'severe',
    reaction: null,
    notes: null,
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function makeCondition(overrides: Partial<PatientMedicalCondition> = {}): PatientMedicalCondition {
  return {
    id: overrides.id ?? 'condition-1',
    patient_id: 'patient-1',
    condition_name: 'Asthma',
    status: 'active',
    diagnosed_date: null,
    notes: null,
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function makeMedication(overrides: Partial<PatientMedication> = {}): PatientMedication {
  return {
    id: overrides.id ?? 'medication-1',
    patient_id: 'patient-1',
    medication_name: 'Metformin',
    dosage: null,
    frequency: null,
    is_current: true,
    start_date: null,
    end_date: null,
    notes: null,
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function page<T>(data: T[]) {
  return { data, meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length } }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('useMedicalHistoryStore.fetchForPatient', () => {
  it('fetches all three sections in parallel', async () => {
    mockedApi.listAllergies.mockResolvedValueOnce(page([makeAllergy()]))
    mockedApi.listConditions.mockResolvedValueOnce(page([makeCondition()]))
    mockedApi.listMedications.mockResolvedValueOnce(page([makeMedication()]))

    const store = useMedicalHistoryStore()
    await store.fetchForPatient('patient-1')

    expect(store.allergies).toHaveLength(1)
    expect(store.conditions).toHaveLength(1)
    expect(store.medications).toHaveLength(1)
    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(1)
    expect(mockedApi.listConditions).toHaveBeenCalledTimes(1)
    expect(mockedApi.listMedications).toHaveBeenCalledTimes(1)
  })

  it('skips the network calls on a second fetch for the same patient', async () => {
    mockedApi.listAllergies.mockResolvedValue(page([]))
    mockedApi.listConditions.mockResolvedValue(page([]))
    mockedApi.listMedications.mockResolvedValue(page([]))

    const store = useMedicalHistoryStore()
    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-1')

    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(1)
  })

  it('refetches when switching to a different patient', async () => {
    mockedApi.listAllergies.mockResolvedValue(page([]))
    mockedApi.listConditions.mockResolvedValue(page([]))
    mockedApi.listMedications.mockResolvedValue(page([]))

    const store = useMedicalHistoryStore()
    await store.fetchForPatient('patient-1')
    await store.fetchForPatient('patient-2')

    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(2)
  })

  it('sets a translation-key error when a section fails to load', async () => {
    mockedApi.listAllergies.mockRejectedValueOnce(new Error('network error'))
    mockedApi.listConditions.mockResolvedValueOnce(page([]))
    mockedApi.listMedications.mockResolvedValueOnce(page([]))

    const store = useMedicalHistoryStore()
    await store.fetchForPatient('patient-1')

    expect(store.error).toBe('medicalHistory.loadError')
    expect(store.allergiesLoading).toBe(false)
  })
})

describe('useMedicalHistoryStore allergies mutations', () => {
  it('createAllergy refreshes page 1 so the new row is immediately visible', async () => {
    mockedApi.listAllergies.mockResolvedValueOnce(page([])).mockResolvedValueOnce(page([makeAllergy()]))
    mockedApi.createAllergy.mockResolvedValueOnce(makeAllergy())

    const store = useMedicalHistoryStore()
    await store.fetchAllergies('patient-1')
    await store.createAllergy('patient-1', { allergen: 'Penicillin' })

    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(2)
    expect(store.allergies).toHaveLength(1)
  })

  it('updateAllergy replaces the cached row in place without a re-fetch', async () => {
    mockedApi.listAllergies.mockResolvedValueOnce(page([makeAllergy()]))
    mockedApi.updateAllergy.mockResolvedValueOnce(makeAllergy({ allergen: 'Latex' }))

    const store = useMedicalHistoryStore()
    await store.fetchAllergies('patient-1')
    await store.updateAllergy('allergy-1', { allergen: 'Latex' })

    expect(store.allergies[0].allergen).toBe('Latex')
    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(1)
  })

  it('removeAllergy filters the cached row out without a re-fetch', async () => {
    mockedApi.listAllergies.mockResolvedValueOnce(page([makeAllergy()]))
    mockedApi.removeAllergy.mockResolvedValueOnce(undefined)

    const store = useMedicalHistoryStore()
    await store.fetchAllergies('patient-1')
    await store.removeAllergy('allergy-1')

    expect(store.allergies).toHaveLength(0)
    expect(store.allergiesMeta.total).toBe(0)
    expect(mockedApi.listAllergies).toHaveBeenCalledTimes(1)
  })
})

describe('useMedicalHistoryStore conditions mutations', () => {
  it('createCondition refreshes page 1', async () => {
    mockedApi.listConditions.mockResolvedValueOnce(page([])).mockResolvedValueOnce(page([makeCondition()]))
    mockedApi.createCondition.mockResolvedValueOnce(makeCondition())

    const store = useMedicalHistoryStore()
    await store.fetchConditions('patient-1')
    await store.createCondition('patient-1', { condition_name: 'Asthma' })

    expect(store.conditions).toHaveLength(1)
  })

  it('updateCondition replaces the cached row in place', async () => {
    mockedApi.listConditions.mockResolvedValueOnce(page([makeCondition({ status: 'active' })]))
    mockedApi.updateCondition.mockResolvedValueOnce(makeCondition({ status: 'resolved' }))

    const store = useMedicalHistoryStore()
    await store.fetchConditions('patient-1')
    await store.updateCondition('condition-1', { status: 'resolved' })

    expect(store.conditions[0].status).toBe('resolved')
  })

  it('removeCondition filters the cached row out without a re-fetch', async () => {
    mockedApi.listConditions.mockResolvedValueOnce(page([makeCondition()]))
    mockedApi.removeCondition.mockResolvedValueOnce(undefined)

    const store = useMedicalHistoryStore()
    await store.fetchConditions('patient-1')
    await store.removeCondition('condition-1')

    expect(store.conditions).toHaveLength(0)
    expect(mockedApi.listConditions).toHaveBeenCalledTimes(1)
  })
})

describe('useMedicalHistoryStore medications mutations', () => {
  it('createMedication refreshes page 1', async () => {
    mockedApi.listMedications.mockResolvedValueOnce(page([])).mockResolvedValueOnce(page([makeMedication()]))
    mockedApi.createMedication.mockResolvedValueOnce(makeMedication())

    const store = useMedicalHistoryStore()
    await store.fetchMedications('patient-1')
    await store.createMedication('patient-1', { medication_name: 'Metformin' })

    expect(store.medications).toHaveLength(1)
  })

  it('updateMedication replaces the cached row in place', async () => {
    mockedApi.listMedications.mockResolvedValueOnce(page([makeMedication({ is_current: true })]))
    mockedApi.updateMedication.mockResolvedValueOnce(makeMedication({ is_current: false }))

    const store = useMedicalHistoryStore()
    await store.fetchMedications('patient-1')
    await store.updateMedication('medication-1', { is_current: false })

    expect(store.medications[0].is_current).toBe(false)
  })

  it('removeMedication filters the cached row out without a re-fetch', async () => {
    mockedApi.listMedications.mockResolvedValueOnce(page([makeMedication()]))
    mockedApi.removeMedication.mockResolvedValueOnce(undefined)

    const store = useMedicalHistoryStore()
    await store.fetchMedications('patient-1')
    await store.removeMedication('medication-1')

    expect(store.medications).toHaveLength(0)
  })
})
