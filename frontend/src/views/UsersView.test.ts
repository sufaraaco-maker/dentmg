import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import UsersView from './UsersView.vue'
import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import type { AuthUser } from '@/types/user'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function makeUser(overrides: Partial<AuthUser> = {}): AuthUser {
  return {
    id: 'u1',
    name: 'Ahmed Dentist',
    email: 'ahmed@example.com',
    role: 'dentist',
    avatar_url: null,
    ...overrides,
  }
}

function makePage(users: AuthUser[]) {
  return { data: users, meta: { current_page: 1, last_page: 1, per_page: 15, total: users.length } }
}

async function mountView(users: AuthUser[] = [makeUser()]) {
  setActivePinia(createPinia())
  const auth = useAuthStore()
  auth.user = makeUser({ id: 'admin1', role: 'admin' })

  mockedApi.get.mockResolvedValue({ data: makePage(users) })

  const wrapper = mount(UsersView)
  await flushPromises()
  return wrapper
}

async function openEditDialog(wrapper: Awaited<ReturnType<typeof mountView>>) {
  const editButton = wrapper.findAll('button').find((b) => b.find('.pi-pencil').exists())
  expect(editButton).toBeTruthy()
  await editButton!.trigger('click')
  await flushPromises()
}

describe('UsersView avatar upload', () => {
  it('does not show a photo picker while creating a brand-new user (no id to attach a file to yet)', async () => {
    const wrapper = await mountView()

    const newUserButton = wrapper.findAll('button').find((b) => b.text().includes('New User'))
    expect(newUserButton).toBeTruthy()
    await newUserButton!.trigger('click')
    await flushPromises()

    expect(wrapper.find('input[type="file"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Save the new user first')
  })

  it('shows the photo picker when editing an existing user', async () => {
    const wrapper = await mountView()

    await openEditDialog(wrapper)

    expect(wrapper.find('input[type="file"]').exists()).toBe(true)
  })

  it('uploads a new avatar for an existing user and reflects it in the table', async () => {
    const wrapper = await mountView()
    await openEditDialog(wrapper)

    const updated = makeUser({ avatar_url: 'https://example.test/storage/avatars/u1.png' })
    mockedApi.post.mockResolvedValueOnce({ data: updated })

    const fileInput = wrapper.find('input[type="file"]')
    const file = new File(['x'], 'avatar.png', { type: 'image/png' })
    Object.defineProperty(fileInput.element, 'files', { value: [file] })
    await fileInput.trigger('change')
    await flushPromises()

    expect(mockedApi.post).toHaveBeenCalledWith(
      '/users/u1/avatar',
      expect.any(FormData),
      expect.objectContaining({ headers: { 'Content-Type': 'multipart/form-data' } }),
    )
    // Reflected both in the still-open dialog's picker and the underlying table row.
    expect(wrapper.html()).toContain('https://example.test/storage/avatars/u1.png')
  })

  it('removes an existing avatar', async () => {
    const wrapper = await mountView([makeUser({ avatar_url: 'https://example.test/storage/avatars/u1.png' })])
    await openEditDialog(wrapper)

    const updated = makeUser({ avatar_url: null })
    mockedApi.delete.mockResolvedValueOnce({ data: updated })

    const removeButton = wrapper.findAll('button').find((b) => b.text().includes('Remove'))
    expect(removeButton).toBeTruthy()
    await removeButton!.trigger('click')
    await flushPromises()

    expect(mockedApi.delete).toHaveBeenCalledWith('/users/u1/avatar')
  })
})
