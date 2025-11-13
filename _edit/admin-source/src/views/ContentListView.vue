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

    <div class="card bg-base-100 border shadow" v-if="contentItems.length > 0">
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
                <div class="font-mono font-semibold">{{ item.slug }}</div>
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
                <div class="opacity-60">{{ formatDate(item.created_at) }}</div>
              </td>
              <td class="text-right">
                <div class="join">
                  <button @click="editContent(item.id)" class="btn btn-neutral join-item">Edit</button>
                  <button @click="deleteContent(item.id)" class="btn btn-error join-item">Delete</button>
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
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'

const router = useRouter()
const route = useRoute()
const props = defineProps(['postTypes'])

const { apiRequest } = useApi()

const contentItems = ref([])

const currentType = computed(() => route.params.type)

const currentPostType = computed(() => {
  return props.postTypes.find(pt => pt.key === currentType.value)
})

async function loadContent() {
  if (!currentType.value) return

  try {
    contentItems.value = await apiRequest('GET', `/${currentType.value}`)
  } catch (error) {
    console.error('Failed to load content:', error)
    contentItems.value = []
  }
}

function createContent() {
  router.push(`/${currentType.value}/create`)
}

function editContent(id) {
  router.push(`/${currentType.value}/${id}`)
}

async function deleteContent(id) {
  if (!confirm('Delete this item?')) return

  try {
    await apiRequest('DELETE', `/${currentType.value}/${id}`)
    await loadContent()
  } catch (error) {
    alert('Failed to delete: ' + error.message)
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}

// Watch for type changes
watch(currentType, () => {
  loadContent()
}, { immediate: true })

onMounted(() => {
  loadContent()
})
</script>
