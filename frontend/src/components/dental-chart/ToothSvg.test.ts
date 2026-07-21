import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import ToothSvg from './ToothSvg.vue'
import ToothSurface from './ToothSurface.vue'
import { i18n } from '@/test/setup'
import type { DentalChartEntry } from '@/types/dentalChart'

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: ['M', 'O'],
    status: 'active',
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
      icon_key: 'caries',
    },
    ...overrides,
  }
}

afterEach(() => {
  i18n.global.locale.value = 'en'
})

describe('ToothSvg — orientation', () => {
  it('orders regions center/top/right/bottom/left with correct surface codes for an upper-right posterior tooth (16)', () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '16' } })
    const labels = wrapper.findAllComponents(ToothSurface).map((c) => c.props('label'))
    expect(labels[0]).toContain('Occlusal') // center, posterior
    expect(labels[1]).toContain('Facial') // top, upper arch
    expect(labels[2]).toContain('Mesial') // right, quadrant 1 -> mesial right
    expect(labels[3]).toContain('Lingual') // bottom
    expect(labels[4]).toContain('Distal') // left
  })

  it('flips mesial/distal for an upper-left tooth (24)', () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '24' } })
    const labels = wrapper.findAllComponents(ToothSurface).map((c) => c.props('label'))
    expect(labels[2]).toContain('Distal') // right, quadrant 2 -> mesial left, so right = distal
    expect(labels[4]).toContain('Mesial') // left
  })

  it('flips facial/lingual for a lower-arch tooth (46)', () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '46' } })
    const labels = wrapper.findAllComponents(ToothSurface).map((c) => c.props('label'))
    expect(labels[1]).toContain('Lingual') // top, lower arch -> lingual on top
    expect(labels[3]).toContain('Facial') // bottom
  })

  it('uses Incisal (not Occlusal) for the center region on an anterior tooth (11)', () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '11' } })
    const labels = wrapper.findAllComponents(ToothSurface).map((c) => c.props('label'))
    expect(labels[0]).toContain('Incisal')
  })
})

describe('ToothSvg — entry rendering', () => {
  it('colors the matching surfaces from an entry and leaves others empty', () => {
    const entries = [makeEntry({ surfaces: ['M', 'O'], status: 'active' })]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const surfaces = wrapper.findAllComponents(ToothSurface)

    const centerRegion = surfaces[0] // O
    const rightRegion = surfaces[2] // M for tooth 16
    const bottomRegion = surfaces[3] // L, untouched

    expect(centerRegion.props('fill')).toBe('#DC2626')
    expect(centerRegion.props('fillOpacity')).toBe(0.55) // active
    expect(rightRegion.props('fill')).toBe('#DC2626')
    expect(bottomRegion.props('fill')).toBe('var(--p-surface-100)')
    expect(bottomRegion.props('fillOpacity')).toBe(1)
  })

  it('gives existing/completed entries full opacity and active/planned a reduced one', () => {
    const existing = mount(ToothSvg, {
      props: { tooth: '16', entries: [makeEntry({ status: 'existing', surfaces: ['O'] })] },
    })
    expect(existing.findAllComponents(ToothSurface)[0].props('fillOpacity')).toBe(1)

    const planned = mount(ToothSvg, {
      props: { tooth: '16', entries: [makeEntry({ status: 'planned', surfaces: ['O'] })] },
    })
    expect(planned.findAllComponents(ToothSurface)[0].props('fillOpacity')).toBe(0.55)

    const cancelled = mount(ToothSvg, {
      props: { tooth: '16', entries: [makeEntry({ status: 'cancelled', surfaces: ['O'] })] },
    })
    expect(cancelled.findAllComponents(ToothSurface)[0].props('fillOpacity')).toBe(0.15)
  })

  it('picks the most clinically relevant entry when two share a surface (active over cancelled)', () => {
    const entries = [
      makeEntry({ id: 'old', status: 'cancelled', surfaces: ['O'], dental_condition: { ...makeEntry().dental_condition!, default_color: '#000000' } }),
      makeEntry({ id: 'new', status: 'active', surfaces: ['O'], dental_condition: { ...makeEntry().dental_condition!, default_color: '#DC2626' } }),
    ]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const centerRegion = wrapper.findAllComponents(ToothSurface)[0]
    expect(centerRegion.props('fill')).toBe('#DC2626')
    expect(centerRegion.props('fillOpacity')).toBe(0.55)
  })

  it('renders a single whole-tooth region (not 5 sub-regions) when an entry has no surfaces', () => {
    const entries = [
      makeEntry({
        surfaces: null,
        status: 'existing',
        dental_condition: { id: 'c2', name: 'Missing Tooth', category: 'finding', default_color: '#6B7280', icon_key: 'missing' },
      }),
    ]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const surfaces = wrapper.findAllComponents(ToothSurface)
    expect(surfaces).toHaveLength(1)
    expect(surfaces[0].props('fill')).toBe('#6B7280')
    expect(surfaces[0].props('glyph')).toBe('x')
  })

  it('renders fill "none" with a colored stroke for an outline-glyph condition (e.g. fracture)', () => {
    const entries = [
      makeEntry({
        surfaces: null,
        status: 'active',
        dental_condition: { id: 'c3', name: 'Fracture', category: 'finding', default_color: '#F97316', icon_key: 'fracture' },
      }),
    ]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const region = wrapper.findAllComponents(ToothSurface)[0]
    expect(region.props('fill')).toBe('none')
    expect(region.props('strokeColor')).toBe('#F97316')
    expect(region.props('glyph')).toBe('outline')
  })
})

