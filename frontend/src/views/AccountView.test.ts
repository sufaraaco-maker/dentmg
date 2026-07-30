import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AccountView from './AccountView.vue'
import { useAuthStore } from '@/stores/auth'
import { getProfile, updateProfile, updateProfilePassword } from '@/services/settings'
import type { AuthUser } from '@/types/user'

vi.mock('@/services/settings', () => ({
  getProfile: vi.fn(),
  updateProfile: vi.fn(),
  updateProfilePassword: vi.fn(),
}))

const mockedGetProfile = vi.mocked(getProfile)
const mockedUpdateProfile = vi.mocked(updateProfile)
const mockedUpdatePassword = vi.mocked(updateProfilePassword)

function makeUser(overrides: Partial<AuthUser> = {}): AuthUser {
  return { id: 'user-1', name: 'Dr. Ada Lovelace', email: 'ada@example.com', role: 'dentist', ...overrides }
}

beforeEach(() => {
  vi.clearAllMocks()
  setActivePinia(createPinia())
})

describe('AccountView', () => {
  it('loads the current profile into the form', async () => {
    mockedGetProfile.mockResolvedValue(makeUser())
    useAuthStore().user = makeUser()

    const wrapper = mount(AccountView)
    await flushPromises()

    expect(wrapper.find('#account-name').element).toHaveProperty('value', 'Dr. Ada Lovelace')
    expect(wrapper.find('#account-email').element).toHaveProperty('value', 'ada@example.com')
  })

  it('saves the profile and syncs the auth store so the header updates', async () => {
    mockedGetProfile.mockResolvedValue(makeUser())
    mockedUpdateProfile.mockResolvedValue(makeUser({ name: 'New Name' }))
    const auth = useAuthStore()
    auth.user = makeUser()

    const wrapper = mount(AccountView)
    await flushPromises()

    await wrapper.find('#account-name').setValue('New Name')
    const forms = wrapper.findAll('form')
    await forms[0].trigger('submit.prevent')
    await flushPromises()

    expect(mockedUpdateProfile).toHaveBeenCalledWith({ name: 'New Name', email: 'ada@example.com' })
    expect(auth.user?.name).toBe('New Name')
  })

  it('changes the password and clears the form fields on success', async () => {
    mockedGetProfile.mockResolvedValue(makeUser())
    mockedUpdatePassword.mockResolvedValue(undefined)
    useAuthStore().user = makeUser()

    const wrapper = mount(AccountView)
    await flushPromises()

    await wrapper.find('#account-current-password input').setValue('old-password')
    await wrapper.find('#account-new-password input').setValue('new-secure-password')
    await wrapper.find('#account-confirm-password input').setValue('new-secure-password')

    const forms = wrapper.findAll('form')
    await forms[1].trigger('submit.prevent')
    await flushPromises()

    expect(mockedUpdatePassword).toHaveBeenCalledWith({
      current_password: 'old-password',
      password: 'new-secure-password',
      password_confirmation: 'new-secure-password',
    })
    expect((wrapper.find('#account-current-password input').element as HTMLInputElement).value).toBe('')
  })

  it('surfaces a wrong-current-password 422 error', async () => {
    mockedGetProfile.mockResolvedValue(makeUser())
    mockedUpdatePassword.mockRejectedValue({
      response: {
        status: 422,
        data: { errors: { current_password: ['The provided password is incorrect.'] } },
      },
    })
    useAuthStore().user = makeUser()

    const wrapper = mount(AccountView)
    await flushPromises()

    const forms = wrapper.findAll('form')
    await forms[1].trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('The provided password is incorrect.')
  })
})
