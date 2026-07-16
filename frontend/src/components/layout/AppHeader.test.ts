import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { beforeEach, describe, expect, it } from 'vitest'
import AppHeader from './AppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/login', name: 'login', component: { template: '<div />' } },
    ],
  })
}

beforeEach(() => {
  setActivePinia(createPinia())
})

async function mountHeader() {
  const router = makeRouter()
  await router.push('/')
  await router.isReady()
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Jane Doe', email: 'jane@example.com', role: 'admin' }
  const wrapper = mount(AppHeader, { global: { plugins: [router] } })
  return { wrapper, router }
}

describe('AppHeader mobile drawer trigger', () => {
  it('opens the mobile sidebar drawer when the hamburger button is clicked', async () => {
    const { wrapper } = await mountHeader()
    const ui = useUiStore()
    expect(ui.mobileSidebarOpen).toBe(false)

    const hamburger = wrapper.find('button[aria-label="Open menu"]')
    expect(hamburger.exists()).toBe(true)
    await hamburger.trigger('click')
    expect(ui.mobileSidebarOpen).toBe(true)
  })

  it('renders the signed-in user name and role', async () => {
    const { wrapper } = await mountHeader()
    expect(wrapper.text()).toContain('Jane Doe')
    expect(wrapper.text()).toContain('Admin')
  })
})
