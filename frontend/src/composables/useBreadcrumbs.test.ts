import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { describe, expect, it } from 'vitest'
import { useBreadcrumbs } from './useBreadcrumbs'

function makeRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'dashboard', component: { template: '<div />' } },
      { path: '/appointments', name: 'appointments', component: { template: '<div />' } },
      { path: '/appointments/types', name: 'appointment-types', component: { template: '<div />' } },
      { path: '/patients', name: 'patients', component: { template: '<div />' } },
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
      {
        path: '/patients/:id/invoices/:invoiceId',
        name: 'invoice-detail',
        component: { template: '<div />' },
      },
      { path: '/account', name: 'account', component: { template: '<div />' } },
      { path: '/settings', name: 'settings', component: { template: '<div />' } },
      { path: '/settings/practice', name: 'settings-practice', component: { template: '<div />' } },
      { path: '/forbidden', name: 'forbidden', component: { template: '<div />' } },
    ],
  })
}

async function breadcrumbsFor(path: string) {
  const router = makeRouter()
  await router.push(path)
  await router.isReady()

  let result: ReturnType<typeof useBreadcrumbs> | undefined
  mount(
    defineComponent({
      setup() {
        result = useBreadcrumbs()
        return () => null
      },
    }),
    { global: { plugins: [router] } },
  )
  return result!.value
}

describe('useBreadcrumbs', () => {
  it('returns a single, non-linked entry for a top-level route with no children', async () => {
    const trail = await breadcrumbsFor('/')
    expect(trail).toEqual([{ labelKey: 'nav.dashboard', routeName: undefined }])
  })

  it('returns parent > child for a route nested under a sidebar group', async () => {
    const trail = await breadcrumbsFor('/appointments/types')
    expect(trail).toEqual([
      { labelKey: 'nav.appointments', routeName: 'appointments' },
      { labelKey: 'appointments.nav.types', routeName: undefined },
    ])
  })

  it('resolves a detail route (not in the sidebar) via its DETAIL_ROUTES parent', async () => {
    const trail = await breadcrumbsFor('/patients/p1')
    expect(trail).toEqual([
      { labelKey: 'nav.patients', routeName: 'patients' },
      { labelKey: 'nav.patients', routeName: undefined },
    ])
  })

  it('resolves a two-param detail route the same way', async () => {
    const trail = await breadcrumbsFor('/patients/p1/invoices/i1')
    expect(trail).toEqual([
      { labelKey: 'nav.patients', routeName: 'patients' },
      { labelKey: 'patients.tabs.invoices', routeName: undefined },
    ])
  })

  it('resolves a standalone route (My Account) with no parent', async () => {
    const trail = await breadcrumbsFor('/account')
    expect(trail).toEqual([{ labelKey: 'nav.myAccount', routeName: undefined }])
  })

  it('resolves a standalone route with a declared parent (a Settings sub-page)', async () => {
    const trail = await breadcrumbsFor('/settings/practice')
    expect(trail).toEqual([
      { labelKey: 'nav.settings', routeName: 'settings' },
      { labelKey: 'settings.nav.practice', routeName: undefined },
    ])
  })

  it('returns an empty trail for a truly unmapped route', async () => {
    const trail = await breadcrumbsFor('/forbidden')
    expect(trail).toEqual([])
  })
})