describe('ToothSvg — events', () => {
  it('emits surface-click with the tooth and resolved surface code', async () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '16' } })
    await wrapper.findAllComponents(ToothSurface)[2].vm.$emit('activate') // right = M for tooth 16
    expect(wrapper.emitted('surface-click')).toEqual([[{ tooth: '16', surface: 'M' }]])
  })

  it('emits tooth-click for a whole-tooth region', async () => {
    const entries = [makeEntry({ surfaces: null })]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    await wrapper.findAllComponents(ToothSurface)[0].vm.$emit('activate')
    expect(wrapper.emitted('tooth-click')).toEqual([[{ tooth: '16' }]])
  })
})

describe('ToothSvg — RTL isolation', () => {
  it('always renders dir="ltr" on the root, regardless of the active locale', () => {
    const enWrapper = mount(ToothSvg, { props: { tooth: '16' } })
    expect(enWrapper.find('svg').attributes('dir')).toBe('ltr')

    i18n.global.locale.value = 'ar'
    const arWrapper = mount(ToothSvg, { props: { tooth: '16' } })
    expect(arWrapper.find('svg').attributes('dir')).toBe('ltr')
  })
})

describe('ToothSvg — accessibility', () => {
  it('builds a root aria-label including the tooth code and display name', () => {
    const wrapper = mount(ToothSvg, { props: { tooth: '16' } })
    const label = wrapper.find('svg').attributes('aria-label')
    expect(label).toContain('16')
    expect(label).toContain('Upper Right First Molar')
    expect(label).toContain('No conditions recorded')
  })

  it('includes a summary of every entry, not just the winning one per region', () => {
    const entries = [
      makeEntry({ id: 'a', status: 'active', surfaces: ['O'] }),
      makeEntry({
        id: 'b',
        status: 'completed',
        surfaces: ['M'],
        dental_condition: { id: 'c4', name: 'Composite Filling', category: 'procedure', default_color: '#2563EB', icon_key: 'filling' },
      }),
    ]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const label = wrapper.find('svg').attributes('aria-label')
    expect(label).toContain('2 entries recorded')
    expect(label).toContain('Caries')
    expect(label).toContain('Composite Filling')
  })

  it('exposes an unmounted-region aria-label distinct from an entry-covered one', () => {
    const entries = [makeEntry({ status: 'active', surfaces: ['O'] })]
    const wrapper = mount(ToothSvg, { props: { tooth: '16', entries } })
    const labels = wrapper.findAllComponents(ToothSurface).map((c) => c.props('label'))
    expect(labels[0]).toContain('Caries') // center/O covered
    expect(labels[3]).toContain('No conditions recorded') // bottom/L empty
  })
})
