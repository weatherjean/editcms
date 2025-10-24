<template>
  <div class="view-container">
    <div class="view-header">
      <h2>Media Library</h2>
      <button @click="$refs.fileInput.click()" class="btn btn-primary">+ Upload</button>
      <input ref="fileInput" type="file" @change="uploadFile" style="display: none;">
    </div>

    <div v-if="mediaItems.length > 0" class="media-grid">
      <div v-for="item in mediaItems" :key="item.id" class="media-item">
        <img :src="item.url" :alt="item.filename">
        <div class="media-info">
          <p>{{ item.filename }}</p>
          <button @click="deleteMedia(item.id)" class="btn btn-sm btn-danger">Delete</button>
        </div>
      </div>
    </div>

    <p v-else class="empty-state">No media files yet. Upload your first one!</p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const { apiRequest } = useApi()

const mediaItems = ref([])

async function loadMedia() {
  try {
    mediaItems.value = await apiRequest('GET', '/media')
  } catch (error) {
    console.error('Failed to load media:', error)
  }
}

async function uploadFile(event) {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)

  try {
    const token = localStorage.getItem('edit_token')
    // Use direct PHP backend URL for file uploads (Vite proxy doesn't handle FormData well)
    const response = await fetch('http://localhost:8001/_edit/api/media', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ error: 'Upload failed' }))
      throw new Error(errorData.error || 'Upload failed')
    }

    await loadMedia()
    event.target.value = ''
  } catch (error) {
    alert('Upload failed: ' + error.message)
    console.error('Upload error:', error)
  }
}

async function deleteMedia(id) {
  if (!confirm('Delete this media?')) return

  try {
    await apiRequest('DELETE', `/media/${id}`)
    await loadMedia()
  } catch (error) {
    alert('Failed to delete: ' + error.message)
  }
}

onMounted(() => {
  loadMedia()
})
</script>
