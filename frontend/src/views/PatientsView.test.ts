import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PatientsView from './PatientsView.vue'
import PatientFormDialog from '@/components/patients/PatientFormDialog.vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { UserRole } from '@/types/user'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function withStartPath(router: Router, path: string) {
  return router
    .push(path)
    .then(() => router.isReady())
    .then(() => router)
}

async function mountView(role: UserRole, path = '/patients') {
  setActivePinia(createPinia())
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/patients', name: 'patients', component: PatientsView },
      { path: '/patients/:id', name: 'patient-detail', component: { template: '<div />' } },
    ],
  })
  await withStartPath(router, path)

  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 'u1@example.com', role }

  mockedApi.get.mockResolvedValue({
    data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } },
  })

  const wrapper = mount(PatientsView, {
    global: { plugins: [router] },
  })
  await flushPromises()
  return { wrapper, router }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PatientsView "?new=1" quick action (Command Palette)', () => {
  it('opens the create dialog on mount when the query flag is present for a role that can manage patients', async () => {
    const { wrapper } = await mountView('admin', '/patients?new=1')

    expect(wrapper.findComponent(PatientFormDialog).props('visible')).toBe(true)
  })

  it('strips the query flag after opening, so a refresh does not reopen the dialog', async () => {
    const { router } = await mountView('admin', '/patients?new=1')

    expect(router.currentRoute.value.query).toEqual({})
  })

  it('does not auto-open the dialog for a role that cannot manage patients', async () => {
    const { wrapper } = await mountView('dentist', '/patients?new=1')

    expect(wrapper.findComponent(PatientFormDialog).props('visible')).toBe(false)
  })

  it('does not auto-open the dialog on a plain visit with no query flag', async () => {
    const { wrapper } = await mountView('admin', '/patients')

    expect(wrapper.findComponent(PatientFormDialog).props('visible')).toBe(false)
  })
})
