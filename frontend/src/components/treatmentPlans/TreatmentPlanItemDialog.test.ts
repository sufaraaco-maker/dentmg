import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from 'primevue/select'
import InputNumber from 'primevue/inputnumber'
import TreatmentPlanItemDialog from './TreatmentPlanItemDialog.vue'
import ToothSurface from '@/components/dental-chart/ToothSurface.vue'
import { treatmentPlansApi, treatmentPlanItemsApi } from '@/services/treatmentPlans'
import { useDentalConditionsStore } from '@/stores/dentalConditions'
import type { TreatmentPlan, TreatmentPlanItem } from '@/types/treatmentPlan'
import type { DentalCondition } from '@/types/dentalChart'

vi.mock('@/services/treatmentPlans', async () => {
  const actual = await vi.importActual<typeof import('@/services/treatmentPlans')>('@/services/treatmentPlans')
  return {
    treatmentPlansApi: {
      list: vi.fn(),
      create: vi.fn(),
      get: vi.fn(),
      update: vi.fn(),
      present: vi.fn(),
      accept: vi.fn(),
      reject: vi.fn(),
      start: vi.fn(),
      complete: vi.fn(),
      cancel: vi.fn(),
      createRevision: vi.fn(),
      remove: vi.fn(),
    },
    treatmentPlanItemsApi: { create: vi.fn(), update: vi.fn(), complete: vi.fn(), cancel: vi.fn(), remove: vi.fn() },
    isTreatmentPlanError: actual.isTreatmentPlanError,
    rethrowTreatmentPlanError: actual.rethrowTreatmentPlanError,
  }
})

const mockedItemsApi = vi.mocked(treatmentPlanItemsApi)
void treatmentPlansApi

function makeCondition(overrides: Partial<DentalCondition> = {}): DentalCondition {
  return {
    id: 'condition-1',
    name: 'Composite Filling',
    category: 'procedure',
    applies_to_surface: true,
    default_color: '#2563EB',
    icon_key: 'filling',
    default_cost: '150.00',
    description: 'A tooth-colored resin filling.',
    is_active: true,
    sort_order: 1,
    created_at: '2026-07-15T00:00:00+00:00',
    updated_at: '2026-07-15T00:00:00+00:00',
    ...overrides,
  }
}

function makeItem(overrides: Partial<TreatmentPlanItem> = {}): TreatmentPlanItem {
  return {
    id: 'item-1',
    treatment_plan_id: 'plan-1',
    dental_condition_id: 'condition-1',
    procedure_name: 'Composite Filling',
    procedure_description: 'A tooth-colored resin filling.',
    diagnosis_entry_id: null,
    tooth_number: '16',
    surfaces: ['O'],
    quantity: 1,
    unit_cost: '150.00',
    estimated_cost: '150.00',
    phase: 1,
    sequence: null,
    status: 'planned',
    appointment_id: null,
    notes: null,
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    ...overrides,
  }
}

function makePlan(overrides: Partial<TreatmentPlan> = {}): TreatmentPlan {
  return {
    id: 'plan-1',
    patient_id: 'patient-1',
    dentist_id: 'dentist-1',
    created_by_id: 'admin-1',
    title: 'Option A',
    status: 'draft',
    notes: null,
    presented_at: null,
    accepted_at: null,
    rejected_at: null,
    started_at: null,
    completed_at: null,
    cancelled_at: null,
    superseded_by_plan_id: null,
    created_at: '2026-07-22T09:00:00+00:00',
    updated_at: '2026-07-22T09:00:00+00:00',
    estimated_cost: '0.00',
    items: [],
    ...overrides,
  }
}

