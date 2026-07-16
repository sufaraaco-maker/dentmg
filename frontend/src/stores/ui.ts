import { defineStore } from 'pinia'
import { ref } from 'vue'
import { setLocale, type SupportedLocale } from '@/locales'

const THEME_KEY = 'dentalsuite.theme'
const SIDEBAR_COLLAPSED_KEY = 'dentalsuite.sidebarCollapsed'

export const useUiStore = defineStore('ui', () => {
  const isDark = ref(localStorage.getItem(THEME_KEY) === 'dark')
  const sidebarCollapsed = ref(localStorage.getItem(SIDEBAR_COLLAPSED_KEY) === 'true')
  const mobileSidebarOpen = ref(false)

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

  function toggleSidebarCollapsed() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem(SIDEBAR_COLLAPSED_KEY, String(sidebarCollapsed.value))
  }

  function openMobileSidebar() {
    mobileSidebarOpen.value = true
  }

  function closeMobileSidebar() {
    mobileSidebarOpen.value = false
  }

  applyTheme()

  return {
    isDark,
    toggleTheme,
    changeLocale,
    sidebarCollapsed,
    toggleSidebarCollapsed,
    mobileSidebarOpen,
    openMobileSidebar,
    closeMobileSidebar,
  }
})
