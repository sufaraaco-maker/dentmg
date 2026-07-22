import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from 'primevue/select'
import ChartEntryDialog from './ChartEntryDialog.vue'
import ToothSurface from './ToothSurface.vue'
import { dentalChartEntriesApi, dentalConditionsApi } from '@/services/dentalChart'
import { providersApi } from '@/services/appointments'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import type { DentalChartEntry, DentalCondition } from '@/types/dentalChart'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/dentalChart', async () => {
  const actual = await vi.importActual<typeof import('@/services/dentalChart')>('@/services/dentalChart')
  return {
    dentalChartEntriesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), complete: vi.fn(), cancel: vi.fn(), remove: vi.fn() },
    dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
    isDentalChartEntryError: actual.isDentalChartEntryError,
    rethrowDentalChartEntryError: actual.rethrowDentalChartEntryError,
  }
})

vi.mock('@/services/appointments', () => ({ providersApi: { listAll: vi.fn() } }))

const mockedEntriesApi = vi.mocked(dentalChartEntriesApi)
const mockedConditionsApi = vi.mocked(dentalConditionsApi)
const mockedProvidersApi = vi.mocked(providersApi)

const DENTIST: AuthUser = { id: 'dentist-1', name: 'Dr. Layla Hassan', email: 'layla@example.com', role: 'dentist' }

function makeCondition(overrides: Partial<DentalCondition> = {}): DentalCondition {
  return {
    id: 'condition-1',
    name: 'Caries',
    category: 'finding',
    applies_to_surface: true,
    default_color: '#DC2626',
    icon_key: null,
    is_active: true,
    sort_order: 1,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: ['M'],
    status: 'planned',
    notes: 'Some notes',
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    dental_condition: { id: 'condition-1', name: 'Caries', category: 'finding', default_color: '#DC2626', icon_key: null },
    dentist: { id: 'dentist-1', name: 'Dr. Layla Hassan' },
    ...overrides,
  }
}

async function primeConditions(conditions: DentalCondition[]) {
  const store = useDentalConditionsStore()
  store.items = conditions
  store.loaded = true
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedProvidersApi.listAll.mockResolvedValue([DENTIST])
  mockedConditionsApi.list.mockResolvedValue([])
})

describe('ChartEntryDialog — validation', () => {
  it('requires tooth, dentist, and condition before submitting', async () => {
    await primeConditions([makeCondition()])
    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedEntriesApi.create).not.toHaveBeenCalled()
  })

  it('requires at least one surface when the condition applies to a surface', async () => {
    await primeConditions([makeCondition({ applies_to_surface: true })])
    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    const selects = wrapper.findAllComponents(Select)
    await selects[0].vm.$emit('update:modelValue', '16') // tooth
    await selects[1].vm.$emit('update:modelValue', 'dentist-1') // dentist
    await selects[2].vm.$emit('update:modelValue', 'condition-1') // condition (finding tab)
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('Select at least one surface')
    expect(mockedEntriesApi.create).not.toHaveBeenCalled()
  })
})

