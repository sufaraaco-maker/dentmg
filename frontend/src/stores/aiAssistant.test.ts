import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { getClinicSettings } from '@/services/settings'
import { useAiAssistantStore } from './aiAssistant'
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

describe('useAiAssistantStore', () => {
  it('starts disabled and unloaded', () => {
    const store = useAiAssistantStore()

    expect(store.enabled).toBe(false)
    expect(store.phiAcknowledged).toBe(false)
    expect(store.loaded).toBe(false)
  })

  it('loads the enabled/phi flags from clinic settings', async () => {
    mockedGetClinicSettings.mockResolvedValue(
      makeSettings({ ai_assistant_enabled: true, ai_assistant_phi_features_acknowledged: true }),
    )
    const store = useAiAssistantStore()

    await store.load()

    expect(store.enabled).toBe(true)
    expect(store.phiAcknowledged).toBe(true)
    expect(store.loaded).toBe(true)
  })

  it('does not re-fetch on a second load() unless forced', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings())
    const store = useAiAssistantStore()

    await store.load()
    await store.load()

    expect(mockedGetClinicSettings).toHaveBeenCalledTimes(1)
  })

  it('re-fetches when forced', async () => {
    mockedGetClinicSettings.mockResolvedValue(makeSettings())
    const store = useAiAssistantStore()

    await store.load()
    await store.load(true)

    expect(mockedGetClinicSettings).toHaveBeenCalledTimes(2)
  })

  it('falls back to disabled on a failed fetch', async () => {
    mockedGetClinicSettings.mockRejectedValue(new Error('network error'))
    const store = useAiAssistantStore()

    await store.load()

    expect(store.enabled).toBe(false)
    expect(store.phiAcknowledged).toBe(false)
    expect(store.loaded).toBe(true)
  })
})
