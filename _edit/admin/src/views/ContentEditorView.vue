<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">{{ isCreating ? 'New' : 'Edit' }} {{ currentPostType?.label }}</h1>
        <p class="text-sm opacity-60 mt-1">{{ isCreating ? 'Create a new' : 'Edit this' }} {{ currentPostType?.label?.toLowerCase() }}</p>
      </div>
      <div class="join">
        <button type="button" @click="goBack" class="btn btn-neutral join-item">Cancel</button>
        <button type="submit" @click="saveContent" class="btn btn-success join-item">Save</button>
      </div>
    </div>

    <form @submit.prevent="saveContent" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main content area (2/3) -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Dynamic fields from field groups -->
        <div v-for="fieldGroup in assignedFieldGroups" :key="fieldGroup.id" class="card bg-base-100 card-border border-base-300 card-sm shadow">
          <div class="card-body">
            <h2 class="card-title text-xl">{{ fieldGroup.title }}</h2>
            <p v-if="fieldGroup.description" class="text-base opacity-60">{{ fieldGroup.description }}</p>

            <div class="divider my-4"></div>

            <div class="space-y-6">
              <div v-for="(field, index) in fieldGroup.fields" :key="field.key">
                <div class="form-control w-full">
                  <label class="label">
                    <span class="label-text text-base font-medium">
                      {{ field.label }}
                      <span v-if="field.required" class="text-error ml-1">*</span>
                    </span>
                  </label>
                  <label v-if="field.instructions" class="label pt-0">
                    <span class="label-text-alt text-sm opacity-60">{{ field.instructions }}</span>
                  </label>

          <!-- Text Field -->
          <input v-if="field.type === 'text'"
                 type="text"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 class="input input-bordered w-full text-base">

          <!-- Textarea Field -->
          <textarea v-else-if="field.type === 'textarea'"
                    v-model="form.fields[field.key]"
                    :required="field.required"
                    rows="5"
                    class="textarea textarea-bordered w-full text-base"></textarea>

          <!-- Number Field -->
          <input v-else-if="field.type === 'number'"
                 type="number"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 :min="field.config?.min"
                 :max="field.config?.max"
                 :step="field.config?.step || '1'"
                 class="input input-bordered w-full text-base">

          <!-- Boolean Field -->
          <div v-else-if="field.type === 'boolean'" class="form-control">
            <label class="label cursor-pointer justify-start gap-4">
              <input type="checkbox" v-model="form.fields[field.key]" class="checkbox">
              <span class="label-text">{{ field.label }}</span>
            </label>
          </div>

          <!-- Select Field -->
          <select v-else-if="field.type === 'select'"
                  v-model="form.fields[field.key]"
                  :required="field.required"
                  class="select select-bordered w-full text-base">
            <option value="">-- Select --</option>
            <option v-for="choice in parseSelectChoices(field.config?.choices)"
                    :key="choice.value"
                    :value="choice.value">
              {{ choice.label }}
            </option>
          </select>

          <!-- Date Field -->
          <input v-else-if="field.type === 'date'"
                 type="date"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 class="input input-bordered w-full text-base">

          <!-- DateTime Field -->
          <input v-else-if="field.type === 'datetime'"
                 type="datetime-local"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 class="input input-bordered w-full text-base">

          <!-- Slug Field -->
          <input v-else-if="field.type === 'slug'"
                 type="text"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 pattern="[a-z0-9\-]+"
                 placeholder="lowercase-with-hyphens"
                 class="input input-bordered w-full text-base">

          <!-- WYSIWYG Field -->
          <div v-else-if="field.type === 'wysiwyg'">
            <textarea v-model="form.fields[field.key]"
                      :required="field.required"
                      rows="10"
                      class="textarea textarea-bordered w-full text-base"></textarea>
            <label class="label pt-0">
              <span class="label-text-alt text-sm opacity-70">Rich text editor (TinyMCE would initialize here)</span>
            </label>
          </div>

          <!-- Media Field -->
          <div v-else-if="field.type === 'media'">
            <button type="button" @click="selectMedia(field.key)" class="btn btn-outline text-base">
              Select Media
            </button>
            <div v-if="form.fields[field.key]" class="mt-2 p-2 bg-base-200 rounded">
              <p class="text-sm">Media ID: {{ form.fields[field.key] }}</p>
            </div>
          </div>

          <!-- Relationship Field -->
          <select v-else-if="field.type === 'relationship'"
                  v-model="form.fields[field.key]"
                  :required="field.required"
                  class="select select-bordered w-full text-base">
            <option value="">-- Select --</option>
            <option value="related-1">Related Item 1 (TODO: Load dynamically)</option>
          </select>

          <!-- Repeater Field -->
          <div v-else-if="field.type === 'repeater'">
            <div class="alert alert-info">
              <span class="text-sm">Repeater fields coming soon</span>
            </div>
          </div>

                <!-- Fallback for unknown types -->
                <div v-else class="alert alert-warning">
                  <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                  <span class="text-sm">Unknown field type: {{ field.type }}</span>
                </div>
                </div>
                <div v-if="index < fieldGroup.fields.length - 1" class="divider my-4"></div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="assignedFieldGroups.length === 0" class="card bg-base-100 card-border border-base-300 shadow">
          <div class="card-body items-center text-center py-16">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 opacity-40 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold mb-2">No fields configured</h3>
            <p class="opacity-60">Add field groups to this post type in your configuration</p>
          </div>
        </div>
      </div>

      <!-- Sidebar (1/3) -->
      <div class="space-y-6">
        <div class="card bg-base-100 card-border border-base-300 card-sm shadow">
          <div class="card-body gap-4">
            <h2 class="flex items-center gap-2 font-semibold">Publish</h2>

            <div class="divider my-4"></div>

            <div class="form-control w-full">
              <label class="label">
                <span class="label-text text-base font-medium">Slug <span class="text-error">*</span></span>
              </label>
              <input
                type="text"
                v-model="form.slug"
                required
                placeholder="my-unique-slug"
                class="input input-bordered w-full"
              />
              <label class="label pt-0">
                <span class="label-text-alt text-sm opacity-60">URL-friendly identifier</span>
              </label>
            </div>

            <div class="divider my-4"></div>

            <div class="form-control w-full">
              <label class="label">
                <span class="label-text text-base font-medium">Status</span>
              </label>
              <select v-model="form.status" class="select select-bordered w-full text-base">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const props = defineProps(['currentType', 'currentId', 'postTypes', 'fieldGroups'])
