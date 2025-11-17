<template>
  <div class="space-y-4">
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
          <fieldset v-for="subField in fields" :key="subField.key" class="fieldset">
            <legend class="fieldset-legend">{{ subField.label }}</legend>

            <!-- Text -->
            <input v-if="subField.type === 'text'" type="text" v-model="item[subField.key]" class="input w-full">

            <!-- Textarea -->
            <textarea v-else-if="subField.type === 'textarea'" v-model="item[subField.key]" rows="3" class="textarea w-full"></textarea>

            <!-- Number -->
            <input v-else-if="subField.type === 'number'" type="number" v-model="item[subField.key]" class="input w-full">

            <!-- Media -->
            <MediaField
              v-else-if="subField.type === 'media'"
              :model-value="item[subField.key]"
              size="small"
              @select="$emit('selectMedia', subField.key, item)"
              @remove="item[subField.key] = null"
            />
          </fieldset>
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
import MediaField from './MediaField.vue'
import IconChevronUp from './icons/IconChevronUp.vue'
import IconChevronDown from './icons/IconChevronDown.vue'
import IconTrash from './icons/IconTrash.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  fields: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'selectMedia'])

const items = computed({
  get: () => props.modelValue || [],
  set: (value) => emit('update:modelValue', value)
})

function addItem() {
  const newItem = {}
  props.fields.forEach(field => {
    newItem[field.key] = ''
  })
  items.value = [...items.value, newItem]
}

function removeItem(index) {
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
