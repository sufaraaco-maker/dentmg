import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import MedicalHistoryPanel from './MedicalHistoryPanel.vue'
import AllergyList from './AllergyList.vue'
import AllergyFormDialog from './AllergyFormDialog.vue'
import MedicalConditionFormDialog from './MedicalConditionFormDialog.vue'
import MedicationFormDialog from './MedicationFormDialog.vue'
import { medicalHistoryApi } from '@/services/medicalHistory'
import { useAuthStore } from '@/stores/auth'
import type { PatientAllergy } from '@/types/medicalHistory'
import type { UserRole } from '@/types/user'

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

function emptyPage() {
  return { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
}

function makeAllergy(overrides: Partial<PatientAllergy> = {}): PatientAllergy {
  return {
    id: 'allergy-1',
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

function setRole(role: UserRole) {
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 't@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedApi.listAllergies.mockResolvedValue(emptyPage())
  mockedApi.listConditions.mockResolvedValue(emptyPage())
  mockedApi.listMedications.mockResolvedValue(emptyPage())
})

describe('MedicalHistoryPanel — data loading', () => {
  it("fetches this patient's three sections on mount", async () => {
    setRole('dentist')
    mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(mockedApi.listAllergies).toHaveBeenCalledWith('patient-1', 1)
    expect(mockedApi.listConditions).toHaveBeenCalledWith('patient-1', 1)
    expect(mockedApi.listMedications).toHaveBeenCalledWith('patient-1', 1)
  })

  it('re-fetches when patientId changes', async () => {
    setRole('dentist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedApi.listAllergies).toHaveBeenCalledWith('patient-2', 1)
  })
})

describe('MedicalHistoryPanel — permissions', () => {
  it('shows the Add buttons for a dentist', async () => {
    setRole('dentist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('Add Allergy')
    expect(wrapper.text()).toContain('Add Condition')
    expect(wrapper.text()).toContain('Add Medication')
  })

  it('shows the Add buttons for an admin', async () => {
    setRole('admin')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('Add Allergy')
  })

  it('withholds the Add buttons from a receptionist (read-only)', async () => {
    setRole('receptionist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Add Allergy')
    expect(wrapper.text()).not.toContain('Add Condition')
    expect(wrapper.text()).not.toContain('Add Medication')
  })
})

describe('MedicalHistoryPanel — empty states', () => {
  it('renders an empty state per section when nothing is on file', async () => {
    setRole('dentist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.text()).toContain('No allergies on file for this patient.')
    expect(wrapper.text()).toContain('No medical conditions on file for this patient.')
    expect(wrapper.text()).toContain('No medications on file for this patient.')
  })
})

describe('MedicalHistoryPanel — dialog wiring', () => {
  it('opens the create allergy dialog with no entry from the Add button', async () => {
    setRole('dentist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    // The Allergies section renders first (design doc §4 tab order), so its Add button is the
    // first `<button>` in the DOM — same convention as `PatientTreatmentPlansPanel.test.ts`.
    await wrapper.find('button').trigger('click')
    await flushPromises()

    const dialog = wrapper.findComponent(AllergyFormDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('allergy')).toBeNull()
  })

  it('opens the edit allergy dialog for an existing row from the list', async () => {
    setRole('dentist')
    const allergy = makeAllergy()
    mockedApi.listAllergies.mockResolvedValue({
      data: [allergy],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    })
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(AllergyList).vm.$emit('edit', allergy)
    await flushPromises()

    const dialog = wrapper.findComponent(AllergyFormDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('allergy')).toEqual(allergy)
  })

  it('renders the condition and medication dialogs, initially hidden', async () => {
    setRole('dentist')
    const wrapper = mount(MedicalHistoryPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(wrapper.findComponent(MedicalConditionFormDialog).props('visible')).toBe(false)
    expect(wrapper.findComponent(MedicationFormDialog).props('visible')).toBe(false)
  })
})