function primeConditions(conditions: DentalCondition[]) {
  const store = useDentalConditionsStore()
  store.items = conditions
  store.loaded = true
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('TreatmentPlanItemDialog — create', () => {
  it('requires a procedure before submitting', async () => {
    primeConditions([makeCondition()])
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedItemsApi.create).not.toHaveBeenCalled()
  })

  it('prefills unit cost from the selected procedure default_cost', async () => {
    primeConditions([makeCondition({ default_cost: '150.00' })])
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await flushPromises()

    // InputNumber order: quantity, unit_cost, phase.
    expect(wrapper.findAllComponents(InputNumber)[1].props('modelValue')).toBe(150)
  })

  it('creates an item with the entered fields', async () => {
    primeConditions([makeCondition({ applies_to_surface: false })])
    mockedItemsApi.create.mockResolvedValue(makePlan())
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await wrapper.findAllComponents(Select)[1].vm.$emit('update:modelValue', '16')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedItemsApi.create).toHaveBeenCalledWith(
      'plan-1',
      expect.objectContaining({ dental_condition_id: 'condition-1', tooth_number: '16', quantity: 1 }),
    )
    expect(wrapper.emitted('saved')).toBeTruthy()
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('requires at least one surface for a condition that applies to a surface', async () => {
    primeConditions([makeCondition({ applies_to_surface: true })])
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await wrapper.findAllComponents(Select)[1].vm.$emit('update:modelValue', '16')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('At least one surface is required')
    expect(mockedItemsApi.create).not.toHaveBeenCalled()
  })

  it('requires a tooth before the surface picker can even appear for a surface-applicable condition', async () => {
    primeConditions([makeCondition({ applies_to_surface: true })])
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedItemsApi.create).not.toHaveBeenCalled()
  })

  it('toggles a surface on click', async () => {
    primeConditions([makeCondition({ applies_to_surface: true })])
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await wrapper.findAllComponents(Select)[1].vm.$emit('update:modelValue', '16')
    await flushPromises()

    const regions = wrapper.findAllComponents(ToothSurface)
    await regions[0].vm.$emit('activate')
    await flushPromises()

    expect(regions[0].props('selected')).toBe(true)
  })
})

describe('TreatmentPlanItemDialog — edit', () => {
  it('updates a still-planned item on a draft plan with the full field set', async () => {
    primeConditions([makeCondition()])
    const item = makeItem()
    mockedItemsApi.update.mockResolvedValue(makePlan())

    const wrapper = mount(TreatmentPlanItemDialog, {
      props: { visible: true, plan: makePlan({ status: 'draft' }), item },
    })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedItemsApi.update).toHaveBeenCalledWith(
      'item-1',
      expect.objectContaining({ dental_condition_id: 'condition-1', tooth_number: '16', quantity: 1 }),
    )
  })

  it('sends only phase/notes once the parent plan is no longer a draft', async () => {
    primeConditions([makeCondition()])
    const item = makeItem()
    mockedItemsApi.update.mockResolvedValue(makePlan())

    const wrapper = mount(TreatmentPlanItemDialog, {
      props: { visible: true, plan: makePlan({ status: 'presented' }), item },
    })
    await flushPromises()

    expect(wrapper.text()).toContain('no longer a draft')

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedItemsApi.update).toHaveBeenCalledWith('item-1', { phase: 1, notes: null })
  })

  it('sends a notes-only payload when the item itself is terminal', async () => {
    primeConditions([makeCondition()])
    const item = makeItem({ status: 'completed' })
    mockedItemsApi.update.mockResolvedValue(makePlan())

    const wrapper = mount(TreatmentPlanItemDialog, {
      props: { visible: true, plan: makePlan({ status: 'in_progress' }), item },
    })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedItemsApi.update).toHaveBeenCalledWith('item-1', { notes: null })
  })
})

describe('TreatmentPlanItemDialog — error handling', () => {
  it('surfaces 422 field errors from the API', async () => {
    primeConditions([makeCondition({ applies_to_surface: false })])
    mockedItemsApi.create.mockRejectedValue({
      response: { status: 422, data: { errors: { unit_cost: ['A cost is required for this procedure.'] } } },
    })
    const wrapper = mount(TreatmentPlanItemDialog, { props: { visible: true, plan: makePlan() } })
    await flushPromises()

    await wrapper.findAllComponents(Select)[0].vm.$emit('update:modelValue', 'condition-1')
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('A cost is required for this procedure.')
    expect(wrapper.emitted('saved')).toBeFalsy()
  })
})
