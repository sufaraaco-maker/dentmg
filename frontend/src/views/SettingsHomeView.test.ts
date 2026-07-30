import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { describe, expect, it } from 'vitest'
import SettingsHomeView from './SettingsHomeView.vue'

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/settings', name: 'settings', component: SettingsHomeView },
      { path: '/settings/practice', name: 'settings-practice', component: { template: '<div />' } },
      { path: '/settings/billing', name: 'settings-billing', component: { template: '<div />' } },
    ],
  })
}

async function mountView() {
  const router = makeRouter()
  router.push('/settings')
  await router.isReady()

  return { router, wrapper: mount(SettingsHomeView, { global: { plugins: [router] } }) }
}

describe('SettingsHomeView', () => {
  it('shows a card for every settings screen', async () => {
    const { wrapper } = await mountView()

    expect(wrapper.text()).toContain('Practice Settings')
    expect(wrapper.text()).toContain('Billing Settings')
  })

  it('navigates to the practice settings screen when its card is activated', async () => {
    const { router, wrapper } = await mountView()

    await wrapper.findAll('[role="link"]')[0].trigger('click')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('settings-practice')
  })

  it('is keyboard-reachable — Enter on a card navigates the same as a click', async () => {
    const { router, wrapper } = await mountView()

    await wrapper.findAll('[role="link"]')[1].trigger('keydown.enter')
    await flushPromises()

    expect(router.currentRoute.value.name).toBe('settings-billing')
  })
})
