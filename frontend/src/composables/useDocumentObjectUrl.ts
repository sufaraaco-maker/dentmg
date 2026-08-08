import { onUnmounted, ref, watch, type Ref } from 'vue'
import { fetchDocumentObjectUrl } from '@/services/documents'

/**
 * Mirrors `useImageObjectUrl` exactly, backed by the Documents authenticated-stream endpoint instead
 * of Imaging's. Fetches an authenticated file URL as a Blob and exposes an object URL, re-fetching
 * whenever `sourceUrl` changes and always revoking the previous one, including on unmount.
 */
export function useDocumentObjectUrl(sourceUrl: Ref<string | null | undefined>) {
  const objectUrl = ref<string | null>(null)
  const loading = ref(false)

  function revoke() {
    if (objectUrl.value) {
      URL.revokeObjectURL(objectUrl.value)
      objectUrl.value = null
    }
  }

  watch(
    sourceUrl,
    async (url) => {
      revoke()
      if (!url) return

      loading.value = true
      try {
        objectUrl.value = await fetchDocumentObjectUrl(url)
      } finally {
        loading.value = false
      }
    },
    { immediate: true },
  )

  onUnmounted(revoke)

  return { objectUrl, loading }
}
