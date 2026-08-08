import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { medicalHistoryApi } from './medicalHistoryApi'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

beforeEach(() => {
  vi.clearAllMocks()
})

describe('medicalHistoryApi allergies', () => {
  it('listAllergies gets the patient-nested route with the page param', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    await medicalHistoryApi.listAllergies('patient-1', 2)

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/allergies', { params: { page: 2 } })
  })

  it('createAllergy posts to the patient-nested route', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: { id: 'allergy-1' } })

    await medicalHistoryApi.createAllergy('patient-1', { allergen: 'Penicillin' })

    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/allergies', { allergen: 'Penicillin' })
  })

  it('updateAllergy puts to the entry-level route (not nested under patient)', async () => {
    mockedApi.put.mockResolvedValueOnce({ data: { id: 'allergy-1' } })

    await medicalHistoryApi.updateAllergy('allergy-1', { allergen: 'Latex' })

    expect(mockedApi.put).toHaveBeenCalledWith('/allergies/allergy-1', { allergen: 'Latex' })
  })

  it('removeAllergy deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await medicalHistoryApi.removeAllergy('allergy-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/allergies/allergy-1')
  })
})

describe('medicalHistoryApi medical conditions', () => {
  it('listConditions gets the patient-nested route', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    await medicalHistoryApi.listConditions('patient-1')

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/medical-conditions', {
      params: { page: undefined },
    })
  })

  it('createCondition posts to the patient-nested route', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: { id: 'condition-1' } })

    await medicalHistoryApi.createCondition('patient-1', { condition_name: 'Asthma' })

    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/medical-conditions', {
      condition_name: 'Asthma',
    })
  })

  it('updateCondition puts to the entry-level route', async () => {
    mockedApi.put.mockResolvedValueOnce({ data: { id: 'condition-1' } })

    await medicalHistoryApi.updateCondition('condition-1', { status: 'resolved' })

    expect(mockedApi.put).toHaveBeenCalledWith('/medical-conditions/condition-1', { status: 'resolved' })
  })

  it('removeCondition deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await medicalHistoryApi.removeCondition('condition-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/medical-conditions/condition-1')
  })
})

describe('medicalHistoryApi medications', () => {
  it('listMedications gets the patient-nested route', async () => {
    mockedApi.get.mockResolvedValueOnce({ data: { data: [], meta: {} } })

    await medicalHistoryApi.listMedications('patient-1')

    expect(mockedApi.get).toHaveBeenCalledWith('/patients/patient-1/medications', {
      params: { page: undefined },
    })
  })

  it('createMedication posts to the patient-nested route', async () => {
    mockedApi.post.mockResolvedValueOnce({ data: { id: 'medication-1' } })

    await medicalHistoryApi.createMedication('patient-1', { medication_name: 'Metformin' })

    expect(mockedApi.post).toHaveBeenCalledWith('/patients/patient-1/medications', {
      medication_name: 'Metformin',
    })
  })

  it('updateMedication puts to the entry-level route', async () => {
    mockedApi.put.mockResolvedValueOnce({ data: { id: 'medication-1' } })

    await medicalHistoryApi.updateMedication('medication-1', { is_current: false })

    expect(mockedApi.put).toHaveBeenCalledWith('/medications/medication-1', { is_current: false })
  })

  it('removeMedication deletes the correct id', async () => {
    mockedApi.delete.mockResolvedValueOnce({ data: undefined })

    await medicalHistoryApi.removeMedication('medication-1')

    expect(mockedApi.delete).toHaveBeenCalledWith('/medications/medication-1')
  })
})
