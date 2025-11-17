<template>
  <dialog ref="dialogRef" class="modal">
    <div class="modal-box max-w-6xl max-h-[90vh] overflow-y-auto">
      <form method="dialog">
        <button class="btn btn-circle btn-ghost absolute right-2 top-2"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
      </form>

      <h3 class="font-bold text-lg mb-4">{{ isEditing ? 'Edit' : 'Create' }} Post Type</h3>

      <form @submit.prevent="save" class="space-y-6">
        <!-- Basic Info -->
        <div class="card bg-base-200">
          <div class="card-body">
            <h4 class="font-semibold mb-3">Basic Information</h4>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Key *</legend>
              <input
                type="text"
                v-model="form.key"
                :disabled="isEditing"
                class="input w-full"
                placeholder="post"
                pattern="[a-z0-9_]+"
                required
              >
              <p class="opacity-60 mt-1">Lowercase with underscores only</p>
            </fieldset>

            <div class="grid grid-cols-2 gap-4">
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Label (Singular) *</legend>
                <input
                  type="text"
                  v-model="form.label"
                  class="input w-full"
                  placeholder="Post"
                  required
                >
              </fieldset>

              <fieldset class="fieldset">
                <legend class="fieldset-legend">Label (Plural) *</legend>
                <input
                  type="text"
                  v-model="form.label_plural"
                  class="input w-full"
                  placeholder="Posts"
                  required
                >
              </fieldset>
            </div>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Description</legend>
              <textarea
                v-model="form.description"
                class="textarea w-full"
                rows="2"
                placeholder="Blog posts and articles"
              ></textarea>
            </fieldset>

            <div class="grid grid-cols-2 gap-4">
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Icon</legend>
                <input
                  type="text"
                  v-model="form.icon"
                  class="input w-full"
                  placeholder="file-text"
                >
              </fieldset>

              <fieldset class="fieldset">
                <legend class="fieldset-legend">Options</legend>
                <label class="label cursor-pointer justify-start gap-4">
                  <input type="checkbox" v-model="form.allow_open" class="toggle">
                  <span class="label-text">Allow Flexible Content (Blocks)</span>
                </label>
              </fieldset>
            </div>
          </div>
        </div>

        <!-- Field Groups -->
        <div class="card bg-base-200">
          <div class="card-body">
            <div class="flex items-center justify-between mb-3">
              <h4 class="font-semibold">Field Groups</h4>
              <button type="button" @click="addFieldGroup" class="btn btn-outline">
                + Add Field Group
              </button>
            </div>

            <p class="opacity-60 mb-4">
              Define field groups specific to this post type.
              For reusable field groups (like SEO), create them separately in the Field Groups section.
            </p>

            <!-- Field Group List -->
            <div v-if="form.field_groups.length > 0" class="space-y-4">
              <div v-for="(group, groupIndex) in form.field_groups" :key="groupIndex" class="card bg-base-100 border">
                <div class="card-body">
                  <div class="flex items-center justify-between mb-3">
                    <h5 class="font-semibold">{{ group.title || 'Untitled Field Group' }}</h5>
                    <div class="join">
                      <button
                        type="button"
                        @click="editingGroupIndex = editingGroupIndex === groupIndex ? null : groupIndex"
                        class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                        :class="{ 'btn-active': editingGroupIndex === groupIndex }"
                        :data-tip="editingGroupIndex === groupIndex ? 'Collapse' : 'Expand'"
                      >
                        <IconChevronUp v-if="editingGroupIndex === groupIndex" />
                        <IconChevronDown v-else />
                      </button>
                      <button
                        type="button"
                        @click="removeFieldGroup(groupIndex)"
                        class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left"
                        data-tip="Remove"
                      >
                        <IconTrash />
                      </button>
                    </div>
                  </div>

                  <div v-if="editingGroupIndex === groupIndex" class="space-y-4">
                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Key *</legend>
                      <input
                        type="text"
                        v-model="group.key"
                        class="input w-full"
                        placeholder="post_content"
                        pattern="[a-z0-9_]+"
                        required
                      >
                    </fieldset>

                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Title *</legend>
                      <input
                        type="text"
                        v-model="group.title"
                        class="input w-full"
                        placeholder="Post Content"
                        required
                      >
                    </fieldset>

                    <fieldset class="fieldset">
                      <legend class="fieldset-legend">Description</legend>
                      <textarea
                        v-model="group.description"
                        class="textarea w-full"
                        rows="2"
                        placeholder="Main content fields for posts"
                      ></textarea>
                    </fieldset>

                    <div class="divider">Fields</div>

                    <FieldBuilder v-model="group.fields" :available-post-types="allPostTypes" />
                  </div>

                  <div v-else class="opacity-60">
                    {{ group.fields?.length || 0 }} field(s) - Click expand to edit
                  </div>
                </div>
              </div>
            </div>

            <!-- Empty State -->
            <div v-else class="card bg-base-100 border-2 border-dashed">
              <div class="card-body items-center text-center py-8">
                <p class="opacity-60">No field groups added yet</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-4">
          <button type="button" @click="close" class="btn btn-neutral">Cancel</button>
          <button type="submit" class="btn btn-primary">{{ isEditing ? 'Save Changes' : 'Create Post Type' }}</button>
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
import { useConfirm } from '../composables/useConfirm'
import IconChevronUp from './icons/IconChevronUp.vue'
import IconChevronDown from './icons/IconChevronDown.vue'
import IconTrash from './icons/IconTrash.vue'

