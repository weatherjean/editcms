<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">{{ isCreating ? 'New' : 'Edit' }} {{ currentPostType?.label }}</h1>
        <p class="opacity-60 mt-1">{{ isCreating ? 'Create a new' : 'Edit this' }} {{ currentPostType?.label?.toLowerCase() }}</p>
      </div>
      <div class="join">
        <button type="button" @click="goBack" class="btn btn-neutral join-item">Cancel</button>
        <button type="submit" @click="saveContent" class="btn btn-primary join-item">Save</button>
      </div>
    </div>

    <form @submit.prevent="saveContent" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main content area (2/3) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Dynamic fields from field groups -->
        <div v-for="fieldGroup in assignedFieldGroups" :key="fieldGroup.id" class="card bg-base-100 border shadow">
          <div class="card-body space-y-6">
            <div>
              <h2 class="card-title">{{ fieldGroup.title }}</h2>
              <p v-if="fieldGroup.description" class="opacity-60 mt-1">{{ fieldGroup.description }}</p>
            </div>

            <div v-for="field in fieldGroup.fields" :key="field.key">
              <!-- Repeater Field -->
              <RepeaterField
                v-if="field.type === 'repeater'"
                v-model="form.fields[field.key]"
                :fields="field.config?.fields || []"
                @selectMedia="(subFieldKey, item) => openMediaModal(subFieldKey, item)"
              />

              <!-- All Other Fields -->
              <FieldRenderer
                v-else
                :field="field"
                v-model="form.fields[field.key]"
                :relationship-items="relationshipData[field.config?.post_type]"
                @selectMedia="openMediaModal(field.key)"
                @loadRelationship="loadRelationshipItems"
              >
                <!-- Repeater slot - not used since repeater is handled above -->
                <template #repeater></template>
              </FieldRenderer>
            </div>
          </div>
        </div>

        <!-- Flexible Content (if allow_open is true) -->
        <div v-if="currentPostType?.allow_open" class="card bg-base-100 border shadow">
          <div class="card-body">
            <FlexibleContentField
              v-model="form.fields.flexible_content"
              :available-blocks="availableBlocks"
              @selectMedia="(subFieldKey, item) => openMediaModal(subFieldKey, item)"
            />
          </div>
        </div>

        <div v-if="assignedFieldGroups.length === 0 && !currentPostType?.allow_open" class="card bg-base-100 border shadow">
          <div class="card-body items-center text-center py-16">
            <svg xmlns="http://www.w3.org/2000/svg" class="opacity-40 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold mb-2">No fields configured</h3>
            <p class="opacity-60">Add field groups to this post type in your configuration</p>
          </div>
        </div>
      </div>

      <!-- Sidebar (1/3) -->
      <div class="space-y-6">
        <ContentMetadata
          v-model:slug="form.slug"
          v-model:status="form.status"
        />
      </div>
    </form>

    <!-- Media Selection Modal -->
    <MediaModal
      ref="mediaModalRef"
      :media-items="mediaItems"
      :selected-media-id="currentMediaTarget ? currentMediaTarget[currentMediaFieldKey] : form.fields[currentMediaFieldKey]"
      @select="selectMediaItem"
      @close="closeMediaModal"
      @upload="uploadMediaFile"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../composables/useApi'
import { useMedia } from '../composables/useMedia'
import { useToast } from '../composables/useToast'
import FieldRenderer from '../components/FieldRenderer.vue'
import RepeaterField from '../components/RepeaterField.vue'
import MediaModal from '../components/MediaModal.vue'
import ContentMetadata from '../components/ContentMetadata.vue'
import FlexibleContentField from '../components/FlexibleContentField.vue'

const router = useRouter()
const route = useRoute()
const props = defineProps(['postTypes', 'fieldGroups'])

const { apiRequest } = useApi()
const { fetchMedia, uploadFile } = useMedia()
const { success, error } = useToast()

const currentType = computed(() => route.params.type)
const currentId = computed(() => route.params.id || null)

const form = ref({
  slug: '',
  status: 'draft',
  fields: {}
})

const mediaItems = ref([])
const mediaModalRef = ref(null)
const currentMediaFieldKey = ref(null)
const currentMediaTarget = ref(null) // For repeater items

const relationshipData = ref({})
const availableBlocks = ref([])

const isCreating = computed(() => !currentId.value)

const currentPostType = computed(() => {
  return props.postTypes.find(pt => pt.key === currentType.value)
})

const assignedFieldGroups = computed(() => {
  if (!currentType.value || !props.fieldGroups) return []

  return props.fieldGroups.filter(fg => {
    return fg.locations && fg.locations.includes(currentType.value)
  })
})

