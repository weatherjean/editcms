import { ref, onScopeDispose } from 'vue'

export function useContentList(apiRequest, currentType, currentPostType, router) {
  const contentItems = ref([])
  const loadingContent = ref(false)
  const loadError = ref('')
  let loadSequence = 0

  // A response must belong to the latest request and a still-mounted view.
  onScopeDispose(() => { loadSequence++ })

  async function loadContent() {
    const type = currentType.value
    const sequence = ++loadSequence
    loadError.value = ''
    contentItems.value = []
    loadingContent.value = !!type
    if (!type) return

    try {
      const items = await apiRequest('GET', `/${type}`)
      if (sequence !== loadSequence) return
      contentItems.value = items

      if (currentPostType.value?.singleton) {
        router.replace(items.length ? `/${type}/${items[0].id}` : `/${type}/create`)
      }
    } catch (err) {
      if (sequence !== loadSequence) return
      loadError.value = err.message || 'Unable to load content. Please try again.'
    } finally {
      if (sequence === loadSequence) loadingContent.value = false
    }
  }

  return { contentItems, loadingContent, loadError, loadContent }
}
