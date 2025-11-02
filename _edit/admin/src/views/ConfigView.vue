<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Configuration</h1>
        <p class="text-sm opacity-60 mt-1">Manage modules, field groups, and content blocks</p>
      </div>
      <div class="flex gap-2">
        <button @click="exportAll" class="btn btn-sm gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Export All
        </button>
        <label class="btn btn-sm btn-primary gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
          </svg>
          Import All
          <input type="file" accept=".zip" @change="handleImportAll" class="hidden">
        </label>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else class="space-y-6">
      <!-- Modules -->
      <div class="card bg-base-100 border shadow">
        <div class="card-body">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="card-title">📦 Modules</h2>
              <p class="text-sm opacity-60">Post types with their specific field groups</p>
            </div>
            <label class="btn btn-sm btn-primary gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
              </svg>
              Upload Module
              <input type="file" accept=".json" @change="handleUpload($event, 'modules')" class="hidden">
            </label>
          </div>

          <div v-if="config.modules.length === 0" class="text-center py-8 text-base-content/60">
            No modules found
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Size</th>
                  <th>Modified</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="file in config.modules" :key="file.filename">
                  <td class="font-mono">{{ file.filename }}</td>
                  <td>{{ formatSize(file.size) }}</td>
                  <td>{{ formatDate(file.modified) }}</td>
                  <td class="text-right">
                    <div class="flex gap-2 justify-end">
                      <button @click="downloadFile('modules', file.name)" class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                      </button>
                      <label class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Replace
                        <input type="file" accept=".json" @change="handleUpload($event, 'modules')" class="hidden">
                      </label>
                      <button @click="confirmDelete('modules', file)" class="btn btn-ghost btn-xs text-error gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Shared Field Groups -->
      <div class="card bg-base-100 border shadow">
        <div class="card-body">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="card-title">🏷️ Shared Field Groups</h2>
              <p class="text-sm opacity-60">Reusable field collections</p>
            </div>
            <label class="btn btn-sm btn-primary gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
              </svg>
              Upload Field Group
              <input type="file" accept=".json" @change="handleUpload($event, 'field-groups')" class="hidden">
            </label>
          </div>

          <div v-if="config.field_groups.length === 0" class="text-center py-8 text-base-content/60">
            No field groups found
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Size</th>
                  <th>Modified</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="file in config.field_groups" :key="file.filename">
                  <td class="font-mono">{{ file.filename }}</td>
                  <td>{{ formatSize(file.size) }}</td>
                  <td>{{ formatDate(file.modified) }}</td>
                  <td class="text-right">
                    <div class="flex gap-2 justify-end">
                      <button @click="downloadFile('field-groups', file.name)" class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                      </button>
                      <label class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Replace
                        <input type="file" accept=".json" @change="handleUpload($event, 'field-groups')" class="hidden">
                      </label>
                      <button @click="confirmDelete('field-groups', file)" class="btn btn-ghost btn-xs text-error gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Content Blocks -->
      <div class="card bg-base-100 border shadow">
        <div class="card-body">
          <div class="flex items-center justify-between mb-4">
            <div>
              <h2 class="card-title">🧱 Content Blocks</h2>
              <p class="text-sm opacity-60">Flexible content building blocks</p>
            </div>
            <label class="btn btn-sm btn-primary gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
              </svg>
              Upload Block
              <input type="file" accept=".json" @change="handleUpload($event, 'blocks')" class="hidden">
            </label>
          </div>

          <div v-if="config.blocks.length === 0" class="text-center py-8 text-base-content/60">
            No blocks found
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Size</th>
                  <th>Modified</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="file in config.blocks" :key="file.filename">
                  <td class="font-mono">{{ file.filename }}</td>
                  <td>{{ formatSize(file.size) }}</td>
                  <td>{{ formatDate(file.modified) }}</td>
                  <td class="text-right">
                    <div class="flex gap-2 justify-end">
                      <button @click="downloadFile('blocks', file.name)" class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download
                      </button>
                      <label class="btn btn-ghost btn-xs gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Replace
                        <input type="file" accept=".json" @change="handleUpload($event, 'blocks')" class="hidden">
                      </label>
                      <button @click="confirmDelete('blocks', file)" class="btn btn-ghost btn-xs text-error gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                          <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <dialog :class="{'modal modal-open': showDeleteModal}" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Delete Configuration File</h3>
        <p class="mb-4">Are you sure you want to delete <strong class="font-mono">{{ deleteTarget?.filename }}</strong>? This action cannot be undone.</p>

        <div v-if="error" class="alert alert-error mb-4">
          <span>{{ error }}</span>
        </div>

        <div class="modal-action">
          <button type="button" @click="closeDeleteModal" class="btn">Cancel</button>
          <button @click="handleDelete" class="btn btn-error" :disabled="submitting">
            <span v-if="submitting" class="loading loading-spinner"></span>
            Delete File
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeDeleteModal">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const { apiRequest } = useApi()

