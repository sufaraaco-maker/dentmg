import { mount, flushPromises } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PracticeSettingsView from './PracticeSettingsView.vue'
import { getClinicSettings, updateClinicSettings } from '@/services/settings'
import type { ClinicSetting } from '@/types/settings'

vi.mock('@/services/settings', () => ({
  getClinicSettings: vi.fn(),
  updateClinicSettings: vi.fn(),
}))

const mockedGet = vi.mocked(getClinicSettings)
const mockedUpdate = vi.mocked(updateClinicSettings)

function makeSettings(overrides: Partial<ClinicSetting> = {}): ClinicSetting {
  return {
    id: 'setting-1',
    name: 'Bright Smile Dental',
    phone: '+1 555-0100',
    address: '123 Main St',
    email: 'contact@brightsmile.example',
    updated_at: '2026-07-30T00:00:00Z',
    ...overrides,
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PracticeSettingsView', () => {
  it('loads and displays the existing clinic settings', async () => {
    mockedGet.mockResolvedValue(makeSettings())

    const wrapper = mount(PracticeSettingsView)
    await flushPromises()

    expect(mockedGet).toHaveBeenCalledOnce()
    expect(wrapper.find('#practice-name').element).toHaveProperty('value', 'Bright Smile Dental')
  })

  it('submits the trimmed form and re-applies the saved response', async () => {
    mockedGet.mockResolvedValue(makeSettings({ name: '' }))
    mockedUpdate.mockResolvedValue(makeSettings({ name: 'Downtown Dental Clinic' }))

    const wrapper = mount(PracticeSettingsView)
    await flushPromises()

    await wrapper.find('#practice-name').setValue('  Downtown Dental Clinic  ')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedUpdate).toHaveBeenCalledWith({
      name: 'Downtown Dental Clinic',
      phone: '+1 555-0100',
      address: '123 Main St',
      email: 'contact@brightsmile.example',
    })
    expect(wrapper.find('#practice-name').element).toHaveProperty('value', 'Downtown Dental Clinic')
  })

  it('surfaces 422 field errors without throwing', async () => {
    mockedGet.mockResolvedValue(makeSettings())
    mockedUpdate.mockRejectedValue({
      response: { status: 422, data: { errors: { name: ['The name field is required.'] } } },
    })

    const wrapper = mount(PracticeSettingsView)
    await flushPromises()

    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The name field is required.')
  })
})
