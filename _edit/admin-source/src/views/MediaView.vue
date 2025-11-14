<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Media Library</h1>
        <p class="opacity-60 mt-1">Manage your images and files</p>
      </div>
      <button
        @click="$refs.fileInput.click()"
        class="btn btn-primary gap-2"
        :disabled="uploading"
      >
        <span v-if="uploading" class="loading loading-spinner loading-sm"></span>
        <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
        </svg>
        {{ uploading ? 'Uploading...' : 'Upload' }}
      </button>
      <input ref="fileInput" type="file" @change="handleUpload" class="hidden" :disabled="uploading">
    </div>

    <!-- Upload Progress Bar -->
    <div v-if="uploading" class="card bg-base-100 border shadow">
      <div class="card-body">
        <div class="flex items-center gap-4">
          <span class="loading loading-spinner loading-lg"></span>
          <div class="flex-1">
            <div class="flex justify-between mb-3">
              <span class="text-base font-semibold">Uploading...</span>
              <span class="text-base font-semibold">{{ uploadProgress }}%</span>
            </div>
            <progress class="progress progress-primary w-full h-4" :value="uploadProgress" max="100"></progress>
          </div>
        </div>
      </div>
    </div>

    <div v-if="mediaItems.length > 0" class="card bg-base-100 border shadow">
      <div class="overflow-x-auto">
        <table class="table">
          <thead>
            <tr>
              <th>Preview</th>
              <th>Filename</th>
              <th>URL</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in mediaItems" :key="item.id" class="hover">
              <td>
                <div class="avatar">
                  <div class="size-24 rounded">
                    <img :src="item.url" :alt="item.filename" class="object-cover">
                  </div>
                </div>
              </td>
              <td>
                <div class="font-mono">{{ item.filename }}</div>
              </td>
              <td>
                <div class="font-mono opacity-60 max-w-xs truncate" :title="item.url">
                  {{ item.url }}
                </div>
              </td>
              <td class="text-right">
                <div class="join">
                  <button @click="copyUrl(item.url)" class="btn btn-neutral join-item" title="Copy URL">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                      <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                      <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                    </svg>
                  </button>
                  <a :href="item.url" download :download="item.filename" class="btn btn-info join-item" title="Download">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                  </a>
                  <button @click="deleteMedia(item.id)" class="btn btn-error join-item" title="Delete">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
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

    <div v-else class="card bg-base-100 border shadow">
      <div class="card-body items-center text-center py-16">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-16 opacity-40 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
import { useToast } from '../composables/useToast'
import { useConfirm } from '../composables/useConfirm'
import { useMedia } from '../composables/useMedia'

const { apiRequest } = useApi()
const { success, error } = useToast()
const { confirm: confirmDialog } = useConfirm()
const { fetchMedia, uploadFile: uploadMediaFile, uploading, uploadProgress } = useMedia()

const mediaItems = ref([])

async function loadMedia() {
  try {
    mediaItems.value = await fetchMedia()
  } catch (err) {
    console.error('Failed to load media:', err)
  }
}

async function handleUpload(event) {
  const file = event.target.files[0]
  if (!file) return

  try {
    await uploadMediaFile(file)
    await loadMedia()
    event.target.value = ''
    success('File uploaded successfully!')
  } catch (err) {
    error('Upload failed: ' + err.message)
    console.error('Upload error:', err)
  } finally {
    event.target.value = ''
  }
}

async function deleteMedia(id) {
  const confirmed = await confirmDialog('Are you sure you want to delete this media file?', {
    title: 'Delete Media',
    variant: 'error',
    confirmText: 'Delete'
  })

  if (!confirmed) return

  try {
    await apiRequest('DELETE', `/media/${id}`)
    await loadMedia()
    success('Media deleted successfully!')
  } catch (err) {
    error('Failed to delete: ' + err.message)
  }
}

async function copyUrl(url) {
  try {
    await navigator.clipboard.writeText(url)
    success('URL copied to clipboard!')
  } catch (err) {
    console.error('Failed to copy URL:', err)
    error('Failed to copy URL')
  }
}

onMounted(() => {
  loadMedia()
})
</script>
