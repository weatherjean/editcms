<template>
  <dialog ref="dialogRef" class="modal">
    <div class="modal-box max-w-4xl">
      <form method="dialog">
        <button class="btn btn-circle btn-ghost absolute right-2 top-2"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
      </form>

      <h3 class="font-bold text-lg mb-4">{{ isEditing ? 'Edit' : 'Create' }} Block</h3>

      <form @submit.prevent="save" class="space-y-4">
        <!-- Basic Info -->
        <div class="grid grid-cols-2 gap-4">
          <fieldset class="fieldset">
            <legend class="fieldset-legend">Key *</legend>
            <input
              type="text"
              v-model="form.key"
              :disabled="isEditing"
              class="input w-full"
              placeholder="hero_section"
              pattern="[a-z0-9_]+"
              required
            >
            <p class="opacity-60 mt-1">Lowercase with underscores only</p>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Icon</legend>
            <input
              type="text"
              v-model="form.icon"
              class="input w-full"
              placeholder=""
              maxlength="2"
            >
          </fieldset>
        </div>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Label *</legend>
          <input
            type="text"
            v-model="form.label"
            class="input w-full"
            placeholder="Hero Section"
            required
          >
        </fieldset>

        <fieldset class="fieldset">
          <legend class="fieldset-legend">Description</legend>
          <textarea
            v-model="form.description"
            class="textarea w-full"
            rows="2"
            placeholder="Large hero banner with image and text"
          ></textarea>
        </fieldset>

        <!-- Fields -->
        <div class="divider">Fields</div>

        <FieldBuilder
          v-model="form.fields"
          :available-post-types="availablePostTypes"
        />

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-4">
          <button type="button" @click="close" class="btn btn-neutral">Cancel</button>
          <button type="submit" class="btn btn-primary">{{ isEditing ? 'Save Changes' : 'Create Block' }}</button>
        </div>
      </form>
    </div>
    <form method="dialog" class="modal-backdrop">
      <button>close</button>
    </form>
  </dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import FieldBuilder from './FieldBuilder.vue'
import { useApi } from '../composables/useApi'
import { useToast } from '../composables/useToast'

const props = defineProps({
  availablePostTypes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['saved'])

const { apiRequest } = useApi()
const { error: showError } = useToast()

const dialogRef = ref(null)
const form = ref({
  key: '',
  label: '',
  description: '',
  icon: '',
  fields: []
})
const originalKey = ref(null)

const isEditing = computed(() => !!originalKey.value)

function open(block = null) {
  if (block) {
    form.value = {
      key: block.key,
      label: block.label,
      description: block.description || '',
      icon: block.icon || '',
      fields: JSON.parse(JSON.stringify(block.fields || []))
    }
    originalKey.value = block.key
  } else {
    form.value = {
      key: '',
      label: '',
      description: '',
      icon: '',
      fields: []
    }
    originalKey.value = null
  }
  dialogRef.value?.showModal()
}

function close() {
  dialogRef.value?.close()
}

async function save() {
  try {
    if (!form.value.key || !form.value.label || form.value.fields.length === 0) {
      showError('Please fill in required fields and add at least one field')
      return
    }

    const blob = new Blob([JSON.stringify(form.value, null, 2)], { type: 'application/json' })
    const file = new File([blob], `${form.value.key}.json`, { type: 'application/json' })

    const formData = new FormData()
    formData.append('file', file)

    const token = localStorage.getItem('edit_token')
    const response = await fetch('/_edit/admin-api/config/blocks', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ error: 'Save failed' }))
      throw new Error(error.error || 'Save failed')
    }

    emit('saved')
    close()
  } catch (error) {
    showError('Failed to save: ' + error.message)
  }
}

defineExpose({ open })
</script>
