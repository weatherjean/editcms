<template>
  <div class="space-y-4">
    <!-- Repeater Label -->
    <div v-if="label">
      <div class="fieldset-legend">{{ label }}</div>
      <p v-if="instructions" class="opacity-60 mb-2">{{ instructions }}</p>
    </div>

    <!-- Repeater Items -->
    <div v-for="(item, index) in items" :key="index" class="card bg-base-200 border">
      <div class="card-body p-4">
        <div class="flex items-center justify-between mb-4">
          <h4 class="font-semibold">Item #{{ index + 1 }}</h4>
          <div class="join">
            <button v-if="index > 0" type="button" @click="moveItem(index, -1)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Move up">
              <IconChevronUp />
            </button>
            <button v-if="index < items.length - 1" type="button" @click="moveItem(index, 1)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Move down">
              <IconChevronDown />
            </button>
            <button type="button" @click="removeItem(index)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
              <IconTrash />
            </button>
          </div>
        </div>

        <!-- Sub-fields -->
        <div class="space-y-4">
          <div v-for="subField in fields" :key="subField.key">
            <!-- Media Field - special handling for size -->
            <fieldset v-if="subField.type === 'media'" class="fieldset">
              <legend class="fieldset-legend">{{ subField.label }}</legend>
              <MediaField
                :model-value="item[subField.key]"
                :multiple="subField.config?.multiple || false"
                size="small"
                @select="$emit('selectMedia', subField.key, item, subField)"
                @remove="item[subField.key] = null"
              />
            </fieldset>

            <!-- Nested Repeater Field - recursive rendering -->
            <RepeaterField
              v-else-if="subField.type === 'repeater'"
              v-model="item[subField.key]"
              :fields="subField.config?.fields || []"
              :relationship-data="relationshipData"
              :label="subField.label"
              :instructions="subField.instructions"
              @selectMedia="(nestedFieldKey, nestedItem, nestedField) => $emit('selectMedia', nestedFieldKey, nestedItem, nestedField)"
              @loadRelationship="(postType) => $emit('loadRelationship', postType)"
            />

            <!-- All Other Fields -->
            <FieldRenderer
              v-else
              :field="subField"
              v-model="item[subField.key]"
              :relationship-items="relationshipData[subField.config?.post_type]"
              @selectMedia="$emit('selectMedia', subField.key, item, subField)"
              @loadRelationship="(postType) => $emit('loadRelationship', postType)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Add New Item Button -->
    <button type="button" @click="addItem" class="btn btn-outline w-full">
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
      </svg>
      Add Item
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FieldRenderer from './FieldRenderer.vue'
import MediaField from './MediaField.vue'
import IconChevronUp from './icons/IconChevronUp.vue'
import IconChevronDown from './icons/IconChevronDown.vue'
import IconTrash from './icons/IconTrash.vue'
import { useConfirm } from '../composables/useConfirm'

const { confirm: confirmDialog } = useConfirm()

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  fields: {
    type: Array,
    default: () => []
  },
  relationshipData: {
    type: Object,
    default: () => ({})
  },
  label: {
    type: String,
    default: ''
  },
  instructions: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'selectMedia', 'loadRelationship'])

const items = computed({
  get: () => props.modelValue || [],
  set: (value) => emit('update:modelValue', value)
})

function addItem() {
  const newItem = {}
  props.fields.forEach(field => {
    // Initialize repeater fields as arrays, others as empty strings
    if (field.type === 'repeater') {
      newItem[field.key] = []
    } else {
      newItem[field.key] = field.default_value !== undefined ? field.default_value : ''
    }
  })
  items.value = [...items.value, newItem]
}

async function removeItem(index) {
  const confirmed = await confirmDialog('Are you sure you want to delete this item?', {
    title: 'Delete Item',
    variant: 'error',
    confirmText: 'Delete'
  })

  if (!confirmed) return

  items.value = items.value.filter((_, i) => i !== index)
}

function moveItem(index, direction) {
  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= items.value.length) return

  const newItems = [...items.value]
  const temp = newItems[index]
  newItems[index] = newItems[newIndex]
  newItems[newIndex] = temp
  items.value = newItems
}
</script>
