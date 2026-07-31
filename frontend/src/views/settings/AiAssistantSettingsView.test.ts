import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AiAssistantSettingsView from './AiAssistantSettingsView.vue'
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
    phone: null,
    address: null,
    email: null,
    ai_assistant_enabled: false,
    ai_assistant_phi_features_acknowledged: false,
    updated_at: '2026-07-30T00:00:00Z',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

describe('AiAssistantSettingsView', () => {
  it('loads and displays the current toggles', async () => {
    mockedGet.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))

    const wrapper = mount(AiAssistantSettingsView)
    await flushPromises()

    expect(mockedGet).toHaveBeenCalledOnce()
    // The PHI acknowledgment block only renders once the general toggle is on.
    expect(wrapper.text()).toContain('confirm')
  })

  it('hides the PHI acknowledgment block until the general toggle is on', async () => {
    mockedGet.mockResolvedValue(makeSettings({ ai_assistant_enabled: false }))

    const wrapper = mount(AiAssistantSettingsView)
    await flushPromises()

    expect(wrapper.text()).not.toContain('Business Associate Agreement')
  })

  it('saves only the two toggle fields — never resends practice name/phone/address/email', async () => {
    // A fresh, never-configured clinic's self-healed row has a blank name (`ClinicSettingService`) —
    // if this screen resent it, the backend's `name` validation would reject the save. Regression
    // test for that bug.
    mockedGet.mockResolvedValue(makeSettings({ name: '' }))
    mockedUpdate.mockResolvedValue(
      makeSettings({ name: '', ai_assistant_enabled: true, ai_assistant_phi_features_acknowledged: true }),
    )

    const wrapper = mount(AiAssistantSettingsView)
    await flushPromises()

    await wrapper.findComponent({ name: 'ToggleSwitch' }).setValue(true)
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(mockedUpdate).toHaveBeenCalledWith({
      ai_assistant_enabled: true,
      ai_assistant_phi_features_acknowledged: false,
    })
  })
})