const emit = defineEmits(['navigate'])

const { apiRequest } = useApi()

const form = ref({
  slug: '',
  status: 'draft',
  fields: {}
})

const isCreating = computed(() => !props.currentId)

const currentPostType = computed(() => {
  return props.postTypes.find(pt => pt.key === props.currentType)
})

const assignedFieldGroups = computed(() => {
  if (!props.currentType || !props.fieldGroups) return []

  return props.fieldGroups.filter(fg => {
    return fg.locations && fg.locations.includes(props.currentType)
  })
})

async function loadContentItem() {
  if (!props.currentId || !props.currentType) return

  try {
    const item = await apiRequest('GET', `/${props.currentType}/${props.currentId}`)
    form.value = {
      slug: item.slug || '',
      status: item.status || 'draft',
      fields: item.fields || {}
    }
  } catch (error) {
    console.error('Failed to load content item:', error)
  }
}

function resetForm() {
  form.value = {
    slug: '',
    status: 'draft',
    fields: {}
  }
}

async function saveContent() {
  try {
    const method = isCreating.value ? 'POST' : 'PUT'
    const url = isCreating.value
      ? `/${props.currentType}`
      : `/${props.currentType}/${props.currentId}`

    await apiRequest(method, url, form.value)
    goBack()
  } catch (error) {
    alert('Failed to save: ' + error.message)
  }
}

function goBack() {
  emit('navigate', 'content-list', props.currentType)
}

function parseSelectChoices(choices) {
  if (!choices) return []

  // If it's already an object, convert to array
  if (typeof choices === 'object' && !Array.isArray(choices)) {
    return Object.entries(choices).map(([value, label]) => ({
      value,
      label
    }))
  }

  // If it's a string (legacy format), parse it
  if (typeof choices === 'string') {
    return choices.split('\n')
      .filter(line => line.trim())
      .map(line => {
        const [value, label] = line.split(':').map(s => s.trim())
        return {
          value: value || label,
          label: label || value
        }
      })
  }

  return []
}

function selectMedia(fieldKey) {
  alert('Media selector coming soon! For now, enter a media ID directly.')
}

// Watch for changes
watch(() => props.currentId, () => {
  if (isCreating.value) {
    resetForm()
  } else {
    loadContentItem()
  }
}, { immediate: true })

onMounted(() => {
  if (isCreating.value) {
    resetForm()
  } else {
    loadContentItem()
  }
})
</script>
