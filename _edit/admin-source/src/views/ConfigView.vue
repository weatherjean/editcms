<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Configuration</h1>
        <p class="opacity-60 mt-1">Manage modules, field groups, and content blocks</p>
      </div>
      <div class="flex gap-2">
        <button @click="exportAll" class="btn gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          Export All
        </button>
        <label class="btn btn-primary gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
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
      <CardSection title="Post Types" description="Content types with their specific field groups" body-class="">
        <template #header>
          <div class="flex items-center justify-between">
            <div>
              <h2 class="card-title">Post Types</h2>
              <p class="opacity-60 mt-1">Content types with their specific field groups</p>
            </div>
            <button @click="createPostType" class="btn btn-primary gap-2">
              + Create Post Type
            </button>
          </div>
        </template>

          <div v-if="modules.length === 0" class="text-center py-8 text-base-content/60">
            No post types found. Create one to get started!
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Post Type</th>
                  <th>Labels</th>
                  <th>Field Groups</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="module in modules" :key="module.file.filename">
                  <td>
                    <div class="font-semibold">{{ module.data.post_types[0]?.label || 'Unknown' }}</div>
                    <div class="font-mono opacity-60">{{ module.data.post_types[0]?.key }}</div>
                  </td>
                  <td>
                    <div>{{ module.data.post_types[0]?.label_plural || '-' }}</div>
                    <span v-if="module.data.post_types[0]?.allow_open" class="badge">Flexible</span>
                  </td>
                  <td>
                    <div>{{ module.data.field_groups?.length || 0 }} group(s)</div>
                  </td>
                  <td class="text-right">
                    <div class="join">
                      <button @click="editPostType(module)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Edit">
                        <IconEdit />
                      </button>
                      <button @click="confirmDelete('modules', module.file)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
                        <IconTrash />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
      </CardSection>

      <!-- Shared Field Groups -->
      <CardSection title="Shared Field Groups" description="Reusable field collections" body-class="">
        <template #header>
          <div class="flex items-center justify-between">
            <div>
              <h2 class="card-title">Shared Field Groups</h2>
              <p class="opacity-60 mt-1">Reusable field collections</p>
            </div>
            <button @click="createFieldGroup" class="btn btn-primary gap-2">
              + Create Field Group
            </button>
          </div>
        </template>

          <div v-if="fieldGroups.length === 0" class="text-center py-8 text-base-content/60">
            No field groups found. Create reusable field groups like "SEO Fields"!
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Field Group</th>
                  <th>Locations</th>
                  <th>Fields</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="fg in fieldGroups" :key="fg.file.filename">
                  <td>
                    <div class="font-semibold">{{ fg.data.title || 'Unknown' }}</div>
                    <div class="font-mono opacity-60">{{ fg.data.key }}</div>
                  </td>
                  <td>
                    <div class="flex flex-wrap gap-1">
                      <span v-for="loc in fg.data.locations" :key="loc" class="badge">{{ loc }}</span>
                    </div>
                  </td>
                  <td>
                    <div>{{ fg.data.fields?.length || 0 }} field(s)</div>
                  </td>
                  <td class="text-right">
                    <div class="join">
                      <button @click="editFieldGroup(fg)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Edit">
                        <IconEdit />
                      </button>
                      <button @click="confirmDelete('field-groups', fg.file)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
                        <IconTrash />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
      </CardSection>

      <!-- Content Blocks -->
      <CardSection title="Content Blocks" description="Flexible content building blocks" body-class="">
        <template #header>
          <div class="flex items-center justify-between">
            <div>
              <h2 class="card-title">Content Blocks</h2>
              <p class="opacity-60 mt-1">Flexible content building blocks</p>
            </div>
            <button @click="createBlock" class="btn btn-primary gap-2">
              + Create Block
            </button>
          </div>
        </template>

          <div v-if="blocks.length === 0" class="text-center py-8 text-base-content/60">
            No blocks found. Create blocks for flexible content like "Hero Section"!
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Block</th>
                  <th>Fields</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="block in blocks" :key="block.file.filename">
                  <td>
                    <div>
                      <div class="font-semibold">{{ block.data.label || 'Unknown' }}</div>
                      <div class="font-mono opacity-60">{{ block.data.key }}</div>
                    </div>
                  </td>
                  <td>
                    <div>{{ block.data.fields?.length || 0 }} field(s)</div>
                  </td>
                  <td class="text-right">
                    <div class="join">
                      <button @click="editBlock(block)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Edit">
                        <IconEdit />
                      </button>
                      <button @click="confirmDelete('blocks', block.file)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
                        <IconTrash />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
      </CardSection>
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
            Delete
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeDeleteModal">close</button>
      </form>
    </dialog>

    <!-- Editor Modals -->
    <PostTypeEditor ref="postTypeEditorRef" :all-post-types="allPostTypes" @saved="handleSaved" />
    <FieldGroupEditor ref="fieldGroupEditorRef" :available-post-types="allPostTypes" @saved="handleSaved" />
    <BlockEditor ref="blockEditorRef" :available-post-types="allPostTypes" @saved="handleSaved" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'