describe('ChartEntryDialog — create', () => {
  it('creates a whole-tooth entry (condition without applies_to_surface) with the entered fields', async () => {
    await primeConditions([makeCondition({ id: 'condition-2', name: 'Missing Tooth', applies_to_surface: false })])
    mockedEntriesApi.create.mockResolvedValue(makeEntry())

    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    const selects = wrapper.findAllComponents(Select)
    await selects[0].vm.$emit('update:modelValue', '18')
    await selects[1].vm.$emit('update:modelValue', 'dentist-1')
    await selects[2].vm.$emit('update:modelValue', 'condition-2')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedEntriesApi.create).toHaveBeenCalledWith(
      'patient-1',
      expect.objectContaining({
        dental_condition_id: 'condition-2',
        dentist_id: 'dentist-1',
        tooth_number: '18',
        surfaces: undefined,
        status: 'existing',
      }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('pre-fills tooth and surfaces from the prefill prop', async () => {
    await primeConditions([makeCondition()])
    const wrapper = mount(ChartEntryDialog, {
      props: { visible: true, patientId: 'patient-1', prefill: { toothNumber: '26', surfaces: ['O'] } },
    })
    await flushPromises()

    const toothSelect = wrapper.findAllComponents(Select)[0]
    expect(toothSelect.props('modelValue')).toBe('26')
  })

  it('clears the selected condition when switching tabs', async () => {
    await primeConditions([
      makeCondition({ id: 'finding-1', category: 'finding' }),
      makeCondition({ id: 'procedure-1', category: 'procedure', name: 'Filling' }),
    ])
    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    const conditionSelect = wrapper.findAllComponents(Select)[2]
    await conditionSelect.vm.$emit('update:modelValue', 'finding-1')
    await flushPromises()
    expect(wrapper.findAllComponents(Select)[2].props('modelValue')).toBe('finding-1')

    await wrapper.findComponent({ name: 'Tabs' }).vm.$emit('update:value', 'procedure')
    await flushPromises()

    // A fresh Select instance now backs the "procedure" panel — its model must be null, not the
    // finding tab's leftover id.
    const procedureSelect = wrapper.findAllComponents(Select).find((s) => s.props('options')?.some((o: { value: string }) => o.value === 'procedure-1'))
    expect(procedureSelect?.props('modelValue')).toBe(null)
  })
})

describe('ChartEntryDialog — surface picker', () => {
  it('toggles a surface on click and reflects it in the selected list', async () => {
    await primeConditions([makeCondition({ applies_to_surface: true })])
    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', '16') // tooth
    await wrapper.findAllComponents(Select)[2].vm.$emit('update:modelValue', 'condition-1') // condition
    await flushPromises()

    const regions = wrapper.findAllComponents(ToothSurface)
    await regions[0].vm.$emit('activate') // center = Occlusal for tooth 16
    await flushPromises()

    expect(wrapper.text()).toContain('Occlusal')
    expect(regions[0].props('selected')).toBe(true)
  })
})

describe('ChartEntryDialog — edit', () => {
  it('prefills from the entry and disables the tooth field', async () => {
    await primeConditions([makeCondition()])
    const entry = makeEntry()
    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry } })
    await flushPromises()

    expect(wrapper.text()).toContain('Edit Chart Entry')
    const toothSelect = wrapper.findAllComponents(Select)[0]
    expect(toothSelect.props('modelValue')).toBe('16')
    expect(toothSelect.props('disabled')).toBe(true)
  })

  it('updates a non-terminal entry with the full field set', async () => {
    await primeConditions([makeCondition()])
    const entry = makeEntry({ status: 'existing' })
    mockedEntriesApi.update.mockResolvedValue(entry)

    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedEntriesApi.update).toHaveBeenCalledWith(
      'entry-1',
      expect.objectContaining({ dental_condition_id: 'condition-1', dentist_id: 'dentist-1', tooth_number: '16' }),
    )
  })

  it('sends a notes-only payload when the entry is terminal (completed)', async () => {
    await primeConditions([makeCondition()])
    const entry = makeEntry({ status: 'completed' })
    mockedEntriesApi.update.mockResolvedValue(entry)

    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedEntriesApi.update).toHaveBeenCalledWith('entry-1', { notes: 'Some notes' })
  })

  it('shows Complete/Cancel actions only when the entry is planned', async () => {
    await primeConditions([makeCondition()])

    const planned = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry: makeEntry({ status: 'planned' }) } })
    await flushPromises()
    expect(planned.text()).toContain('Complete')
    expect(planned.text()).toContain('Cancel Entry')

    const existing = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry: makeEntry({ status: 'existing' }) } })
    await flushPromises()
    expect(existing.text()).not.toContain('Cancel Entry')
  })

  it('calls the complete transition and emits saved', async () => {
    await primeConditions([makeCondition()])
    const entry = makeEntry({ status: 'planned' })
    const completed = makeEntry({ status: 'completed' })
    mockedEntriesApi.complete.mockResolvedValue(completed)

    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1', entry } })
    await flushPromises()

    const completeButton = wrapper.findAll('button').find((b) => b.text() === 'Complete')
    await completeButton?.trigger('click')
    await flushPromises()

    expect(mockedEntriesApi.complete).toHaveBeenCalledWith('entry-1')
    expect(wrapper.emitted('saved')?.[0]).toEqual([completed])
  })
})

describe('ChartEntryDialog — error handling', () => {
  it('shows field errors from a 422 response', async () => {
    await primeConditions([makeCondition({ applies_to_surface: false })])
    mockedEntriesApi.create.mockRejectedValue({
      response: { status: 422, data: { errors: { dentist_id: ['The selected dentist is invalid.'] } } },
    })

    const wrapper = mount(ChartEntryDialog, { props: { visible: true, patientId: 'patient-1' } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', '18')
    await wrapper.findAllComponents(Select)[1].vm.$emit('update:modelValue', 'dentist-1')
    await wrapper.findAllComponents(Select)[2].vm.$emit('update:modelValue', 'condition-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The selected dentist is invalid.')
    expect(wrapper.emitted('saved')).toBeUndefined()
  })
})