const config = ref({
  modules: [],
  field_groups: [],
  blocks: []
})

const loading = ref(false)
const submitting = ref(false)
const error = ref(null)

const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deleteType = ref(null)

async function loadConfig() {
  loading.value = true
  try {
    const data = await apiRequest('GET', '/config')
    config.value = data
  } catch (err) {
    console.error('Failed to load config:', err)
  } finally {
    loading.value = false
  }
}

async function downloadFile(type, name) {
  try {
    const response = await fetch(`/_edit/api/config/${type}/${name}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('edit_token')}`
      }
    })

    if (!response.ok) throw new Error('Download failed')

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${name}.json`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.message || 'Download failed')
  }
}

async function exportAll() {
  try {
    const response = await fetch('/_edit/api/config/export', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('edit_token')}`
      }
    })

    if (!response.ok) throw new Error('Export failed')

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `edit-config-${new Date().toISOString().split('T')[0]}.zip`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    alert(err.message || 'Export failed')
  }
}

async function handleImportAll(event) {
  const file = event.target.files[0]
  if (!file) return

  if (!confirm('Import will replace existing configuration files with the same names. Continue?')) {
    event.target.value = ''
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  try {
    const result = await apiRequest('POST', '/config/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    await loadConfig()
    emit('reload')

    // Show detailed results
    const summary = `Imported:\n- ${result.imported.modules} modules\n- ${result.imported.field_groups} field groups\n- ${result.imported.blocks} blocks`

    if (result.errors && result.errors.length > 0) {
      alert(`${summary}\n\nErrors:\n${result.errors.join('\n')}`)
    } else {
      alert(`${summary}\n\nAll files imported successfully!`)
    }
  } catch (err) {
    alert(err.message || 'Import failed')
  }

  event.target.value = ''
}

async function handleUpload(event, type) {
  const file = event.target.files[0]
  if (!file) return

  const formData = new FormData()
  formData.append('file', file)

  try {
    await apiRequest('POST', `/config/${type}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    await loadConfig()
    // Reload the page to refresh post types/field groups
    emit('reload')
  } catch (err) {
    alert(err.message || 'Upload failed')
  }

  // Reset input
  event.target.value = ''
}

function confirmDelete(type, file) {
  deleteType.value = type
  deleteTarget.value = file
  error.value = null
  showDeleteModal.value = true
}

async function handleDelete() {
  if (!deleteTarget.value || !deleteType.value) return

  submitting.value = true
  error.value = null

  try {
    await apiRequest('DELETE', `/config/${deleteType.value}/${deleteTarget.value.name}`)
    await loadConfig()
    closeDeleteModal()
    // Reload the page to refresh post types/field groups
    emit('reload')
  } catch (err) {
    error.value = err.message || 'Failed to delete file'
  } finally {
    submitting.value = false
  }
}

function closeDeleteModal() {
  showDeleteModal.value = false
  deleteTarget.value = null
  deleteType.value = null
  error.value = null
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function formatDate(timestamp) {
  const date = new Date(timestamp * 1000)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const emit = defineEmits(['reload'])

onMounted(() => {
  loadConfig()
})
</script>
