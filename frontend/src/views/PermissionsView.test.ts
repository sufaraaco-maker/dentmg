import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import PermissionsView from './PermissionsView.vue'
import { permissionsApi } from '@/services/permissions'
import type { Permission, RolePermissionMatrix } from '@/types/permission'

vi.mock('@/services/permissions', () => ({
  permissionsApi: { catalog: vi.fn(), matrix: vi.fn(), updateMatrix: vi.fn() },
}))

const mockedApi = vi.mocked(permissionsApi)

function makeCatalog(): Permission[] {
  return [
    { key: 'patients.view', group: 'patients', description: null },
    { key: 'users.manage', group: 'users', description: null },
  ]
}

function makeMatrix(): RolePermissionMatrix {
  return {
    admin: ['patients.view', 'users.manage'],
    dentist: ['patients.view'],
    receptionist: [],
  }
}

async function mountView() {
  setActivePinia(createPinia())
  mockedApi.catalog.mockResolvedValue(makeCatalog())
  mockedApi.matrix.mockResolvedValue(makeMatrix())

  const wrapper = mount(PermissionsView)
  await flushPromises()
  return wrapper
}

/** The desktop matrix table is the only `<table>` in this view (the mobile layout is an
 *  Accordion) — scoping to it gives deterministic row-major (group → permission → role) order. */
function matrixToggles(wrapper: Awaited<ReturnType<typeof mountView>>) {
  return wrapper.find('table').findAllComponents({ name: 'ToggleSwitch' })
}

describe('PermissionsView', () => {
  it('renders one toggle per role for every catalog permission', async () => {
    const wrapper = await mountView()

    expect(wrapper.text()).toContain('View patients')
    expect(wrapper.text()).toContain('Manage users')
    expect(matrixToggles(wrapper)).toHaveLength(6) // 2 permissions x 3 roles
  })

  it('renders the Admin/users.manage cell disabled — the server-enforced self-lockout guard', async () => {
    const wrapper = await mountView()

    const toggles = matrixToggles(wrapper)
    // Row order: patients.view (admin, dentist, receptionist), users.manage (admin, dentist, receptionist)
    expect(toggles[3].props('disabled')).toBe(true)
    expect(toggles[3].props('modelValue')).toBe(true)
    expect(toggles[4].props('disabled')).toBeFalsy()
  })

  it('toggling a cell and saving submits the updated assignment for that role only', async () => {
    mockedApi.updateMatrix.mockResolvedValueOnce(makeMatrix())
    const wrapper = await mountView()

    // Turn off patients.view for dentist (index 1: patients.view/dentist)
    await matrixToggles(wrapper)[1].vm.$emit('update:modelValue', false)

    const saveButton = wrapper
      .findAllComponents({ name: 'Button' })
      .find((button) => button.props('label') === 'Save Changes')
    await saveButton!.trigger('click')
    await flushPromises()

    expect(mockedApi.updateMatrix).toHaveBeenCalledWith({
      admin: ['patients.view', 'users.manage'],
      dentist: [],
      receptionist: [],
    })
  })

  it('cannot revoke users.manage from Admin even by direct emit — the locked cell ignores the toggle', async () => {
    const wrapper = await mountView()

    await matrixToggles(wrapper)[3].vm.$emit('update:modelValue', false)

    expect(matrixToggles(wrapper)[3].props('modelValue')).toBe(true)
  })
})
