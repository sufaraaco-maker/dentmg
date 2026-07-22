import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useConfirm } from 'primevue/useconfirm'
import ChartEntryListTable from './ChartEntryListTable.vue'
import { dentalChartEntriesApi } from '@/services/dentalChart'
import { useAuthStore } from '@/stores/auth'
import type { DentalChartEntry } from '@/types/dentalChart'

vi.mock('@/services/dentalChart', () => ({
  dentalChartEntriesApi: {
    list: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    complete: vi.fn(),
    cancel: vi.fn(),
    remove: vi.fn(),
  },
}))

vi.mock('primevue/useconfirm', () => ({ useConfirm: vi.fn() }))

const mockedApi = vi.mocked(dentalChartEntriesApi)

function makeEntry(overrides: Partial<DentalChartEntry> = {}): DentalChartEntry {
  return {
    id: 'entry-1',
    patient_id: 'patient-1',
    tooth_number: '16',
    dentition_type: 'permanent',
    surfaces: ['M', 'O'],
    status: 'planned',
    notes: null,
    recorded_at: '2026-07-20T09:00:00+00:00',
    completed_at: null,
    cancelled_at: null,
    created_at: '2026-07-20T09:00:00+00:00',
    updated_at: '2026-07-20T09:00:00+00:00',
    dental_condition: {
      id: 'c1',
      name: 'Caries',
      category: 'finding',
      default_color: '#DC2626',
      icon_key: null,
    },
    dentist: { id: 'd1', name: 'Dr. Layla Hassan' },
    ...overrides,
  }
}

function setRole(role: 'admin' | 'dentist' | 'receptionist') {
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 't@example.com', role }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  vi.mocked(useConfirm).mockReturnValue({ require: vi.fn() } as unknown as ReturnType<typeof useConfirm>)
})

describe('ChartEntryListTable — rendering', () => {
  it('renders entry rows with tooth, condition, surfaces, status, dentist', () => {
    setRole('admin')
    const wrapper = mount(ChartEntryListTable, { props: { entries: [makeEntry()], loading: false } })

    expect(wrapper.text()).toContain('16')
    expect(wrapper.text()).toContain('Caries')
    expect(wrapper.text()).toContain('Mesial, Occlusal')
    expect(wrapper.text()).toContain('Dr. Layla Hassan')
  })

  it('shows "Whole tooth" when the entry has no surfaces', () => {
    setRole('admin')
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ surfaces: null })], loading: false },
    })
    expect(wrapper.text()).toContain('Whole tooth')
  })

  it('shows the empty message with no entries', () => {
    setRole('admin')
    const wrapper = mount(ChartEntryListTable, { props: { entries: [], loading: false } })
    expect(wrapper.text()).toContain('No dental chart entries found')
  })
})

describe('ChartEntryListTable — role-gated actions', () => {
  it('shows edit/complete/cancel/delete for an admin on a planned entry', () => {
    setRole('admin')
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ status: 'planned' })], loading: false },
    })
    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label'))
    expect(labels).toContain('Edit')
    expect(labels).toContain('Complete')
    expect(labels).toContain('Cancel Entry')
    expect(labels).toContain('Delete')
  })

  it('hides delete for a dentist (admin-only) but keeps edit/complete/cancel', () => {
    setRole('dentist')
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ status: 'planned' })], loading: false },
    })
    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label'))
    expect(labels).toContain('Edit')
    expect(labels).not.toContain('Delete')
  })

  it('hides all write actions for a receptionist (read-only)', () => {
    setRole('receptionist')
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ status: 'planned' })], loading: false },
    })
    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label'))
    expect(labels).not.toContain('Edit')
    expect(labels).not.toContain('Complete')
    expect(labels).not.toContain('Cancel Entry')
    expect(labels).not.toContain('Delete')
  })

  it('hides complete/cancel for a non-planned entry even for an admin', () => {
    setRole('admin')
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ status: 'existing' })], loading: false },
    })
    const labels = wrapper.findAll('button').map((b) => b.attributes('aria-label'))
    expect(labels).toContain('Edit')
    expect(labels).not.toContain('Complete')
    expect(labels).not.toContain('Cancel Entry')
  })
})

describe('ChartEntryListTable — actions', () => {
  it('emits edit-entry with the row data', async () => {
    setRole('admin')
    const entry = makeEntry()
    const wrapper = mount(ChartEntryListTable, { props: { entries: [entry], loading: false } })

    const editButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Edit')
    await editButton?.trigger('click')

    expect(wrapper.emitted('edit-entry')?.[0]).toEqual([entry])
  })

  it('calls complete() when the Complete button is clicked', async () => {
    setRole('admin')
    mockedApi.complete.mockResolvedValue(makeEntry({ status: 'completed' }))
    const wrapper = mount(ChartEntryListTable, {
      props: { entries: [makeEntry({ status: 'planned' })], loading: false },
    })

    const button = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Complete')
    await button?.trigger('click')
    await flushPromises()

    expect(mockedApi.complete).toHaveBeenCalledWith('entry-1')
  })

  it('deletes an entry once the confirm popup is accepted', async () => {
    setRole('admin')
    const requireMock = vi.fn()
    vi.mocked(useConfirm).mockReturnValue({ require: requireMock } as unknown as ReturnType<
      typeof useConfirm
    >)
    mockedApi.remove.mockResolvedValue(undefined)

    const wrapper = mount(ChartEntryListTable, { props: { entries: [makeEntry()], loading: false } })
    const deleteButton = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete')
    await deleteButton?.trigger('click')

    expect(requireMock).toHaveBeenCalledTimes(1)
    await requireMock.mock.calls[0][0].accept()
    await flushPromises()

    expect(mockedApi.remove).toHaveBeenCalledWith('entry-1')
  })
})
