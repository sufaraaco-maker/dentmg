import { createI18n } from 'vue-i18n'
import ar from './ar.json'
import en from './en.json'
import tr from './tr.json'

export type SupportedLocale = 'ar' | 'en' | 'tr'

export const RTL_LOCALES: SupportedLocale[] = ['ar']

export const AVAILABLE_LOCALES: { code: SupportedLocale; label: string }[] = [
  { code: 'en', label: 'English' },
  { code: 'ar', label: 'العربية' },
  { code: 'tr', label: 'Türkçe' },
]

const STORAGE_KEY = 'dentalsuite.locale'

function detectInitialLocale(): SupportedLocale {
  const stored = localStorage.getItem(STORAGE_KEY) as SupportedLocale | null
  if (stored && AVAILABLE_LOCALES.some((l) => l.code === stored)) {
    return stored
  }
  return 'en'
}

export const i18n = createI18n({
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { ar, en, tr },
})

export function setLocale(locale: SupportedLocale) {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
  document.documentElement.dir = RTL_LOCALES.includes(locale) ? 'rtl' : 'ltr'
}
