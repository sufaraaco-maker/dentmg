import { mount } from '@vue/test-utils'
import { defineComponent, nextTick, ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useImageObjectUrl } from './useImageObjectUrl'

vi.mock('@/services/imaging', () => ({
  fetchImageObjectUrl: vi.fn(),
}))

import { fetchImageObjectUrl } from '@/services/imaging'

const mockedFetch = vi.mocked(fetchImageObjectUrl)

function mountWithHook(url: ReturnType<typeof ref<string | null>>) {
  let hookResult!: ReturnType<typeof useImageObjectUrl>
  const wrapper = mount(
    defineComponent({
      setup() {
        hookResult = useImageObjectUrl(url)
        return () => null
      },
    }),
  )
  return { wrapper, hook: hookResult }
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.stubGlobal('URL', { ...URL, createObjectURL: vi.fn(), revokeObjectURL: vi.fn() })
})

describe('useImageObjectUrl', () => {
  it('fetches the object URL immediately for an initial non-null source', async () => {
    mockedFetch.mockResolvedValue('blob:one')
    const url = ref<string | null>('/api/images/1/file')

    const { hook } = mountWithHook(url)
    await nextTick()
    await nextTick()

    expect(mockedFetch).toHaveBeenCalledWith('/api/images/1/file')
    expect(hook.objectUrl.value).toBe('blob:one')
  })

  it('revokes the previous object URL before fetching the next one when source changes', async () => {
    mockedFetch.mockResolvedValueOnce('blob:one').mockResolvedValueOnce('blob:two')
    const url = ref<string | null>('/api/images/1/file')

    mountWithHook(url)
    await nextTick()
    await nextTick()

    url.value = '/api/images/2/file'
    await nextTick()
    await nextTick()

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:one')
    expect(mockedFetch).toHaveBeenCalledWith('/api/images/2/file')
  })

  it('does not fetch when the source is null', async () => {
    const url = ref<string | null>(null)

    mountWithHook(url)
    await nextTick()

    expect(mockedFetch).not.toHaveBeenCalled()
  })

  it('revokes the object URL on unmount', async () => {
    mockedFetch.mockResolvedValue('blob:one')
    const url = ref<string | null>('/api/images/1/file')

    const { wrapper } = mountWithHook(url)
    await nextTick()
    await nextTick()

    wrapper.unmount()

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:one')
  })
})
