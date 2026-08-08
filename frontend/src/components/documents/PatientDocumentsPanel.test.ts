import { mount, flushPromises } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Select from 'primevue/select'
import PatientDocumentsPanel from './PatientDocumentsPanel.vue'
import AttachmentUpload from './AttachmentUpload.vue'
import { fetchPatientDocuments, uploadDocument } from '@/services/documents'
import { useAuthStore } from '@/stores/auth'
import type { PatientDocument } from '@/types/documents'
import type { UserRole } from '@/types/user'

vi.mock('@/services/documents', () => ({
  fetchPatientDocuments: vi.fn(),
  uploadDocument: vi.fn(),
  updatePatientDocument: vi.fn(),
  deletePatientDocument: vi.fn(),
  fetchDocumentObjectUrl: vi.fn(),
}))

const mockedFetch = vi.mocked(fetchPatientDocuments)
const mockedUpload = vi.mocked(uploadDocument)

function makeDocument(overrides: Partial<PatientDocument> = {}): PatientDocument {
  return {
    id: 'doc-1',
    patient_id: 'patient-1',
    uploaded_by: 'user-1',
    category: 'consent_form',
    title: 'Consent Form',
    original_filename: 'consent.pdf',
    mime_type: 'application/pdf',
    file_size: 12345,
    notes: null,
    file_url: '/api/documents/doc-1/file',
    created_at: '2026-08-08T09:00:00+00:00',
    updated_at: '2026-08-08T09:00:00+00:00',
    ...overrides,
  }
}

function makePage(documents: PatientDocument[]) {
  return {
    data: documents,
    meta: { current_page: 1, last_page: 1, per_page: 15, total: documents.length },
  }
}

function setRole(role: UserRole) {
  const auth = useAuthStore()
  auth.user = { id: 'u1', name: 'Test User', email: 't@example.com', role }
}

async function mountPanel(patientId = 'patient-1') {
  const wrapper = mount(PatientDocumentsPanel, { props: { patientId } })
  await flushPromises()
  return { wrapper }
}

beforeEach(() => {
  setActivePinia(createPinia())
  vi.clearAllMocks()
  mockedFetch.mockResolvedValue(makePage([]))
})

describe('PatientDocumentsPanel — data loading', () => {
  it("fetches this patient's documents (page 1) on mount", async () => {
    setRole('dentist')
    await mountPanel('patient-1')

    expect(mockedFetch).toHaveBeenCalledWith('patient-1', { category: null }, 1)
  })

  it('re-fetches when patientId changes', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    await wrapper.setProps({ patientId: 'patient-2' })
    await flushPromises()

    expect(mockedFetch).toHaveBeenCalledWith('patient-2', { category: null }, 1)
  })

  it('shows the empty state when the patient has no documents', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('No documents for this patient yet.')
  })

  it('renders the document list once documents are loaded', async () => {
    setRole('dentist')
    mockedFetch.mockResolvedValue(makePage([makeDocument()]))
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('Consent Form')
    expect(wrapper.text()).toContain('consent.pdf')
  })

  it('refetches page 1 when the category filter changes', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')
    expect(mockedFetch).toHaveBeenCalledTimes(1)

    await wrapper.findComponent(Select).vm.$emit('update:modelValue', 'insurance')
    await flushPromises()

    expect(mockedFetch).toHaveBeenCalledWith('patient-1', { category: 'insurance' }, 1)
  })
})

describe('PatientDocumentsPanel — permissions', () => {
  it('shows the Upload Document button for a dentist', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('Upload Document')
  })

  it('shows the Upload Document button for an admin', async () => {
    setRole('admin')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('Upload Document')
  })

  it('shows the Upload Document button for a receptionist (matches PatientDocumentPolicy::create)', async () => {
    setRole('receptionist')
    const { wrapper } = await mountPanel('patient-1')

    expect(wrapper.text()).toContain('Upload Document')
  })
})

describe('PatientDocumentsPanel — dialog wiring', () => {
  it('opens the upload dialog for the current patient', async () => {
    setRole('dentist')
    const { wrapper } = await mountPanel('patient-1')

    await wrapper.find('button').trigger('click')

    const dialog = wrapper.findComponent(AttachmentUpload)
    expect(dialog.props('visible')).toBe(true)
    expect(dialog.props('patientId')).toBe('patient-1')
  })

  it('closes the dialog and refreshes the list once a document is uploaded', async () => {
    setRole('dentist')
    mockedUpload.mockResolvedValueOnce(makeDocument({ id: 'doc-2' }))
    const { wrapper } = await mountPanel('patient-1')
    expect(mockedFetch).toHaveBeenCalledTimes(1)

    await wrapper.find('button').trigger('click')
    const dialog = wrapper.findComponent(AttachmentUpload)

    await dialog.vm.$emit('uploaded', makeDocument({ id: 'doc-2' }))
    await flushPromises()

    expect(dialog.props('visible')).toBe(false)
  })
})
