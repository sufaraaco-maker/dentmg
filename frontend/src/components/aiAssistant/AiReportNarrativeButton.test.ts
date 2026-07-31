import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AiReportNarrativeButton from './AiReportNarrativeButton.vue'
import { getClinicSettings } from '@/services/settings'
import { getReportNarrative } from '@/services/aiAssistant/aiAssistantApi'
import type { ClinicSetting } from '@/types/settings'

vi.mock('@/services/settings', () => ({
  getClinicSettings: vi.fn(),
}))
vi.mock('@/services/aiAssistant/aiAssistantApi', () => ({
  getReportNarrative: vi.fn(),
  aiAssistantErrorCode: (err: unknown) =>
    (err as { response?: { data?: { code?: string } } })?.response?.data?.code ?? null,
}))

const mockedGetClinicSettings = vi.mocked(getClinicSettings)
const mockedGetReportNarrative = vi.mocked(getReportNarrative)

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

describe('AiReportNarrativeButton', () => {
  it('renders nothing when the AI Assistant is disabled', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: false }))

    const wrapper = mount(AiReportNarrativeButton, { props: { reportType: 'production', params: {} } })
    await flushPromises()

    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('fetches and displays the narrative when clicked', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings({ ai_assistant_enabled: true }))
    mockedGetReportNarrative.mockResolvedValue({ narrative: 'Production held steady.', interaction_id: 'log-1' })

    const wrapper = mount(AiReportNarrativeButton, {
      props: { reportType: 'production', params: { date_from: '2026-01-01', date_to: '2026-01-31' } },
    })
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(mockedGetReportNarrative).toHaveBeenCalledWith('production', {
      date_from: '2026-01-01',
      date_to: '2026-01-31',
    })
    expect(wrapper.text()).toContain('Production held steady.')
  })
})
