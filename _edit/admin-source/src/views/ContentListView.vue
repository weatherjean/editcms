<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">{{ currentPostType?.label_plural || 'Content' }}</h1>
        <p class="opacity-60 mt-1">Manage your {{ currentPostType?.label_plural?.toLowerCase() }}</p>
      </div>
      <button @click="createContent" class="btn btn-primary gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        Add {{ currentPostType?.label }}
      </button>
    </div>

    <div v-if="loadingContent" role="status" class="flex justify-center gap-3 py-16">
      <span class="loading loading-spinner" aria-hidden="true"></span>
      Loading content…
    </div>

    <div v-else-if="loadError" role="alert" class="alert alert-error">
      <span>{{ loadError }}</span>
      <button class="btn btn-sm" @click="loadContent">Try again</button>
    </div>

    <div v-else-if="contentItems.length > 0" class="card bg-base-100 border shadow">
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Slug</th>
              <th>Status</th>
              <th>Created</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in contentItems" :key="item.id" class="hover">
              <td>
                <button
                  @click="editContent(item.id)"
                  class="font-mono font-semibold hover:text-primary transition-colors text-left"
                >
                  {{ item.slug }}
                </button>
              </td>
              <td>
                <span class="badge badge-sm font-mono" :class="{
                  'badge-success': item.status === 'published',
                  'badge-warning': item.status === 'draft'
                }">
                  {{ item.status }}
                </span>
              </td>
              <td>
                <div class="opacity-60">{{ formatDateTime(item.created_at) }}</div>
              </td>
              <td class="text-right">
                <div class="join">
                  <button @click="editContent(item.id)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Edit">
                    <IconEdit />
                  </button>
                  <button @click="deleteContent(item.id)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
                    <IconTrash />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else class="card bg-base-100 border shadow">
      <div class="card-body items-center text-center py-16">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-16 opacity-40 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h3 class="text-lg font-semibold mb-2">No {{ currentPostType?.label_plural?.toLowerCase() }} yet</h3>
        <p class="opacity-60 mb-4">Get started by creating your first {{ currentPostType?.label?.toLowerCase() }}</p>
        <button @click="createContent" class="btn btn-primary">
          Add {{ currentPostType?.label }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useContentList } from '../composables/useContentList'
import { useToast } from '../composables/useToast'
import IconEdit from '../components/icons/IconEdit.vue'
import IconTrash from '../components/icons/IconTrash.vue'
import { useConfirm } from '../composables/useConfirm'
import { useDate } from '../composables/useDate'

const router = useRouter()
const route = useRoute()
const props = defineProps(['postTypes'])

const { apiRequest } = useApi()
const { success, error } = useToast()
const { confirm: confirmDialog } = useConfirm()
const { formatDateTime } = useDate()

const currentType = computed(() => route.params.type)

const currentPostType = computed(() => {
  return props.postTypes.find(pt => pt.key === currentType.value)
})

const { contentItems, loadingContent, loadError, loadContent } = useContentList(
  apiRequest, currentType, currentPostType, router
)

function createContent() {
  router.push(`/${currentType.value}/create`)
}

function editContent(id) {
  router.push(`/${currentType.value}/${id}`)
}

async function deleteContent(id) {
  const confirmed = await confirmDialog('Are you sure you want to delete this item?', {
    title: 'Delete Content',
    variant: 'error',
    confirmText: 'Delete'
  })

  if (!confirmed) return

  try {
    await apiRequest('DELETE', `/${currentType.value}/${id}`)
    await loadContent()
    success('Content deleted successfully!')
  } catch (err) {
    error('Failed to delete: ' + err.message)
  }
}

// Watch with immediate:true handles initial load and subsequent changes
watch(currentType, () => {
  loadContent()
}, { immediate: true })
</script>
