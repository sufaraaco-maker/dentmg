import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PatientDentalChartPanel from './PatientDentalChartPanel.vue'
import ToothChart from './ToothChart.vue'
import ChartEntryDialog from './ChartEntryDialog.vue'
import { dentalChartEntriesApi, dentalConditionsApi } from '@/services/dentalChart'
import { providersApi } from '@/services/appointments'
import { useAuthStore } from '@/stores/auth'
import type { DentalChartEntry } from '@/types/dentalChart'
import type { UserRole } from '@/types/user'

vi.mock('@/services/dentalChart', async () => {
  const actual = await vi.importActual<typeof import('@/services/dentalChart')>('@/services/dentalChart')
  return {
    dentalChartEntriesApi: {
      list: vi.fn(),
      create: vi.fn(),
      update: vi.fn(),
      complete: vi.fn(),
      cancel: vi.fn(),
      remove: vi.fn(),
    },
    dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
    isDentalChartEntryError: actual.isDentalChartEntryError,
    rethrowDentalChartEntryError: actual.rethrowDentalChartEntryError,
  }
})

vi.mock('@/services/appointments', () => ({ providersApi: { listAll: vi.fn() } }))

const mockedEntriesApi = vi.mocked(dentalChartEntriesApi)
const mockedConditionsApi = vi.mocked(dentalConditionsApi)
const mockedProvidersApi = vi.mocked(providersApi)

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: ['M'],
    status: 'planned',
    notes: null,
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    dental_condition: {
      id: 'condition-1',
      name: 'Caries',
      category: 'finding',
      default_color: '#DC2626',
      icon_key: null,
    },
    dentist: { id: 'dentist-1', name: 'Dr. Layla Hassan' },
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
  mockedProvidersApi.listAll.mockResolvedValue([])
  mockedConditionsApi.list.mockResolvedValue([])
  mockedEntriesApi.list.mockResolvedValue([])
})

describe('PatientDentalChartPanel — data loading', () => {
  it("fetches this patient's entries and the conditions catalog on mount", async () => {
    setRole('dentist')
    mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    expect(mockedEntriesApi.list).toHaveBeenCalledWith('patient-1')
    expect(mockedConditionsApi.list).toHaveBeenCalled()
  })

  it('re-fetches when patientId changes', async () => {
    setRole('dentist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedEntriesApi.list).toHaveBeenCalledWith('patient-2')
  })
})

describe('PatientDentalChartPanel — permissions', () => {
  it('grants canWrite to a dentist', async () => {
    setRole('dentist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()
    expect(wrapper.findComponent(ToothChart).props('canWrite')).toBe(true)
  })

  it('grants canWrite to an admin', async () => {
    setRole('admin')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()
    expect(wrapper.findComponent(ToothChart).props('canWrite')).toBe(true)
  })

  it('withholds canWrite from a receptionist', async () => {
    setRole('receptionist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()
    expect(wrapper.findComponent(ToothChart).props('canWrite')).toBe(false)
  })
})

describe('PatientDentalChartPanel — dialog wiring', () => {
  it('opens the create dialog prefilled from a surface click', async () => {
    setRole('dentist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(ToothChart).vm.$emit('surface-click', { tooth: '16', surface: 'M' })
    await flushPromises()

    const dialog = wrapper.findComponent(ChartEntryDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('entry')).toBeNull()
    expect(dialog.props('prefill')).toEqual({ toothNumber: '16', surfaces: ['M'] })
  })

  it('opens the create dialog prefilled from a whole-tooth click, with no surfaces', async () => {
    setRole('dentist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(ToothChart).vm.$emit('tooth-click', { tooth: '18' })
    await flushPromises()

    expect(wrapper.findComponent(ChartEntryDialog).props('prefill')).toEqual({ toothNumber: '18' })
  })

  it("opens the create dialog with no prefill from the toolbar's Add Entry button", async () => {
    setRole('dentist')
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(ToothChart).vm.$emit('add-entry')
    await flushPromises()

    const dialog = wrapper.findComponent(ChartEntryDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('entry')).toBeNull()
    expect(dialog.props('prefill')).toBeNull()
  })

  it('opens the edit dialog for an existing entry from the list table', async () => {
    setRole('dentist')
    const entry = makeEntry()
    mockedEntriesApi.list.mockResolvedValue([entry])
    const wrapper = mount(PatientDentalChartPanel, { props: { patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findComponent(ToothChart).vm.$emit('edit-entry', entry)
    await flushPromises()

    const dialog = wrapper.findComponent(ChartEntryDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('entry')).toEqual(entry)
  })
})
