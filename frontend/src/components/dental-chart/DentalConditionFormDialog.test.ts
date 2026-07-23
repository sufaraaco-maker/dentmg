import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DentalConditionFormDialog from './DentalConditionFormDialog.vue'
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
    icon_key: 'caries',
    default_cost: null,
    description: null,
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

describe('DentalConditionFormDialog', () => {
  it('shows a validation error when submitted with no name', async () => {
    const wrapper = mount(DentalConditionFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedApi.create).not.toHaveBeenCalled()
  })

  it('rejects a color that is not a valid 6-digit hex code', async () => {
    const wrapper = mount(DentalConditionFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#condition-name').setValue('Cracked Tooth')
    await wrapper.find('input.font-mono').setValue('#zzz')
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('Color must be a valid hex code')
    expect(mockedApi.create).not.toHaveBeenCalled()
  })

  it('creates a new dental condition with the entered fields', async () => {
    const created = makeCondition({ id: 'condition-2', name: 'Sealant' })
    mockedApi.create.mockResolvedValue(created)

    const wrapper = mount(DentalConditionFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#condition-name').setValue('Sealant')
    await wrapper.find('input.font-mono').setValue('#10B981')
    await wrapper.find('#condition-icon-key').setValue('sealant')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedApi.create).toHaveBeenCalledWith({
      name: 'Sealant',
      category: 'finding',
      applies_to_surface: false,
      default_color: '#10B981',
      icon_key: 'sealant',
      sort_order: null,
      is_active: true,
    })
    expect(wrapper.emitted('saved')?.[0]).toEqual([created])
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('prefills the form from the passed condition when editing', async () => {
    const existing = makeCondition({ name: 'Root Canal Treatment', category: 'procedure', is_active: false })
    const wrapper = mount(DentalConditionFormDialog, {
      props: { visible: true, condition: existing },
    })
    await flushPromises()

    expect((wrapper.find('#condition-name').element as HTMLInputElement).value).toBe('Root Canal Treatment')
    expect(wrapper.text()).toContain('Edit Dental Condition')
  })

  it('updates an existing dental condition on save', async () => {
    const existing = makeCondition()
    const updated = makeCondition({ name: 'Caries (Updated)' })
    mockedApi.update.mockResolvedValue(updated)

    const wrapper = mount(DentalConditionFormDialog, {
      props: { visible: true, condition: existing },
    })
    await flushPromises()

    await wrapper.find('#condition-name').setValue('Caries (Updated)')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedApi.update).toHaveBeenCalledWith(
      'condition-1',
      expect.objectContaining({ name: 'Caries (Updated)' }),
    )
    expect(wrapper.emitted('saved')?.[0]).toEqual([updated])
  })

  it('shows field errors from a 422 response', async () => {
    mockedApi.create.mockRejectedValue({
      response: { status: 422, data: { errors: { name: ['Name has already been taken'] } } },
    })

    const wrapper = mount(DentalConditionFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#condition-name').setValue('Cleaning')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Name has already been taken')
    expect(wrapper.emitted('saved')).toBeUndefined()
  })
})
