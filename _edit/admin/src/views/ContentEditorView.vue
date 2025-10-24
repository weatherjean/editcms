<template>
  <div class="view-container">
    <div class="view-header">
      <h2>
        {{ isCreating ? 'New' : 'Edit' }} {{ currentPostType?.label }}
      </h2>
      <button @click="goBack" class="btn btn-secondary">Back</button>
    </div>

    <form @submit.prevent="saveContent" class="content-form">
      <div class="form-group">
        <label>Slug *</label>
        <input type="text" v-model="form.slug" required placeholder="my-unique-slug">
        <small class="field-help">URL-friendly identifier (e.g., "my-post-name")</small>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select v-model="form.status">
          <option value="draft">Draft</option>
          <option value="published">Published</option>
        </select>
      </div>

      <hr>

      <!-- Dynamic fields from field groups -->
      <div v-for="fieldGroup in assignedFieldGroups" :key="fieldGroup.id" class="field-group-section">
        <h3 class="field-group-title">{{ fieldGroup.title }}</h3>
        <p v-if="fieldGroup.description" class="field-group-description">{{ fieldGroup.description }}</p>

        <div v-for="field in fieldGroup.fields" :key="field.key" class="form-group">
          <label>
            <span>{{ field.label }}</span>
            <span v-if="field.required" class="required">*</span>
          </label>
          <p v-if="field.instructions" class="field-instructions">{{ field.instructions }}</p>

          <!-- Text Field -->
          <input v-if="field.type === 'text'"
                 type="text"
                 v-model="form.fields[field.key]"
                 :required="field.required">

          <!-- Textarea Field -->
          <textarea v-else-if="field.type === 'textarea'"
                    v-model="form.fields[field.key]"
                    :required="field.required"
                    rows="5"></textarea>

          <!-- Number Field -->
          <input v-else-if="field.type === 'number'"
                 type="number"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 :min="field.config?.min"
                 :max="field.config?.max"
                 :step="field.config?.step || '1'">

          <!-- Boolean Field -->
          <label v-else-if="field.type === 'boolean'" class="checkbox-label">
            <input type="checkbox" v-model="form.fields[field.key]">
            <span>{{ field.label }}</span>
          </label>

          <!-- Select Field -->
          <select v-else-if="field.type === 'select'"
                  v-model="form.fields[field.key]"
                  :required="field.required">
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
                 :required="field.required">

          <!-- DateTime Field -->
          <input v-else-if="field.type === 'datetime'"
                 type="datetime-local"
                 v-model="form.fields[field.key]"
                 :required="field.required">

          <!-- Slug Field -->
          <input v-else-if="field.type === 'slug'"
                 type="text"
                 v-model="form.fields[field.key]"
                 :required="field.required"
                 pattern="[a-z0-9\-]+"
                 placeholder="lowercase-with-hyphens">

          <!-- WYSIWYG Field -->
          <div v-else-if="field.type === 'wysiwyg'">
            <textarea v-model="form.fields[field.key]"
                      :required="field.required"
                      rows="10"></textarea>
            <small>Rich text editor (TinyMCE would initialize here)</small>
          </div>

          <!-- Media Field -->
          <div v-else-if="field.type === 'media'">
            <button type="button" @click="selectMedia(field.key)" class="btn btn-secondary">
              Select Media
            </button>
            <div v-if="form.fields[field.key]" class="media-preview">
              <p>Media ID: {{ form.fields[field.key] }}</p>
            </div>
          </div>

          <!-- Relationship Field -->
          <select v-else-if="field.type === 'relationship'"
                  v-model="form.fields[field.key]"
                  :required="field.required">
            <option value="">-- Select --</option>
            <option value="related-1">Related Item 1 (TODO: Load dynamically)</option>
          </select>

          <!-- Repeater Field -->
          <div v-else-if="field.type === 'repeater'">
            <p class="help-text">Repeater fields coming soon</p>
          </div>

          <!-- Fallback for unknown types -->
          <p v-else class="help-text">Unknown field type: {{ field.type }}</p>
        </div>
      </div>

      <p v-if="assignedFieldGroups.length === 0" class="help-text">
        No field groups assigned to this post type yet.
      </p>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" @click="goBack" class="btn btn-secondary">Cancel</button>
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

<style scoped>
.field-help {
  display: block;
  margin-top: 4px;
  color: #6c757d;
  font-size: 13px;
}

code {
  padding: 2px 6px;
  background: #f8f9fa;
  border-radius: 3px;
  font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
  font-size: 13px;
  color: #495057;
}
</style>
