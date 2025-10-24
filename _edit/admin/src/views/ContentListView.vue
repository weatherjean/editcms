<template>
  <div class="view-container">
    <div class="view-header">
      <h2>{{ currentPostType?.label_plural || 'Content' }}</h2>
      <button @click="createContent" class="btn btn-primary">
        + New {{ currentPostType?.label }}
      </button>
    </div>

    <table class="data-table" v-if="contentItems.length > 0">
      <thead>
        <tr>
          <th>Slug</th>
          <th>Status</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in contentItems" :key="item.id">
          <td><code>{{ item.slug }}</code></td>
          <td><span class="badge" :class="'badge-' + item.status">{{ item.status }}</span></td>
          <td>{{ formatDate(item.created_at) }}</td>
          <td>
            <button @click="editContent(item.id)" class="btn btn-sm">Edit</button>
            <button @click="deleteContent(item.id)" class="btn btn-sm btn-danger">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>

    <p v-else class="empty-state">
      No items yet. Create your first one!
    </p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const props = defineProps(['currentType', 'postTypes'])
const emit = defineEmits(['navigate'])

const { apiRequest } = useApi()

const contentItems = ref([])

const currentPostType = computed(() => {
  return props.postTypes.find(pt => pt.key === props.currentType)
})

async function loadContent() {
  if (!props.currentType) return

  try {
    contentItems.value = await apiRequest('GET', `/${props.currentType}`)
  } catch (error) {
    console.error('Failed to load content:', error)
    contentItems.value = []
  }
}

function createContent() {
  emit('navigate', 'content-create', props.currentType)
}

function editContent(id) {
  emit('navigate', 'content-edit', props.currentType, id)
}

async function deleteContent(id) {
  if (!confirm('Delete this item?')) return

  try {
    await apiRequest('DELETE', `/${props.currentType}/${id}`)
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
watch(() => props.currentType, () => {
  loadContent()
}, { immediate: true })

onMounted(() => {
  loadContent()
})
</script>
