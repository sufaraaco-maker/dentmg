import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Button from 'primevue/button'
import ActivityTimeline from './ActivityTimeline.vue'
import { patientActivitiesApi } from '@/services/patientActivities'
import type { PatientActivity } from '@/types/patientActivity'

vi.mock('@/services/patientActivities', () => ({
  patientActivitiesApi: { list: vi.fn() },
}))

const mockedList = vi.mocked(patientActivitiesApi.list)

function makeActivity(overrides: Partial<PatientActivity> = {}): PatientActivity {
  return {
    id: overrides.id ?? 'activity-1',
    patient_id: 'patient-1',
    event_type: 'invoice.issued',
    category: 'billing',
    subject_type: 'Invoice',
    subject_id: 'invoice-1',
    actor_id: 'user-1',
    summary: 'Invoice #INV-1 issued',
    metadata: null,
    occurred_at: '2026-08-09T09:00:00+00:00',
    actor: { id: 'user-1', name: 'Dr. Smith' },
    ...overrides,
  }
}

function makePage(
  activities: PatientActivity[],
  overrides: Partial<{ current_page: number; last_page: number; per_page: number; total: number }> = {},
) {
  return {
    data: activities,
    meta: {
      current_page: overrides.current_page ?? 1,
      last_page: overrides.last_page ?? 1,
      per_page: overrides.per_page ?? 15,
      total: overrides.total ?? activities.length,
    },
  }
}

async function mountTimeline(props: Record<string, unknown> = {}) {
  const wrapper = mount(ActivityTimeline, { props: { patientId: 'patient-1', ...props } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedList.mockResolvedValue(makePage([]))
})

describe('ActivityTimeline — data loading', () => {
  it('fetches page 1 for this patient on mount', async () => {
    await mountTimeline()

    expect(mockedList).toHaveBeenCalledWith('patient-1', 1, null, 15)
  })

  it('shows the empty state when the patient has no activity', async () => {
    const wrapper = await mountTimeline()

    expect(wrapper.text()).toContain('No activity recorded for this patient yet.')
  })

  it('renders each activity’s summary, actor, and formatted date once loaded', async () => {
    mockedList.mockResolvedValue(makePage([makeActivity()]))
    const wrapper = await mountTimeline()

    expect(wrapper.text()).toContain('Invoice #INV-1 issued')
    expect(wrapper.text()).toContain('Dr. Smith')
  })

  it('re-fetches when patientId changes', async () => {
    const wrapper = await mountTimeline()
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedList).toHaveBeenCalledWith('patient-2', 1, null, 15)
  })
})

describe('ActivityTimeline — category filter', () => {
  it('shows category chips and refetches with the selected category, for the unfiltered full-Timeline case', async () => {
    const wrapper = await mountTimeline()
    expect(mockedList).toHaveBeenCalledTimes(1)

    const billingChip = wrapper.findAll('button').find((btn) => btn.text() === 'Billing')
    await billingChip?.trigger('click')
    await flushPromises()

    expect(mockedList).toHaveBeenLastCalledWith('patient-1', 1, 'billing', 15)
  })

  it('hides the chip row when embedded with a fixed category (e.g. Billing’s Payment History)', async () => {
    const wrapper = await mountTimeline({ category: 'billing' })

    expect(wrapper.findAll('button').some((btn) => btn.text() === 'All')).toBe(false)
    expect(mockedList).toHaveBeenCalledWith('patient-1', 1, 'billing', 15)
  })

  it('hides the chip row in compact mode even without a fixed category', async () => {
    const wrapper = await mountTimeline({ compact: true, pageSize: 5 })

    expect(wrapper.findAll('button').some((btn) => btn.text() === 'All')).toBe(false)
  })
})

describe('ActivityTimeline — pagination', () => {
  it('shows a "Load more" button when another page is available, and appends on click', async () => {
    mockedList.mockResolvedValueOnce(
      makePage([makeActivity({ id: 'a1' })], { current_page: 1, last_page: 2 }),
    )
    const wrapper = await mountTimeline()
    expect(wrapper.text()).toContain('Load more')

    mockedList.mockResolvedValueOnce(
      makePage([makeActivity({ id: 'a2', summary: 'Second activity' })], { current_page: 2, last_page: 2 }),
    )
    const loadMore = wrapper.findAllComponents(Button).find((btn) => btn.text() === 'Load more')
    await loadMore?.trigger('click')
    await flushPromises()

    expect(mockedList).toHaveBeenLastCalledWith('patient-1', 2, null, 15)
    expect(wrapper.text()).toContain('Second activity')
  })

  it('does not show "Load more" once the last page is loaded', async () => {
    mockedList.mockResolvedValueOnce(makePage([makeActivity()], { current_page: 1, last_page: 1 }))
    const wrapper = await mountTimeline()

    expect(wrapper.text()).not.toContain('Load more')
  })
})

describe('ActivityTimeline — compact (Overview preview) mode', () => {
  it('shows a "View Timeline" button instead of "Load more", and emits viewAll on click', async () => {
    mockedList.mockResolvedValue(makePage([makeActivity()], { current_page: 1, last_page: 2 }))
    const wrapper = await mountTimeline({ compact: true, pageSize: 5 })

    expect(wrapper.text()).toContain('View Timeline')
    expect(wrapper.text()).not.toContain('Load more')

    const viewAll = wrapper.findAllComponents(Button).find((btn) => btn.text() === 'View Timeline')
    await viewAll?.trigger('click')

    expect(wrapper.emitted('viewAll')).toHaveLength(1)
  })
})
