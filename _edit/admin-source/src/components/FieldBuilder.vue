<template>
  <div class="space-y-4">
    <!-- Field List -->
    <div v-if="fields.length > 0" class="space-y-3">
      <div v-for="(field, index) in fields" :key="index" class="card bg-base-200 border">
        <div class="card-body p-4 space-y-3">
          <!-- Field Header -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="badge badge-sm">{{ field.type || 'unknown' }}</span>
              <span class="font-semibold">{{ field.label || field.key || 'Untitled Field' }}</span>
            </div>
            <div class="join">
              <button
                type="button"
                @click="moveField(index, -1)"
                :disabled="index === 0"
                class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                data-tip="Move Up"
              >
                <IconChevronUp />
              </button>
              <button
                type="button"
                @click="moveField(index, 1)"
                :disabled="index === fields.length - 1"
                class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                data-tip="Move Down"
              >
                <IconChevronDown />
              </button>
              <button
                type="button"
                @click="editingIndex = editingIndex === index ? null : index"
                class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                :class="{ 'btn-active': editingIndex === index }"
                data-tip="Edit"
              >
                <IconEdit />
              </button>
              <button
                type="button"
                @click="removeField(index)"
                class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left"
                data-tip="Remove"
              >
                <IconTrash />
              </button>
            </div>
          </div>

          <!-- Field Edit Form (collapsible) -->
          <div v-if="editingIndex === index" class="space-y-3 pt-3 border-t">
            <div class="grid grid-cols-2 gap-3">
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Key *</legend>
                <input type="text" v-model="field.key" class="input w-full" placeholder="field_key" required>
              </fieldset>

              <fieldset class="fieldset">
                <legend class="fieldset-legend">Label *</legend>
                <input type="text" v-model="field.label" class="input w-full" placeholder="Field Label" required>
              </fieldset>
            </div>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Type *</legend>
              <select v-model="field.type" class="select w-full" required>
                <option value="">-- Select Type --</option>
                <option value="text">Text</option>
                <option value="textarea">Textarea</option>
                <option value="wysiwyg">WYSIWYG</option>
                <option value="number">Number</option>
                <option value="boolean">Boolean</option>
                <option value="select">Select</option>
                <option value="date">Date</option>
                <option value="datetime">DateTime</option>
                <option value="slug">Slug</option>
                <option value="media">Media</option>
                <option value="relationship">Relationship</option>
                <option value="repeater">Repeater</option>
              </select>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Instructions</legend>
              <textarea v-model="field.instructions" class="textarea w-full" rows="2" placeholder="Help text for this field"></textarea>
            </fieldset>

            <div class="flex gap-4">
              <label class="label cursor-pointer gap-4">
                <input type="checkbox" v-model="field.required" class="toggle">
                <span class="label-text">Required</span>
              </label>
            </div>

            <!-- Type-specific config -->
            <div v-if="field.type === 'select'" class="space-y-2">
              <label class="label">
                <span class="label-text font-semibold">Choices</span>
              </label>
              <textarea
                v-model="selectChoicesText[index]"
                @input="updateSelectChoices(index)"
                class="textarea w-full font-mono"
                rows="4"
                placeholder="value1 : Label 1&#10;value2 : Label 2&#10;value3 : Label 3"
              ></textarea>
              <p class="opacity-60">Format: value : Label (one per line)</p>
            </div>

            <div v-if="field.type === 'number'" class="grid grid-cols-3 gap-2">
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Min</legend>
                <input type="number" v-model.number="field.config.min" class="input w-full">
              </fieldset>
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Max</legend>
                <input type="number" v-model.number="field.config.max" class="input w-full">
              </fieldset>
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Step</legend>
                <input type="number" v-model.number="field.config.step" class="input w-full" placeholder="1">
              </fieldset>
            </div>

            <div v-if="field.type === 'relationship'">
              <fieldset class="fieldset">
                <legend class="fieldset-legend">Post Type</legend>
                <select v-model="field.config.post_type" class="select w-full">
                  <option value="">-- Select Post Type --</option>
                  <option v-for="pt in availablePostTypes" :key="pt.key" :value="pt.key">
                    {{ pt.label }}
                  </option>
                </select>
              </fieldset>
            </div>

            <div v-if="field.type === 'media'">
              <label class="label cursor-pointer gap-4">
                <input type="checkbox" v-model="field.config.multiple" class="toggle">
                <span class="label-text">Allow Multiple</span>
              </label>
            </div>

            <div v-if="field.type === 'repeater'" class="space-y-2">
              <label class="label">
                <span class="label-text font-semibold">Sub-fields</span>
              </label>
              <div class="pl-4 border-l-2 border-base-300">
                <FieldBuilder
                  v-model="field.config.fields"
                  :available-post-types="availablePostTypes"
                  :depth="depth + 1"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card bg-base-100 border-2 border-dashed">
      <div class="card-body items-center text-center py-8">
        <p class="opacity-60">No fields added yet</p>
      </div>
    </div>

    <!-- Add Field Button -->
    <button
      type="button"
      @click="addField"
      class="btn btn-outline w-full"
      :disabled="depth > 2"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
      </svg>
      Add Field
    </button>
    <p v-if="depth > 2" class="text-center opacity-60">Maximum nesting depth reached</p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useConfirm } from '../composables/useConfirm'
