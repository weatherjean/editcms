<template>
  <div class="card bg-base-100 border shadow">
    <div class="card-body">
      <h3 class="card-title text-base">Revisions</h3>

      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center py-8">
        <span class="loading loading-spinner loading-md"></span>
      </div>

      <!-- Revisions List -->
      <div v-else-if="revisions.length > 0" class="space-y-2">
        <div
          v-for="revision in revisions"
          :key="revision.id"
          class="flex items-center justify-between p-3 bg-base-200 rounded hover:bg-base-300 transition-colors"
        >
          <div class="flex-1 min-w-0">
            <div class="font-medium text-sm">
              Revision #{{ revision.revision_number }}
            </div>
            <div class="text-xs opacity-60 truncate">
              {{ formatDate(revision.created_at) }}
            </div>
            <div v-if="revision.author_name" class="text-xs opacity-60">
              by {{ revision.author_name }}
            </div>
          </div>
          <button
            type="button"
            @click="restoreRevision(revision.id)"
            :disabled="restoring"
            class="btn btn-ghost btn-xs"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-4 h-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
              />
            </svg>
            Restore
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="text-center py-8 opacity-60">
        <p class="text-sm">No revisions yet</p>
        <p class="text-xs mt-1">Revisions are created when you save</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const props = defineProps({
  contentType: {
    type: String,
    required: true
  },
  contentId: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['restored'])

const { apiRequest } = useApi()
const { success, error } = useToast()

const revisions = ref([])
const loading = ref(false)
const restoring = ref(false)

async function loadRevisions() {
  if (!props.contentId) {
    revisions.value = []
    return
  }

  loading.value = true
  try {
    revisions.value = await apiRequest('GET', `/${props.contentType}/${props.contentId}/revisions`)
  } catch (err) {
    error('Failed to load revisions: ' + err.message)
    console.error('Failed to load revisions:', err)
    revisions.value = []
  } finally {
    loading.value = false
  }
}

async function restoreRevision(revisionId) {
  if (!confirm('Are you sure you want to restore this revision? This will create a new revision with the old data.')) {
    return
  }

  restoring.value = true
  try {
    await apiRequest('POST', `/${props.contentType}/${props.contentId}/revisions/${revisionId}/restore`)
    success('Revision restored successfully!')
    emit('restored')
    await loadRevisions()
  } catch (err) {
    error('Failed to restore revision: ' + err.message)
    console.error('Failed to restore revision:', err)
  } finally {
    restoring.value = false
  }
}

function formatDate(dateString) {
  if (!dateString) return ''
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  }).format(date)
}

// Watch for content ID changes
watch(() => props.contentId, () => {
  loadRevisions()
}, { immediate: true })
</script>
