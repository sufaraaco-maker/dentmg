import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import TreatmentPlanListTable from './TreatmentPlanListTable.vue'
import type { TreatmentPlan, TreatmentPlanItem } from '@/types/treatmentPlan'

function makeItem(overrides: Partial<TreatmentPlanItem> = {}): TreatmentPlanItem {
  return {
    id: 'item-1',
    treatment_plan_id: 'plan-1',
    dental_condition_id: 'condition-1',
    procedure_name: 'Composite Filling',
    procedure_description: null,
    diagnosis_entry_id: null,
    tooth_number: '16',
    surfaces: null,
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
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    estimated_cost: '150.00',
    dentist: { id: 'dentist-1', name: 'Dr. Layla Hassan' },
    items: [makeItem()],
    ...overrides,
  }
}

describe('TreatmentPlanListTable', () => {
  it('renders a row with title, status, dentist, item count, and cost', () => {
    const wrapper = mount(TreatmentPlanListTable, {
      props: { plans: [makePlan()], loading: false },
    })

    expect(wrapper.text()).toContain('Option A')
    expect(wrapper.text()).toContain('Draft')
    expect(wrapper.text()).toContain('Dr. Layla Hassan')
    expect(wrapper.text()).toContain('1')
    expect(wrapper.text()).toContain('150.00')
  })

  it('falls back to a placeholder title when the plan has none', () => {
    const wrapper = mount(TreatmentPlanListTable, {
      props: { plans: [makePlan({ title: null })], loading: false },
    })

    expect(wrapper.text()).toContain('Untitled Plan')
  })

  it('shows the localized empty state when there are no plans', () => {
    const wrapper = mount(TreatmentPlanListTable, { props: { plans: [], loading: false } })
    expect(wrapper.text()).toContain('No treatment plans found')
  })

  it('emits row-click with the plan id when a row is clicked', async () => {
    const wrapper = mount(TreatmentPlanListTable, {
      props: { plans: [makePlan()], loading: false },
    })

    await wrapper.find('tbody tr').trigger('click')

    expect(wrapper.emitted('row-click')).toEqual([['plan-1']])
  })
})
