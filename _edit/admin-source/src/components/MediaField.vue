<template>
  <div>
    <!-- Show media preview if selected -->
    <div v-if="mediaValue" class="mb-2">
      <div class="card bg-base-100 border">
        <div class="card-body p-4 flex-row items-center gap-4">
          <div class="avatar">
            <div class="rounded">
              <img :src="mediaValue.url" :alt="mediaValue.filename" class="object-cover">
            </div>
          </div>
          <div class="flex-1">
            <div class="font-mono">{{ mediaValue.filename }}</div>
          </div>
          <div class="join">
            <button type="button" @click="$emit('select')" class="btn btn-ghost join-item">Change</button>
            <button type="button" @click="$emit('remove')" class="btn btn-ghost join-item">Remove</button>
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

const props = defineProps({
  modelValue: {
    type: [Object, Number, String],
    default: null
  },
  size: {
    type: String,
    default: 'default', // 'default' or 'small'
    validator: (value) => ['default', 'small'].includes(value)
  }
})

defineEmits(['select', 'remove', 'update:modelValue'])

const mediaValue = computed(() => {
  if (!props.modelValue) return null

  // If it's already a media object with url, return it
  if (typeof props.modelValue === 'object' && props.modelValue.url) {
    return props.modelValue
  }

  return null
})

const buttonClass = computed(() => {
  return props.size === 'small'
    ? 'btn btn-outline w-full'
    : 'btn btn-outline w-full'
})
</script>
