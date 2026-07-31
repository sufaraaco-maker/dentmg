import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AiQuestionBox from './AiQuestionBox.vue'
import { getClinicSettings } from '@/services/settings'
import type { ClinicSetting } from '@/types/settings'

vi.mock('@/services/settings', () => ({
  getClinicSettings: vi.fn(),
}))

const mockedGetClinicSettings = vi.mocked(getClinicSettings)

function makeSettings(overrides: Partial<ClinicSetting> = {}): ClinicSetting {
  return {
    id: 'setting-1',
    name: 'Bright Smile',
    phone: null,
    address: null,
    email: null,
    ai_assistant_enabled: false,
    ai_assistant_phi_features_acknowledged: false,
    updated_at: '2026-07-31T00:00:00Z',
    ...overrides,
  }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
})

async function mountBox(askFn = vi.fn()) {
  const wrapper = mount(AiQuestionBox, {
    props: { title: 'Ask a question', placeholder: 'Type here', askFn },
  })
  await flushPromises()
  return wrapper
}

describe('AiQuestionBox', () => {
  it('renders nothing when the AI Assistant is disabled', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: false }))

    const wrapper = await mountBox()

    expect(wrapper.find('.p-card').exists()).toBe(false)
  })

  it('renders the question box when enabled', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))

    const wrapper = await mountBox()

    expect(wrapper.text()).toContain('Ask a question')
  })

  it('asks the question and shows the answer', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))
    const askFn = vi.fn().mockResolvedValue({ answer: 'Collections rose 12%.', interaction_id: 'log-1' })

    const wrapper = await mountBox(askFn)
    await wrapper.find('textarea').setValue('How did collections trend?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(askFn).toHaveBeenCalledWith('How did collections trend?')
    expect(wrapper.text()).toContain('Collections rose 12%.')
  })

  it('shows the unavailable error when the API call fails with that code', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))
    const askFn = vi.fn().mockRejectedValue({ response: { data: { code: 'ai_assistant_unavailable' } } })

    const wrapper = await mountBox(askFn)
    await wrapper.find('textarea').setValue('question')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('not configured')
  })
})
