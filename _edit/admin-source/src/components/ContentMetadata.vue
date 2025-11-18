<template>
  <CardSection title="Publish" body-class="space-y-4" collapsible>
    <fieldset class="fieldset">
      <legend class="fieldset-legend">Slug <span class="text-error">*</span></legend>
      <input
        type="text"
        :value="slug"
        @input="$emit('update:slug', $event.target.value)"
        required
        placeholder="my-unique-slug"
        class="input w-full"
      />
      <p class="label">URL-friendly identifier</p>

      <!-- API URL Display -->
      <div v-if="slug && contentType" class="mt-2 p-2 bg-base-200 rounded text-xs">
        <div class="font-semibold mb-1 opacity-60">Public API URL:</div>
        <div class="flex items-center gap-2">
          <code class="flex-1 break-all">{{ publicApiUrl }}</code>
          <button
            type="button"
            @click="copyUrl"
            class="btn btn-ghost btn-xs"
            title="Copy URL"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              class="w-4 h-4"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
              />
            </svg>
          </button>
        </div>
        <div v-if="fullUrl" class="mt-2">
          <div class="font-semibold mb-1 opacity-60">Full URL:</div>
          <div class="flex items-center gap-2">
            <code class="flex-1 break-all">{{ fullUrl }}</code>
            <button
              type="button"
              @click="copyFullUrl"
              class="btn btn-ghost btn-xs"
              title="Copy Full URL"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-4 h-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </fieldset>

    <fieldset class="fieldset">
      <legend class="fieldset-legend">Status</legend>
      <select :value="status" @change="$emit('update:status', $event.target.value)" class="select w-full">
        <option value="draft">Draft</option>
        <option value="published">Published</option>
      </select>
    </fieldset>
  </CardSection>
</template>

<script setup>
import { computed } from 'vue'
import CardSection from './CardSection.vue'
import { useToast } from '../composables/useToast'

const props = defineProps({
  slug: {
    type: String,
    required: true
  },
  status: {
    type: String,
    default: 'draft'
  },
  contentType: {
    type: String,
    default: null
  }
})

defineEmits(['update:slug', 'update:status'])

const { success, error } = useToast()

const publicApiUrl = computed(() => {
  if (!props.slug || !props.contentType) return ''
  return `/_edit/api/public/${props.contentType}/${props.slug}`
})

const fullUrl = computed(() => {
  if (!props.slug || !props.contentType) return ''
  // Get the current origin
  const origin = window.location.origin
  return `${origin}/_edit/api/public/${props.contentType}/${props.slug}`
})

async function copyUrl() {
  try {
    await navigator.clipboard.writeText(publicApiUrl.value)
    success('API URL copied!')
  } catch (err) {
    error('Failed to copy')
  }
}

async function copyFullUrl() {
  try {
    await navigator.clipboard.writeText(fullUrl.value)
    success('Full URL copied!')
  } catch (err) {
    error('Failed to copy')
  }
}
</script>
