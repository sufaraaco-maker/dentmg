import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ToothLegend from './ToothLegend.vue'
import ToothSurface from './ToothSurface.vue'
import { dentalConditionsApi } from '@/services/dentalChart'
import type { DentalCondition } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

const mockedApi = vi.mocked(dentalConditionsApi)

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

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('ToothLegend', () => {
  it('renders only active conditions, grouped by category', async () => {
    mockedApi.list.mockResolvedValue([
      makeCondition({ id: '1', name: 'Caries', category: 'finding' }),
      makeCondition({ id: '2', name: 'Composite Filling', category: 'procedure' }),
      makeCondition({ id: '3', name: 'Retired Finding', is_active: false }),
    ])
    const wrapper = mount(ToothLegend)
    await flushPromises()

    expect(wrapper.text()).toContain('Caries')
    expect(wrapper.text()).toContain('Composite Filling')
    expect(wrapper.text()).not.toContain('Retired Finding')
    expect(wrapper.text()).toContain('Finding')
    expect(wrapper.text()).toContain('Procedure')
  })

  it('renders a whole-region ToothSurface swatch using each condition\'s own color/glyph', async () => {
    mockedApi.list.mockResolvedValue([
      makeCondition({ id: '1', name: 'Missing Tooth', default_color: '#6B7280', icon_key: 'missing' }),
    ])
    const wrapper = mount(ToothLegend)
    await flushPromises()

    const swatch = wrapper.findComponent(ToothSurface)
    expect(swatch.props('fill')).toBe('#6B7280')
    expect(swatch.props('glyph')).toBe('x')
    expect(swatch.props('interactive')).toBe(false)
  })

  it('shows the empty message when there are no active conditions', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = mount(ToothLegend)
    await flushPromises()

    expect(wrapper.text()).toContain('No active conditions')
  })

  it('shows a load-error message when fetching the catalog fails', async () => {
    mockedApi.list.mockRejectedValue(new Error('network error'))
    const wrapper = mount(ToothLegend)
    await flushPromises()

    expect(wrapper.text()).toContain('Failed to load dental conditions')
  })

  it('renders every status in the status-tone key with its own opacity/dasharray', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = mount(ToothLegend)
    await flushPromises()

    const statusLabels = ['Existing', 'Active', 'Planned', 'Completed', 'Cancelled']
    statusLabels.forEach((label) => expect(wrapper.text()).toContain(label))

    const swatches = wrapper.findAllComponents(ToothSurface)
    const cancelledSwatch = swatches[swatches.length - 1]
    expect(cancelledSwatch.props('fillOpacity')).toBe(0.15)
  })
})