const props = defineProps({
  allPostTypes: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['saved'])

const { apiRequest } = useApi()
const { error: showError } = useToast()
const { confirm: confirmDialog } = useConfirm()

const dialogRef = ref(null)
const form = ref({
  key: '',
  label: '',
  label_plural: '',
  description: '',
  icon: '',
  allow_open: false,
  field_groups: []
})
const originalKey = ref(null)
const editingGroupIndex = ref(null)

const isEditing = computed(() => !!originalKey.value)

function open(module = null) {
  if (module) {
    const postType = module.post_types[0]
    form.value = {
      key: postType.key,
      label: postType.label,
      label_plural: postType.label_plural,
      description: postType.description || '',
      icon: postType.icon || '',
      allow_open: postType.allow_open || false,
      field_groups: JSON.parse(JSON.stringify(module.field_groups || []))
    }
    form.value.field_groups.forEach(fg => {
      fg.locations = [postType.key]
    })
    originalKey.value = postType.key
  } else {
    form.value = {
      key: '',
      label: '',
      label_plural: '',
      description: '',
      icon: '',
      allow_open: false,
      field_groups: []
    }
    originalKey.value = null
  }
  editingGroupIndex.value = null
  dialogRef.value?.showModal()
}

function close() {
  dialogRef.value?.close()
}

function addFieldGroup() {
  form.value.field_groups.push({
    key: '',
    title: '',
    description: '',
    locations: [form.value.key],
    fields: []
  })
  editingGroupIndex.value = form.value.field_groups.length - 1
}

async function removeFieldGroup(index) {
  const confirmed = await confirmDialog('Are you sure you want to remove this field group?', {
    title: 'Remove Field Group',
    variant: 'warning'
  })

  if (confirmed) {
    form.value.field_groups.splice(index, 1)
    if (editingGroupIndex.value === index) {
      editingGroupIndex.value = null
    }
  }
}

async function save() {
  try {
    if (!form.value.key || !form.value.label || !form.value.label_plural) {
      showError('Please fill in required fields')
      return
    }

    const module = {
      post_types: [
        {
          key: form.value.key,
          label: form.value.label,
          label_plural: form.value.label_plural,
          description: form.value.description,
          icon: form.value.icon,
          allow_open: form.value.allow_open
        }
      ],
      field_groups: form.value.field_groups.map(fg => ({
        key: fg.key,
        title: fg.title,
        description: fg.description,
        locations: [form.value.key],
        fields: fg.fields
      }))
    }

    const blob = new Blob([JSON.stringify(module, null, 2)], { type: 'application/json' })
    const file = new File([blob], `${form.value.key}.json`, { type: 'application/json' })

    const formData = new FormData()
    formData.append('file', file)

    const token = localStorage.getItem('edit_token')
    const response = await fetch('/_edit/admin-api/config/modules', {
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
