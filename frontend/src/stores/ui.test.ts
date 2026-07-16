import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useUiStore } from './ui'

beforeEach(() => {
  localStorage.clear()
  setActivePinia(createPinia())
})

describe('useUiStore sidebar state', () => {
  it('defaults to expanded and persists the collapsed flag to localStorage on toggle', () => {
    const ui = useUiStore()
    expect(ui.sidebarCollapsed).toBe(false)

    ui.toggleSidebarCollapsed()
    expect(ui.sidebarCollapsed).toBe(true)
    expect(localStorage.getItem('dentalsuite.sidebarCollapsed')).toBe('true')

    ui.toggleSidebarCollapsed()
    expect(ui.sidebarCollapsed).toBe(false)
    expect(localStorage.getItem('dentalsuite.sidebarCollapsed')).toBe('false')
  })

  it('restores the collapsed state from localStorage on init', () => {
    localStorage.setItem('dentalsuite.sidebarCollapsed', 'true')
    const ui = useUiStore()
    expect(ui.sidebarCollapsed).toBe(true)
  })
})

describe('useUiStore mobile drawer state', () => {
  it('starts closed and opens/closes via dedicated actions', () => {
    const ui = useUiStore()
    expect(ui.mobileSidebarOpen).toBe(false)

    ui.openMobileSidebar()
    expect(ui.mobileSidebarOpen).toBe(true)

    ui.closeMobileSidebar()
    expect(ui.mobileSidebarOpen).toBe(false)
  })
})
