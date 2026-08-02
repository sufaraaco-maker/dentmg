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

// Real `ConfirmDialog` is a global singleton mounted once in App.vue, not by this view — auto-
// accepting here tests the actual removeApiKey() wiring (does it send an explicit `null`?)
// without needing to render/click through the real dialog component.
vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({
    require: (options: { accept?: () => void }) => options.accept?.(),
  }),
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
    ai_assistant_api_key_configured: false,
    ai_assistant_api_key_last4: null,
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

  describe('API key management', () => {
    it('shows an empty input (never pre-filled) when no key is configured yet', async () => {
      mockedGet.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      expect(wrapper.text()).toContain('Anthropic API Key')
      expect(wrapper.text()).not.toContain('configured, ending in')
      const input = wrapper.find('input[type="password"], input[type="text"]')
      expect(input.exists()).toBe(true)
    })

    it('shows the masked "configured" chip with last4 once a key is set, not the raw value', async () => {
      mockedGet.mockResolvedValue(
        makeSettings({
          ai_assistant_enabled: true,
          ai_assistant_api_key_configured: true,
          ai_assistant_api_key_last4: 'ab12',
        }),
      )

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      expect(wrapper.text()).toContain('ab12')
      expect(wrapper.text()).toContain('Replace')
      expect(wrapper.text()).toContain('Remove')
    })

    it('saves a new key as its own action, independent of the toggles form', async () => {
      mockedGet.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))
      mockedUpdate.mockResolvedValue(
        makeSettings({
          ai_assistant_enabled: true,
          ai_assistant_api_key_configured: true,
          ai_assistant_api_key_last4: 'cd34',
        }),
      )

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      await wrapper.get('input[type="password"]').setValue('sk-ant-abcd1234')
      const saveKeyButton = wrapper.findAll('button').find((b) => b.text() === 'Save Key')
      await saveKeyButton?.trigger('click')
      await flushPromises()

      expect(mockedUpdate).toHaveBeenCalledWith({ ai_assistant_api_key: 'sk-ant-abcd1234' })
      expect(mockedUpdate).not.toHaveBeenCalledWith(
        expect.objectContaining({ ai_assistant_enabled: expect.anything() }),
      )
    })

    it('disables Save Key until something is typed', async () => {
      mockedGet.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      const saveKeyButton = wrapper.findAll('button').find((b) => b.text() === 'Save Key')
      expect(saveKeyButton?.attributes('disabled')).toBeDefined()
    })

    it('removes the key on confirm accept, sending an explicit null (not omitting the field)', async () => {
      mockedGet.mockResolvedValue(
        makeSettings({
          ai_assistant_enabled: true,
          ai_assistant_api_key_configured: true,
          ai_assistant_api_key_last4: 'ab12',
        }),
      )
      mockedUpdate.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      const removeButton = wrapper.findAll('button').find((b) => b.text() === 'Remove')
      await removeButton?.trigger('click')
      await flushPromises()

      expect(mockedUpdate).toHaveBeenCalledWith({ ai_assistant_api_key: null })
    })

    it('"Replace" reveals an empty input rather than the previously configured key', async () => {
      mockedGet.mockResolvedValue(
        makeSettings({
          ai_assistant_enabled: true,
          ai_assistant_api_key_configured: true,
          ai_assistant_api_key_last4: 'ab12',
        }),
      )

      const wrapper = mount(AiAssistantSettingsView)
      await flushPromises()

      const replaceButton = wrapper.findAll('button').find((b) => b.text() === 'Replace')
      await replaceButton?.trigger('click')

      const input = wrapper.get<HTMLInputElement>('input[type="password"]')
      expect(input.element.value).toBe('')
    })
  })
})
