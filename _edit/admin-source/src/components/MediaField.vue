<template>
  <div>
    <!-- Multiple media preview -->
    <div v-if="multiple && mediaValues.length > 0" class="space-y-2 mb-2">
      <div v-for="(media, index) in mediaValues" :key="media.id" class="card bg-base-100 border">
        <div class="card-body p-4 flex-row items-center gap-4">
          <div class="avatar">
            <div class="w-12 rounded">
              <img :src="media.url" :alt="media.filename" class="object-cover">
            </div>
          </div>
          <div class="flex-1">
            <div class="font-mono text-sm">{{ media.filename }}</div>
          </div>
          <button type="button" @click="removeItem(index)" class="btn btn-sm btn-ghost btn-error tooltip tooltip-left" data-tip="Remove">
            <IconTrash />
          </button>
        </div>
      </div>
      <button type="button" @click="$emit('select')" class="btn btn-outline btn-sm w-full">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        Add More
      </button>
    </div>

    <!-- Single media preview -->
    <div v-else-if="!multiple && mediaValue" class="mb-2">
      <div class="card bg-base-100 border">
        <div class="card-body p-4 flex-row items-center gap-4">
          <div class="avatar">
            <div class="w-12 rounded">
              <img :src="mediaValue.url" :alt="mediaValue.filename" class="object-cover">
            </div>
          </div>
          <div class="flex-1">
            <div class="font-mono text-sm">{{ mediaValue.filename }}</div>
          </div>
          <div class="join">
            <button type="button" @click="$emit('select')" class="btn btn-sm btn-ghost join-item tooltip tooltip-left" data-tip="Change">
              <IconEdit />
            </button>
            <button type="button" @click="$emit('remove')" class="btn btn-sm btn-ghost btn-error join-item tooltip tooltip-left" data-tip="Remove">
              <IconTrash />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Show select button if no media selected -->
    <button v-else type="button" @click="$emit('select')" :class="buttonClass">
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
      </svg>
      Select Media
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import IconEdit from './icons/IconEdit.vue'
import IconTrash from './icons/IconTrash.vue'

const props = defineProps({
  modelValue: {
    type: [Object, Number, String, Array],
    default: null
  },
  multiple: {
    type: Boolean,
    default: false
  },
  size: {
    type: String,
    default: 'default', // 'default' or 'small'
    validator: (value) => ['default', 'small'].includes(value)
  }
})

const emit = defineEmits(['select', 'remove', 'update:modelValue'])

// For single media selection
const mediaValue = computed(() => {
  if (props.multiple) return null
  if (!props.modelValue) return null

  // If it's already a media object with url, return it
  if (typeof props.modelValue === 'object' && props.modelValue.url) {
    return props.modelValue
  }

  return null
})

// For multiple media selection
const mediaValues = computed(() => {
  if (!props.multiple) return []
  if (!props.modelValue) return []

  // If it's an array of media objects
  if (Array.isArray(props.modelValue)) {
    return props.modelValue.filter(item => item && item.url)
  }

  // If it's a single media object, wrap it in array
  if (typeof props.modelValue === 'object' && props.modelValue.url) {
    return [props.modelValue]
  }

  return []
})

const buttonClass = computed(() => {
  return props.size === 'small'
    ? 'btn btn-outline w-full'
    : 'btn btn-outline w-full'
})

function removeItem(index) {
  if (props.multiple) {
    const newValues = [...mediaValues.value]
    newValues.splice(index, 1)
    emit('update:modelValue', newValues.length > 0 ? newValues : null)
  }
}
</script>