import { useConfirm } from '../composables/useConfirm'
import CardSection from '../components/CardSection.vue'
import PostTypeEditor from '../components/PostTypeEditor.vue'
import FieldGroupEditor from '../components/FieldGroupEditor.vue'
import BlockEditor from '../components/BlockEditor.vue'
import IconEdit from '../components/icons/IconEdit.vue'
import IconTrash from '../components/icons/IconTrash.vue'

const { apiRequest } = useApi()
const { success: showSuccess, error: showError } = useToast()
const { confirm: confirmDialog } = useConfirm()

const config = ref({
  modules: [],
  field_groups: [],
  blocks: []
})

const modules = ref([])
const fieldGroups = ref([])
const blocks = ref([])

const loading = ref(false)
const submitting = ref(false)
const error = ref(null)

const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deleteType = ref(null)

const postTypeEditorRef = ref(null)
const fieldGroupEditorRef = ref(null)
const blockEditorRef = ref(null)

const allPostTypes = computed(() => {
  return modules.value.map(m => m.data.post_types[0]).filter(Boolean)
})

async function loadConfig() {
  loading.value = true
  try {
    const data = await apiRequest('GET', '/config')
    config.value = data

    modules.value = await loadConfigType('modules', data.modules)
    fieldGroups.value = await loadConfigType('field-groups', data.field_groups)
    blocks.value = await loadConfigType('blocks', data.blocks)
  } catch (err) {
    console.error('Failed to load config:', err)
  } finally {
    loading.value = false
  }
}

async function loadJsonFile(type, name) {
  try {
    const response = await fetch(`/_edit/admin-api/config/${type}/${name}`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('edit_token')}`
      }
    })
    if (!response.ok) throw new Error('Failed to load')
    return await response.json()
  } catch (err) {
    console.error(`Failed to load ${type}/${name}:`, err)
    return {}
  }
}

async function loadConfigType(type, files) {
  return await Promise.all(files.map(async file => {
    const json = await loadJsonFile(type, file.name)
    return { file, data: json }
  }))
}
function createPostType() {
  postTypeEditorRef.value?.open()
}

function editPostType(module) {
  postTypeEditorRef.value?.open(module.data)
}

function createFieldGroup() {
  fieldGroupEditorRef.value?.open()
}

function editFieldGroup(fg) {
  fieldGroupEditorRef.value?.open(fg.data)
}

function createBlock() {
  blockEditorRef.value?.open()
}

function editBlock(block) {
  blockEditorRef.value?.open(block.data)
}

async function exportAll() {
  try {
    const response = await fetch('/_edit/admin-api/config/export', {
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
    showSuccess('Configuration exported successfully!')
  } catch (err) {
    showError(err.message || 'Export failed')
  }
}

async function handleImportAll(event) {
  const file = event.target.files[0]
  if (!file) return

  const confirmed = await confirmDialog('Import will replace existing configuration files with the same names. Continue?', {
    title: 'Import Configuration',
    variant: 'warning',
    confirmText: 'Import'
  })

  if (!confirmed) {
    event.target.value = ''
    return
  }

  const formData = new FormData()
  formData.append('file', file)

  try {
    const result = await apiRequest('POST', '/config/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    await handleSaved()

    const summaryParts = [
      `Imported ${result.imported.modules} modules, ${result.imported.field_groups} field groups, and ${result.imported.blocks} blocks.`,
      `Backup saved: ${result.backup}`
    ]

    if (result.errors && result.errors.length > 0) {
      showError(`Import completed with errors: ${result.errors.join(', ')}`)
    } else {
      showSuccess(summaryParts.join(' '))
    }
  } catch (err) {
    showError(err.message || 'Import failed')
  }

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
    await handleSaved()
    closeDeleteModal()
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

async function handleSaved() {
  await loadConfig()
  emit('reload')
}

const emit = defineEmits(['reload'])

onMounted(() => {
  loadConfig()
})
</script>
