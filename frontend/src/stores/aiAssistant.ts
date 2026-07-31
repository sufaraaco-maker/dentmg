import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getClinicSettings } from '@/services/settings'

/**
 * Caches the two `ClinicSetting` AI Assistant flags so every widget/panel across the app (Dashboard,
 * Patients, Reports, Clinical Notes, Treatment Plans) can decide whether to render its AI entry
 * point without each re-fetching the whole clinic settings record — mirrors `authStore`'s
 * fetch-once-cache-in-memory shape. `loaded` guards against a widget rendering (or hiding) based on
 * stale defaults before the first fetch resolves.
 */
export const useAiAssistantStore = defineStore('aiAssistant', () => {
  const enabled = ref(false)
  const phiAcknowledged = ref(false)
  const loaded = ref(false)

  async function load(force = false) {
    if (loaded.value && !force) return

    try {
      const settings = await getClinicSettings()
      enabled.value = settings.ai_assistant_enabled
      phiAcknowledged.value = settings.ai_assistant_phi_features_acknowledged
    } catch {
      enabled.value = false
      phiAcknowledged.value = false
    } finally {
      loaded.value = true
    }
  }

  return { enabled, phiAcknowledged, loaded, load }
})
