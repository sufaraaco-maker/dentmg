import { defineStore } from 'pinia'
import { ref } from 'vue'
import { setLocale, type SupportedLocale } from '@/locales'

const THEME_KEY = 'dentalsuite.theme'

export const useUiStore = defineStore('ui', () => {
  const isDark = ref(localStorage.getItem(THEME_KEY) === 'dark')

  function applyTheme() {
    document.documentElement.classList.toggle('dark', isDark.value)
    localStorage.setItem(THEME_KEY, isDark.value ? 'dark' : 'light')
  }

  function toggleTheme() {
    isDark.value = !isDark.value
    applyTheme()
  }

  function changeLocale(locale: SupportedLocale) {
    setLocale(locale)
  }

  applyTheme()

  return { isDark, toggleTheme, changeLocale }
})
