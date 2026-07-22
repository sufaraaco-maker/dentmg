import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ToothChart from './ToothChart.vue'
import ToothSvg from './ToothSvg.vue'
import DentalChartToolbar from './DentalChartToolbar.vue'
import ChartEntryListTable from './ChartEntryListTable.vue'
import { dentalConditionsApi } from '@/services/dentalChart'
import type { DentalChartEntry } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
  dentalChartEntriesApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    remove: vi.fn(),
  },
}))

const mockedApi = vi.mocked(dentalConditionsApi)

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: ['M'],
    status: 'active',
    notes: null,
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    dental_condition: {
      id: 'c1',
      name: 'Caries',
      category: 'finding',
      default_color: '#DC2626',
      icon_key: null,
    },
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedApi.list.mockResolvedValue([])
})

describe('ToothChart — arch rendering', () => {
  it('renders exactly the 32 permanent teeth by default', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    expect(wrapper.findAllComponents(ToothSvg)).toHaveLength(32)
  })

  it('switches to the 20 primary teeth when the dentition filter changes to primary', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('update:dentitionFilter', 'primary')
    await flushPromises()

    const teeth = wrapper.findAllComponents(ToothSvg)
    expect(teeth).toHaveLength(20)
    expect(teeth.every((t) => Number(t.props('tooth')[0]) >= 5)).toBe(true)
  })

  it('renders all 52 teeth when the dentition filter is "all"', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('update:dentitionFilter', 'all')
    await flushPromises()

    expect(wrapper.findAllComponents(ToothSvg)).toHaveLength(52)
  })

  it('renders no tooth grid at all when switched to list view', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('update:viewMode', 'list')
    await flushPromises()

    expect(wrapper.findAllComponents(ToothSvg)).toHaveLength(0)
  })
})

describe('ToothChart — list view', () => {
  it('renders ChartEntryListTable with the full entry set when no filters are active', async () => {
    const entries = [makeEntry({ id: 'a', tooth_number: '16' }), makeEntry({ id: 'b', tooth_number: '55' })]
    const wrapper = mount(ToothChart, { props: { entries, loading: true } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('update:viewMode', 'list')
    await flushPromises()

    const table = wrapper.findComponent(ChartEntryListTable)
    expect(table.exists()).toBe(true)
    expect(table.props('loading')).toBe(true)
    // Default dentitionFilter is 'permanent' (matches the chart view's own default), so the
    // primary tooth '55' is excluded until the filter is widened.
    expect(table.props('entries')).toEqual([entries[0]])
  })

  it('applies the status/category/tooth filters to the list, independently of the chart view', async () => {
    const entries = [
      makeEntry({ id: 'a', tooth_number: '16', status: 'active' }),
      makeEntry({ id: 'b', tooth_number: '17', status: 'planned' }),
    ]
    const wrapper = mount(ToothChart, { props: { entries } })
    await flushPromises()
    const toolbar = wrapper.findComponent(DentalChartToolbar)
    await toolbar.vm.$emit('update:viewMode', 'list')
    await toolbar.vm.$emit('update:statusFilter', 'planned')
    await flushPromises()

    expect(wrapper.findComponent(ChartEntryListTable).props('entries')).toEqual([entries[1]])
  })

  it('bubbles edit-entry from the list table', async () => {
    const entries = [makeEntry({ id: 'a', tooth_number: '16' })]
    const wrapper = mount(ToothChart, { props: { entries } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('update:viewMode', 'list')
    await flushPromises()

    await wrapper.findComponent(ChartEntryListTable).vm.$emit('edit-entry', entries[0])
    expect(wrapper.emitted('edit-entry')).toEqual([[entries[0]]])
  })
})

describe('ToothChart — RTL isolation', () => {
  it('forces dir="ltr" on the chart body regardless of locale', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    expect(wrapper.find('[dir="ltr"]').exists()).toBe(true)
  })
})

describe('ToothChart — entry grouping', () => {
  it("passes only the matching tooth's entries down to its ToothSvg", async () => {
    const entries = [makeEntry({ id: 'a', tooth_number: '16' }), makeEntry({ id: 'b', tooth_number: '21' })]
    const wrapper = mount(ToothChart, { props: { entries } })
    await flushPromises()

    const tooth16 = wrapper.findAllComponents(ToothSvg).find((t) => t.props('tooth') === '16')
    const tooth11 = wrapper.findAllComponents(ToothSvg).find((t) => t.props('tooth') === '11')

    expect(tooth16?.props('entries')).toEqual([entries[0]])
    expect(tooth11?.props('entries')).toEqual([])
  })
})

describe('ToothChart — selection and events', () => {
  it('bubbles surface-click and highlights the selection on the matching tooth', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()

    const tooth16 = wrapper.findAllComponents(ToothSvg).find((t) => t.props('tooth') === '16')!
    await tooth16.vm.$emit('surface-click', { tooth: '16', surface: 'M' })

    expect(wrapper.emitted('surface-click')).toEqual([[{ tooth: '16', surface: 'M' }]])
    expect(tooth16.props('selectedSurface')).toBe('M')

    const tooth11 = wrapper.findAllComponents(ToothSvg).find((t) => t.props('tooth') === '11')!
    expect(tooth11.props('selectedSurface')).toBe(null)
  })

  it('bubbles tooth-click with a "whole" selection', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()

    const tooth18 = wrapper.findAllComponents(ToothSvg).find((t) => t.props('tooth') === '18')!
    await tooth18.vm.$emit('tooth-click', { tooth: '18' })

    expect(wrapper.emitted('tooth-click')).toEqual([[{ tooth: '18' }]])
    expect(tooth18.props('selectedSurface')).toBe('whole')
  })

  it('bubbles add-entry from the toolbar', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] } })
    await flushPromises()
    await wrapper.findComponent(DentalChartToolbar).vm.$emit('add-entry')
    expect(wrapper.emitted('add-entry')).toHaveLength(1)
  })

  it('passes canWrite through to the toolbar and disables tooth interactivity when false', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [], canWrite: false } })
    await flushPromises()

    expect(wrapper.findComponent(DentalChartToolbar).props('canWrite')).toBe(false)
    expect(wrapper.findAllComponents(ToothSvg).every((t) => t.props('interactive') === false)).toBe(true)
  })
})

describe('ToothChart — keyboard navigation', () => {
  function pressKey(key: string) {
    window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
  }

  it('moves focus to the adjacent tooth on ArrowLeft/ArrowRight', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] }, attachTo: document.body })
    await flushPromises()

    const tooth17 = wrapper.get('[data-tooth="17"] [tabindex]').element as HTMLElement
    tooth17.focus()

    pressKey('ArrowRight')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="16"] [tabindex]').element)

    wrapper.unmount()
  })

  it('moves focus to the corresponding tooth in the other quadrant row on ArrowDown', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] }, attachTo: document.body })
    await flushPromises()

    const tooth21 = wrapper.get('[data-tooth="21"] [tabindex]').element as HTMLElement
    tooth21.focus()

    pressKey('ArrowDown')
    expect(document.activeElement).toBe(wrapper.get('[data-tooth="31"] [tabindex]').element)

    wrapper.unmount()
  })

  it('does not move focus when a non-tooth element (e.g. the toolbar) is focused', async () => {
    const wrapper = mount(ToothChart, { props: { entries: [] }, attachTo: document.body })
    await flushPromises()

    document.body.focus()
    pressKey('ArrowRight')

    expect(document.activeElement).not.toBe(wrapper.get('[data-tooth="17"] [tabindex]').element)

    wrapper.unmount()
  })
})
