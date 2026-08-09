import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FinancialSnapshotWidget from './FinancialSnapshotWidget.vue'
import type { DashboardFinancialSummary } from '@/types/dashboard'

function makeSummary(overrides: Partial<DashboardFinancialSummary> = {}): DashboardFinancialSummary {
  return {
    monthly_revenue: '1200.50',
    production_trend: { current: '1500.00', previous: '1000.00', change_pct: 50.0 },
    collections_trend: { current: '1200.50', previous: '1000.00', change_pct: 20.05 },
    ar_aging: {
      total: '450.00',
      buckets: {
        current: '200.00',
        '1_30': '150.00',
        '31_60': '50.00',
        '61_90': '30.00',
        '90_plus': '20.00',
      },
    },
    ...overrides,
  }
}

describe('FinancialSnapshotWidget — loading', () => {
  it('shows skeletons while loading with no data yet', () => {
    const wrapper = mount(FinancialSnapshotWidget, { props: { summary: null, loading: true } })

    expect(wrapper.findComponent({ name: 'Skeleton' }).exists()).toBe(true)
  })
})

describe('FinancialSnapshotWidget — rendering', () => {
  it('renders monthly revenue, production, and A/R aging total', () => {
    const wrapper = mount(FinancialSnapshotWidget, { props: { summary: makeSummary(), loading: false } })

    expect(wrapper.text()).toContain('1200.50')
    expect(wrapper.text()).toContain('1500.00')
    expect(wrapper.text()).toContain('450.00')
  })

  it('renders a positive trend badge for an increase', () => {
    const wrapper = mount(FinancialSnapshotWidget, {
      props: {
        summary: makeSummary({
          collections_trend: { current: '1200.50', previous: '1000.00', change_pct: 20 },
        }),
        loading: false,
      },
    })

    expect(wrapper.text()).toContain('+20.0%')
  })

  it('renders a negative trend badge for a decrease', () => {
    const wrapper = mount(FinancialSnapshotWidget, {
      props: {
        summary: makeSummary({
          production_trend: { current: '800.00', previous: '1000.00', change_pct: -20.0 },
        }),
        loading: false,
      },
    })

    expect(wrapper.text()).toContain('-20.0%')
  })

  it('renders no trend badge when change_pct is null', () => {
    const wrapper = mount(FinancialSnapshotWidget, {
      props: {
        summary: makeSummary({
          collections_trend: { current: '1200.50', previous: '0.00', change_pct: null },
          production_trend: { current: '1500.00', previous: '0.00', change_pct: null },
        }),
        loading: false,
      },
    })

    expect(wrapper.text()).not.toContain('%')
  })

  it('renders all 5 A/R aging buckets', () => {
    const wrapper = mount(FinancialSnapshotWidget, { props: { summary: makeSummary(), loading: false } })

    expect(wrapper.text()).toContain('200.00')
    expect(wrapper.text()).toContain('150.00')
    expect(wrapper.text()).toContain('50.00')
    expect(wrapper.text()).toContain('30.00')
    expect(wrapper.text()).toContain('20.00')
  })
})
