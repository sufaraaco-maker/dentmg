import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'
import AuditLogsView from './AuditLogsView.vue'
import { auditLogsApi } from '@/services/auditLogs'
import { api } from '@/lib/api'
import type { AuditLog, PaginatedAuditLogs } from '@/types/auditLog'

vi.mock('@/services/auditLogs', () => ({
  auditLogsApi: { list: vi.fn() },
}))
vi.mock('@/lib/api', () => ({
  api: { get: vi.fn() },
}))

const mockedApi = vi.mocked(auditLogsApi)
const mockedHttp = vi.mocked(api)

function makeLog(overrides: Partial<AuditLog> = {}): AuditLog {
  return {
    id: 'log-1',
    action: 'updated',
    auditable_type: 'App\\Models\\Patient',
    auditable_id: 'p1234567',
    changes: { first_name: 'Jane' },
    old_values: { first_name: 'Janet' },
    context: null,
    ip_address: '127.0.0.1',
    user_agent: 'test-agent',
    user: { id: 'u1', name: 'Admin User' },
    created_at: '2026-08-09T10:00:00+00:00',
    ...overrides,
  }
}

function makePage(overrides: Partial<PaginatedAuditLogs> = {}): PaginatedAuditLogs {
  return {
    data: [makeLog()],
    meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    ...overrides,
  }
}

async function mountView() {
  setActivePinia(createPinia())
  mockedHttp.get.mockResolvedValue({ data: { data: [], links: { next: null } } })

  const wrapper = mount(AuditLogsView)
  await flushPromises()
  return wrapper
}

describe('AuditLogsView', () => {
  it('fetches and renders the audit log on mount', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage())

    const wrapper = await mountView()

    expect(mockedApi.list).toHaveBeenCalledWith({}, 1)
    expect(wrapper.text()).toContain('Admin User')
    expect(wrapper.text()).toContain('Updated')
    expect(wrapper.text()).toContain('Patient')
  })

  it('shows the attempted email as the actor for a login_failed row with no resolved user', async () => {
    mockedApi.list.mockResolvedValueOnce(
      makePage({
        data: [
          makeLog({
            action: 'login_failed',
            auditable_type: 'App\\Models\\User',
            auditable_id: null,
            changes: null,
            old_values: null,
            context: { email: 'intruder@example.com' },
            user: null,
          }),
        ],
      }),
    )

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('intruder@example.com')
    expect(wrapper.text()).toContain('Login failed')
  })

  it('shows the empty state when no entries match', async () => {
    mockedApi.list.mockResolvedValueOnce(
      makePage({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }),
    )

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('No audit log entries found')
  })

  it('expands a row to reveal the before/after diff', async () => {
    mockedApi.list.mockResolvedValueOnce(makePage())
    const wrapper = await mountView()

    expect(wrapper.text()).not.toContain('Janet')

    await wrapper.get('table button[aria-expanded]').trigger('click')
    await flushPromises()

    expect(wrapper.text()).toContain('first_name')
    expect(wrapper.text()).toContain('Janet')
    expect(wrapper.text()).toContain('Jane')
  })

  it('re-fetches page 1 with the selected filters when Apply is clicked', async () => {
    mockedApi.list.mockResolvedValue(makePage())
    const wrapper = await mountView()
    mockedApi.list.mockClear()

    // Template order: user, resourceType, action Select components.
    const actionSelect = wrapper.findAllComponents({ name: 'Select' })[2]
    await actionSelect.vm.$emit('update:modelValue', 'deleted')

    const applyButton = wrapper
      .findAllComponents({ name: 'Button' })
      .find((button) => button.props('label') === 'Apply')
    await applyButton!.trigger('click')
    await flushPromises()

    expect(mockedApi.list).toHaveBeenCalledWith(expect.objectContaining({ action: 'deleted' }), 1)
  })
})
