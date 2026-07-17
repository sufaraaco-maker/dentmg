import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import AppointmentTypesView from './AppointmentTypesView.vue'
import AppointmentTypeFormDialog from '@/components/appointments/AppointmentTypeFormDialog.vue'
import { appointmentTypesApi } from '@/services/appointments'
import type { AppointmentType } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentTypesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

const mockedApi = vi.mocked(appointmentTypesApi)

function makeType(overrides: Partial<AppointmentType> = {}): AppointmentType {
  return {
    id: 'type-1',
    name: 'Consultation',
    default_duration_minutes: 30,
    color: '#3B82F6',
    is_active: true,
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

describe('AppointmentTypesView', () => {
  it('loads and renders appointment types in the table', async () => {
    mockedApi.list.mockResolvedValue([makeType(), makeType({ id: 'type-2', name: 'Cleaning' })])
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    expect(wrapper.text()).toContain('Consultation')
    expect(wrapper.text()).toContain('Cleaning')
  })

  it('filters the table by the search text', async () => {
    mockedApi.list.mockResolvedValue([makeType(), makeType({ id: 'type-2', name: 'Cleaning' })])
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    await wrapper.find('input[type="text"]').setValue('clean')
    await flushPromises()

    expect(wrapper.text()).toContain('Cleaning')
    expect(wrapper.text()).not.toContain('Consultation')
  })

  it('hides inactive types when "Show inactive" is toggled off', async () => {
    mockedApi.list.mockResolvedValue([
      makeType(),
      makeType({ id: 'type-2', name: 'Retired Type', is_active: false }),
    ])
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    expect(wrapper.text()).toContain('Retired Type')

    await wrapper.findComponent({ name: 'ToggleSwitch' }).vm.$emit('update:modelValue', false)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Retired Type')
  })

  it('opens the create dialog with no prefilled type', async () => {
    mockedApi.list.mockResolvedValue([])
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    const newButton = wrapper.findAll('button').find((b) => b.text().includes('New Type'))
    await newButton?.trigger('click')

    const dialog = wrapper.findComponent(AppointmentTypeFormDialog)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('appointmentType')).toBeUndefined()
  })

  it('opens the edit dialog prefilled with the clicked row', async () => {
    mockedApi.list.mockResolvedValue([makeType()])
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    const editButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Edit')
    await editButton?.trigger('click')

    const dialog = wrapper.findComponent(AppointmentTypeFormDialog)
    expect(dialog.props('appointmentType')?.id).toBe('type-1')
  })

  it('deletes a type once the confirm popup is accepted', async () => {
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)
    mockedApi.list.mockResolvedValue([makeType()])
    mockedApi.remove.mockResolvedValue(undefined)
    const wrapper = mount(AppointmentTypesView)
    await flushPromises()

    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    await requireMock.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockedApi.remove).toHaveBeenCalledWith('type-1')
    expect(wrapper.text()).not.toContain('Consultation')
  })
})
