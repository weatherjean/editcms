<template>
  <div class="card bg-base-100 border shadow">
    <div class="card-body">
      <!-- Header (Collapsible) -->
      <div v-if="title || description || $slots.header" class="flex items-start justify-between">
        <button
          v-if="collapsible"
          type="button"
          @click="toggleCollapse"
          class="flex items-center gap-2 text-left flex-1"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-5 h-5 transition-transform flex-shrink-0"
            :class="{ 'rotate-90': !isCollapsed }"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          <div class="flex-1">
            <slot name="header">
              <h2 class="card-title">{{ title }}</h2>
              <p v-if="description" class="opacity-60 mt-1">{{ description }}</p>
            </slot>
          </div>
        </button>
        <div v-else class="flex-1">
          <slot name="header">
            <h2 class="card-title">{{ title }}</h2>
            <p v-if="description" class="opacity-60 mt-1">{{ description }}</p>
          </slot>
        </div>
      </div>

      <!-- Body (Collapsible) -->
      <div v-show="!isCollapsed">
        <!-- Divider -->
        <div v-if="title || description || $slots.header" class="divider my-0"></div>

        <!-- Content -->
        <div :class="bodyClass">
          <slot></slot>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  description: {
    type: String,
    default: ''
  },
  bodyClass: {
    type: String,
    default: 'space-y-6'
  },
  collapsible: {
    type: Boolean,
    default: false
  },
  defaultCollapsed: {
    type: Boolean,
    default: false
  }
})

const isCollapsed = ref(props.defaultCollapsed)

function toggleCollapse() {
  isCollapsed.value = !isCollapsed.value
}
</script>
