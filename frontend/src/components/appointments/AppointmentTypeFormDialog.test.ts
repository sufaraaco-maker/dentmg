import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AppointmentTypeFormDialog from './AppointmentTypeFormDialog.vue'
import { appointmentTypesApi } from '@/services/appointments'
import type { AppointmentType } from '@/types/appointment'

vi.mock('@/services/appointments', () => ({
  appointmentTypesApi: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

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
})

describe('AppointmentTypeFormDialog', () => {
  it('shows a validation error when submitted with no name', async () => {
    const wrapper = mount(AppointmentTypeFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('This field is required')
    expect(mockedApi.create).not.toHaveBeenCalled()
  })

  it('rejects a color that is not a valid 6-digit hex code', async () => {
    const wrapper = mount(AppointmentTypeFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#type-name').setValue('Cleaning')
    await wrapper.find('input.font-mono').setValue('#zzz')
    await wrapper.find('form').trigger('submit.prevent')

    expect(wrapper.text()).toContain('Color must be a valid hex code')
    expect(mockedApi.create).not.toHaveBeenCalled()
  })

  it('creates a new appointment type with the entered fields', async () => {
    const created = makeType({ id: 'type-2', name: 'Cleaning' })
    mockedApi.create.mockResolvedValue(created)

    const wrapper = mount(AppointmentTypeFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#type-name').setValue('Cleaning')
    await wrapper.find('input.font-mono').setValue('#10B981')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedApi.create).toHaveBeenCalledWith({
      name: 'Cleaning',
      default_duration_minutes: 30,
      color: '#10B981',
      is_active: true,
    })
    expect(wrapper.emitted('saved')?.[0]).toEqual([created])
    expect(wrapper.emitted('update:visible')?.[0]).toEqual([false])
  })

  it('prefills the form from the passed appointmentType when editing', async () => {
    const existing = makeType({ name: 'Root Canal', default_duration_minutes: 90, is_active: false })
    const wrapper = mount(AppointmentTypeFormDialog, {
      props: { visible: true, appointmentType: existing },
    })
    await flushPromises()

    expect((wrapper.find('#type-name').element as HTMLInputElement).value).toBe('Root Canal')
    expect(wrapper.text()).toContain('Edit Appointment Type')
  })

  it('updates an existing appointment type on save', async () => {
    const existing = makeType()
    const updated = makeType({ name: 'Consultation (Updated)' })
    mockedApi.update.mockResolvedValue(updated)

    const wrapper = mount(AppointmentTypeFormDialog, {
      props: { visible: true, appointmentType: existing },
    })
    await flushPromises()

    await wrapper.find('#type-name').setValue('Consultation (Updated)')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedApi.update).toHaveBeenCalledWith(
      'type-1',
      expect.objectContaining({ name: 'Consultation (Updated)' }),
    )
    expect(wrapper.emitted('saved')?.[0]).toEqual([updated])
  })

  it('shows field errors from a 422 response', async () => {
    mockedApi.create.mockRejectedValue({
      response: { status: 422, data: { errors: { name: ['Name has already been taken'] } } },
    })

    const wrapper = mount(AppointmentTypeFormDialog, { props: { visible: true } })
    await flushPromises()

    await wrapper.find('#type-name').setValue('Cleaning')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Name has already been taken')
    expect(wrapper.emitted('saved')).toBeUndefined()
  })
})
