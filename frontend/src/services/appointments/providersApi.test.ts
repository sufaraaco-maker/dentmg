import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '@/lib/api'
import { providersApi } from './providersApi'
import type { AuthUser } from '@/types/user'

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(api)

function user(id: string, role: AuthUser['role']): AuthUser {
  return { id, name: id, email: `${id}@example.com`, role }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('providersApi.listAll', () => {
  it('filters to role === "dentist" and stops after a single page with no next link', async () => {
    mockedApi.get.mockResolvedValueOnce({
      data: {
        data: [user('1', 'admin'), user('2', 'dentist'), user('3', 'receptionist')],
        links: { first: null, last: null, prev: null, next: null },
      },
    })

    const result = await providersApi.listAll()

    expect(result).toEqual([user('2', 'dentist')])
    expect(mockedApi.get).toHaveBeenCalledTimes(1)
    expect(mockedApi.get).toHaveBeenCalledWith('/users', { params: { page: 1 } })
  })

  it('paginates through every page until links.next is null', async () => {
    mockedApi.get
      .mockResolvedValueOnce({
        data: {
          data: [user('1', 'dentist')],
          links: { first: null, last: null, prev: null, next: '/users?page=2' },
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [user('2', 'dentist')],
          links: { first: null, last: null, prev: null, next: '/users?page=3' },
        },
      })
      .mockResolvedValueOnce({
        data: {
          data: [user('3', 'admin')],
          links: { first: null, last: null, prev: null, next: null },
        },
      })

    const result = await providersApi.listAll()

    expect(result).toEqual([user('1', 'dentist'), user('2', 'dentist')])
    expect(mockedApi.get).toHaveBeenCalledTimes(3)
    expect(mockedApi.get).toHaveBeenNthCalledWith(1, '/users', { params: { page: 1 } })
    expect(mockedApi.get).toHaveBeenNthCalledWith(2, '/users', { params: { page: 2 } })
    expect(mockedApi.get).toHaveBeenNthCalledWith(3, '/users', { params: { page: 3 } })
  })
})
