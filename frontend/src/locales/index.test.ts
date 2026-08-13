import { beforeEach, describe, expect, it, vi } from 'vitest'

/**
 * `detectInitialLocale()` runs once at module load time (top-level `createI18n({ locale:
 * detectInitialLocale() })`), so each case needs a fresh module instance via `vi.resetModules()` +
 * a dynamic import rather than the usual static import.
 */
async function loadLocaleModule() {
  vi.resetModules()
  return import('./index')
}

beforeEach(() => {
  localStorage.clear()
})

describe('detectInitialLocale', () => {
  it('defaults to English when no locale is stored', async () => {
    const { i18n } = await loadLocaleModule()

    expect(i18n.global.locale.value).toBe('en')
  })

  it('respects a previously stored locale preference', async () => {
    localStorage.setItem('dentalsuite.locale', 'ar')

    const { i18n } = await loadLocaleModule()

    expect(i18n.global.locale.value).toBe('ar')
  })

  it('falls back to English for an unrecognized stored value', async () => {
    localStorage.setItem('dentalsuite.locale', 'fr')

    const { i18n } = await loadLocaleModule()

    expect(i18n.global.locale.value).toBe('en')
  })
})

describe('AVAILABLE_LOCALES', () => {
  it('lists English first', async () => {
    const { AVAILABLE_LOCALES } = await loadLocaleModule()

    expect(AVAILABLE_LOCALES[0].code).toBe('en')
  })
})
