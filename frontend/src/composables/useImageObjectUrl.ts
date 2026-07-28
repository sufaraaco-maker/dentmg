import { onUnmounted, ref, watch, type Ref } from 'vue'
import { fetchImageObjectUrl } from '@/services/imaging'

/**
 * Fetches an authenticated image URL (design doc §9 — never a plain `<img src>` cross-origin) as a
 * Blob via `api` and exposes an object URL to bind to `<img src>`/CSS `background-image`. Re-fetches
 * whenever `sourceUrl` changes and always revokes the previous object URL first, including on unmount
 * — object URLs otherwise leak memory for the lifetime of the tab.
 */
export function useImageObjectUrl(sourceUrl: Ref<string | null | undefined>) {
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
        objectUrl.value = await fetchImageObjectUrl(url)
      } finally {
        loading.value = false
      }
    },
    { immediate: true },
  )

  onUnmounted(revoke)

  return { objectUrl, loading }
}
