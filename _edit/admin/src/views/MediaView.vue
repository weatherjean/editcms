<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Media Library</h1>
        <p class="text-sm opacity-60 mt-1">Manage your images and files</p>
      </div>
      <button @click="$refs.fileInput.click()" class="btn btn-primary gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
        Upload
      </button>
      <input ref="fileInput" type="file" @change="uploadFile" class="hidden">
    </div>

    <div v-if="mediaItems.length > 0" class="card bg-base-100 card-border border-base-300 shadow">
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th class="bg-base-200 text-sm first:rounded-tl-lg">Preview</th>
              <th class="bg-base-200 text-sm">Filename</th>
              <th class="bg-base-200 text-sm">URL</th>
              <th class="bg-base-200 text-sm text-right last:rounded-tr-lg">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in mediaItems" :key="item.id" class="hover border-b border-dashed border-base-content/5">
              <td>
                <div class="avatar">
                  <div class="w-12 h-12 rounded">
                    <img :src="item.url" :alt="item.filename" class="object-cover">
                  </div>
                </div>
              </td>
              <td>
                <div class="font-mono text-base">{{ item.filename }}</div>
              </td>
              <td>
                <div class="font-mono text-sm opacity-60 max-w-xs truncate" :title="item.url">
                  {{ item.url }}
                </div>
              </td>
              <td class="text-right">
                <div class="join">
                  <button @click="copyUrl(item.url)" class="btn btn-xs btn-neutral join-item" title="Copy URL">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                      <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                    </svg>
                  </button>
                  <a :href="item.url" download :download="item.filename" class="btn btn-xs btn-info join-item" title="Download">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </a>
                  <button @click="deleteMedia(item.id)" class="btn btn-xs btn-error join-item" title="Delete">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-else class="card bg-base-100 card-border border-base-300 shadow">
      <div class="card-body items-center text-center py-16">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 opacity-40 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <h3 class="text-lg font-semibold mb-2">No media files yet</h3>
        <p class="opacity-60 mb-4">Upload your first image or file</p>
        <button @click="$refs.fileInput.click()" class="btn btn-primary">
          Upload File
        </button>
      </div>
    </div>
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

async function copyUrl(url) {
  try {
    await navigator.clipboard.writeText(url)
    alert('URL copied to clipboard!')
  } catch (error) {
    console.error('Failed to copy URL:', error)
    alert('Failed to copy URL')
  }
}

onMounted(() => {
  loadMedia()
})
</script>
