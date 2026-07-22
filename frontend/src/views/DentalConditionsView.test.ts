import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import DentalConditionsView from './DentalConditionsView.vue'
import DentalConditionFormDialog from '@/components/dental-chart/DentalConditionFormDialog.vue'
import { dentalConditionsApi } from '@/services/dentalChart'
import type { DentalCondition } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalConditionsApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

const mockedApi = vi.mocked(dentalConditionsApi)

function makeCondition(overrides: Partial<DentalCondition> = {}): DentalCondition {
  return {
    id: 'condition-1',
    name: 'Caries',
    category: 'finding',
    applies_to_surface: true,
    default_color: '#DC2626',
    icon_key: 'caries',
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
  vi.mocked(useConfirm).mockReturnValue({ require: vi.fn() } as unknown as ReturnType<typeof useConfirm>)
})

describe('DentalConditionsView', () => {
  it('loads and renders dental conditions in the table', async () => {
    mockedApi.list.mockResolvedValue([makeCondition(), makeCondition({ id: 'condition-2', name: 'Crown' })])
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Caries')
    expect(wrapper.text()).toContain('Crown')
  })

  it('filters the table by the search text', async () => {
    mockedApi.list.mockResolvedValue([makeCondition(), makeCondition({ id: 'condition-2', name: 'Crown' })])
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    await wrapper.find('input[type="text"]').setValue('crown')
    await flushPromises()

    expect(wrapper.text()).toContain('Crown')
    expect(wrapper.text()).not.toContain('Caries')
  })

  it('hides inactive conditions when "Show inactive" is toggled off', async () => {
    mockedApi.list.mockResolvedValue([
      makeCondition(),
      makeCondition({ id: 'condition-2', name: 'Retired Condition', is_active: false }),
    ])
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    expect(wrapper.text()).toContain('Retired Condition')

    await wrapper.findComponent({ name: 'ToggleSwitch' }).vm.$emit('update:modelValue', false)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Retired Condition')
  })

  it('opens the create dialog with no prefilled condition', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    const newButton = wrapper.findAll('button').find((b) => b.text().includes('New Condition'))
    await newButton?.trigger('click')

    const dialog = wrapper.findComponent(DentalConditionFormDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('condition')).toBeUndefined()
  })

  it('opens the edit dialog prefilled with the clicked row', async () => {
    mockedApi.list.mockResolvedValue([makeCondition()])
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    const editButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Edit')
    await editButton?.trigger('click')

    const dialog = wrapper.findComponent(DentalConditionFormDialog)
    expect(dialog.props('condition')?.id).toBe('condition-1')
  })

  it('deletes a condition once the confirm popup is accepted', async () => {
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)
    mockedApi.list.mockResolvedValue([makeCondition()])
    mockedApi.remove.mockResolvedValue(undefined)
    const wrapper = mount(DentalConditionsView)
    await flushPromises()

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    await requireMock.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockedApi.remove).toHaveBeenCalledWith('condition-1')
    expect(wrapper.text()).not.toContain('Caries')
  })
})