import IconChevronUp from './icons/IconChevronUp.vue'
import IconChevronDown from './icons/IconChevronDown.vue'
import IconEdit from './icons/IconEdit.vue'
import IconTrash from './icons/IconTrash.vue'

const { confirm: confirmDialog } = useConfirm()

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  availablePostTypes: {
    type: Array,
    default: () => []
  },
  depth: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['update:modelValue'])

const fields = ref(props.modelValue || [])
const editingIndex = ref(null)
const selectChoicesText = ref({})

watch(() => props.modelValue, (newVal) => {
  fields.value = newVal || []
  fields.value.forEach((field, index) => {
    if (field.type === 'select' && field.config?.choices) {
      selectChoicesText.value[index] = formatSelectChoices(field.config.choices)
    }
  })
}, { immediate: true })

watch(fields, (newVal) => {
  emit('update:modelValue', newVal)
}, { deep: true })

function addField() {
  const newField = {
    key: '',
    label: '',
    type: 'text',
    instructions: '',
    required: false,
    config: {}
  }
  fields.value.push(newField)
  editingIndex.value = fields.value.length - 1
}

async function removeField(index) {
  const confirmed = await confirmDialog('Are you sure you want to remove this field?', {
    title: 'Remove Field',
    variant: 'warning'
  })

  if (confirmed) {
    fields.value.splice(index, 1)
    if (editingIndex.value === index) {
      editingIndex.value = null
    }
  }
}

function moveField(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= fields.value.length) return

  const temp = fields.value[index]
  fields.value[index] = fields.value[newIndex]
  fields.value[newIndex] = temp

  if (editingIndex.value === index) {
    editingIndex.value = newIndex
  } else if (editingIndex.value === newIndex) {
    editingIndex.value = index
  }
}

function formatSelectChoices(choices) {
  if (typeof choices === 'object' && !Array.isArray(choices)) {
    return Object.entries(choices)
      .map(([value, label]) => `${value} : ${label}`)
      .join('\n')
  }
  return ''
}

function updateSelectChoices(index) {
  const field = fields.value[index]
  if (!field || field.type !== 'select') return

  const text = selectChoicesText.value[index] || ''
  const choices = {}

  text.split('\n').forEach(line => {
    const trimmed = line.trim()
    if (!trimmed) return

    const [value, label] = trimmed.split(':').map(s => s.trim())
    if (value) {
      choices[value] = label || value
    }
  })

  if (!field.config) field.config = {}
  field.config.choices = choices
}
</script>