async function loadContentItem() {
  if (!currentId.value || !currentType.value) return

  try {
    const item = await apiRequest('GET', `/${currentType.value}/${currentId.value}`)

    // Parse fields - flexible_content comes as JSON string from API
    const parsedFields = { ...(item.fields || {}) }

    // Parse flexible_content if it's a string
    if (parsedFields.flexible_content && typeof parsedFields.flexible_content === 'string') {
      try {
        parsedFields.flexible_content = JSON.parse(parsedFields.flexible_content)
      } catch (e) {
        console.error('Failed to parse flexible_content:', e)
        parsedFields.flexible_content = []
      }
    }

    form.value = {
      slug: item.slug || '',
      status: item.status || 'draft',
      fields: parsedFields
    }

    // Ensure flexible_content exists if post type allows it
    if (currentPostType.value?.allow_open && !form.value.fields.flexible_content) {
      form.value.fields.flexible_content = []
    }

    // Load media items
    await loadMedia()
  } catch (error) {
    console.error('Failed to load content item:', error)
  }
}

async function resetForm() {
  const initializedFields = {}
  assignedFieldGroups.value.forEach(group => {
    group.fields.forEach(field => {
      initializedFields[field.key] = field.type === 'repeater' ? [] : ''
    })
  })

  if (currentPostType.value?.allow_open) {
    initializedFields.flexible_content = []
  }

  form.value = {
    slug: '',
    status: 'draft',
    fields: initializedFields
  }

  await loadMedia()
}

async function saveContent() {
  try {
    const method = isCreating.value ? 'POST' : 'PUT'
    const url = isCreating.value
      ? `/${currentType.value}`
      : `/${currentType.value}/${currentId.value}`

    const normalizedForm = { ...form.value }
    if (normalizedForm.fields) {
      normalizedForm.fields = normalizeMediaFields(normalizedForm.fields)
    }

    const result = await apiRequest(method, url, normalizedForm)

    if (isCreating.value && result.id) {
      router.push(`/${currentType.value}/${result.id}`)
    } else {
      await loadContentItem()
    }

    success(isCreating.value ? 'Created successfully!' : 'Saved successfully!')
  } catch (err) {
    error('Failed to save: ' + err.message)
  }
}

function normalizeMediaFields(fields) {
  const normalized = {}

  Object.keys(fields).forEach(key => {
    const value = fields[key]

    if (Array.isArray(value)) {
      normalized[key] = value.map(item => {
        if (typeof item === 'object' && item !== null) {
          return normalizeMediaFields(item)
        }
        return item
      })
    }
    else if (value && typeof value === 'object' && value.id && value.url) {
      normalized[key] = value.id
    }
    else {
      normalized[key] = value
    }
  })

  return normalized
}

function goBack() {
  router.push(`/${currentType.value}`)
}
async function loadMedia() {
  try {
    mediaItems.value = await fetchMedia()
  } catch (err) {
    console.error('Failed to load media:', err)
  }
}

function openMediaModal(fieldKey, target = null) {
  currentMediaFieldKey.value = fieldKey
  currentMediaTarget.value = target
  loadMedia()
  mediaModalRef.value?.open()
}

function closeMediaModal() {
  currentMediaFieldKey.value = null
  currentMediaTarget.value = null
}

function selectMediaItem(mediaId) {
  if (currentMediaFieldKey.value) {
    const mediaObject = mediaItems.value.find(m => m.id === mediaId)

    if (mediaObject) {
      if (currentMediaTarget.value) {
        currentMediaTarget.value[currentMediaFieldKey.value] = mediaObject
      } else {
        form.value.fields[currentMediaFieldKey.value] = mediaObject
      }
    }
  }
}

async function uploadMediaFile(file) {
  if (!file) return

  try {
    await uploadFile(file)
    await loadMedia()
  } catch (err) {
    error('Upload failed: ' + err.message)
    console.error('Upload error:', err)
  }
}
async function loadRelationshipItems(postType) {
  if (!postType) return

  if (relationshipData.value[postType]) return

  try {
    const items = await apiRequest('GET', `/${postType}`)
    relationshipData.value[postType] = items
  } catch (error) {
    console.error(`Failed to load ${postType}:`, error)
    relationshipData.value[postType] = []
  }
}
async function loadBlocks() {
  try {
    availableBlocks.value = await apiRequest('GET', '/blocks')
  } catch (error) {
    console.error('Failed to load blocks:', error)
    availableBlocks.value = []
  }
}

watch(currentId, async () => {
  if (isCreating.value) {
    await resetForm()
  } else {
    await loadContentItem()
  }
}, { immediate: true })

onMounted(async () => {
  // Load blocks first so they're available when rendering
  await loadBlocks()

  if (isCreating.value) {
    await resetForm()
  } else {
    await loadContentItem()
  }
})
</script>
