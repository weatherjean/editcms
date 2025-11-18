<template>
  <CardSection title="Revisions" collapsible :default-collapsed="true" body-class="space-y-4">
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
          <div class="join">
            <button
              type="button"
              @click="viewRevision(revision)"
              class="btn btn-ghost btn-xs join-item"
              title="View revision data"
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
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                />
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                />
              </svg>
            </button>
            <button
              type="button"
              @click="restoreRevision(revision.id)"
              :disabled="restoring"
              class="btn btn-ghost btn-xs join-item"
              title="Restore this revision"
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
            </button>
          </div>
        </div>
      </div>

    <!-- Empty State -->
    <div v-else class="text-center py-8 opacity-60">
      <p class="text-sm">No revisions yet</p>
      <p class="text-xs mt-1">Revisions are created when you save</p>
    </div>
  </CardSection>

  <!-- View Revision Modal -->
  <dialog ref="viewModalRef" class="modal">
    <div class="modal-box max-w-4xl">
      <h3 class="font-bold text-lg mb-4">
        Revision #{{ selectedRevision?.revision_number }} Data
      </h3>

      <div class="mb-4">
        <div class="text-sm opacity-60 mb-2">
          <strong>Slug:</strong> {{ selectedRevision?.slug }}
        </div>
        <div class="text-sm opacity-60 mb-2">
          <strong>Status:</strong> {{ selectedRevision?.status }}
        </div>
        <div class="text-sm opacity-60 mb-4">
          <strong>Created:</strong> {{ formatDate(selectedRevision?.created_at) }}
          <span v-if="selectedRevision?.author_name"> by {{ selectedRevision.author_name }}</span>
        </div>
      </div>

      <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
          <h4 class="font-semibold">Fields Data:</h4>
          <button
            type="button"
            @click="copyToClipboard"
            class="btn btn-ghost btn-sm"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-4 h-4 mr-1"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
              />
            </svg>
            Copy JSON
          </button>
        </div>
        <pre class="bg-base-200 p-4 rounded text-xs overflow-auto max-h-96">{{ formattedFields }}</pre>
      </div>

      <div class="modal-action">
        <button type="button" @click="closeViewModal" class="btn">Close</button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop">
      <button type="button" @click="closeViewModal">close</button>
    </form>
  </dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import { useConfirm } from '../composables/useConfirm'
import CardSection from './CardSection.vue'

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
const { confirm: confirmDialog } = useConfirm()

const revisions = ref([])
const loading = ref(false)
const restoring = ref(false)
const viewModalRef = ref(null)
const selectedRevision = ref(null)

const formattedFields = computed(() => {
  if (!selectedRevision.value?.fields) return ''
  return JSON.stringify(selectedRevision.value.fields, null, 2)
})

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
  const confirmed = await confirmDialog(
    'This will create a new revision with the old data. Your current changes will be saved as the latest revision.',
    {
      title: 'Restore Revision',
      variant: 'warning',
      confirmText: 'Restore'
    }
  )

  if (!confirmed) return

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

function viewRevision(revision) {
  selectedRevision.value = revision
  viewModalRef.value?.showModal()
}

function closeViewModal() {
  viewModalRef.value?.close()
  selectedRevision.value = null
}

async function copyToClipboard() {
  try {
    await navigator.clipboard.writeText(formattedFields.value)
    success('Copied to clipboard!')
  } catch (err) {
    error('Failed to copy to clipboard')
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

// Expose refresh method for parent component
function refresh() {
  loadRevisions()
}

defineExpose({
  refresh
})

// Watch for content ID changes
watch(() => props.contentId, () => {
  loadRevisions()
}, { immediate: true })
</script>
