<template>
  <div class="card bg-base-100 border shadow">
    <div class="card-body">
      <!-- Header -->
      <div v-if="title || description || $slots.header">
        <slot name="header">
          <h2 class="card-title">{{ title }}</h2>
          <p v-if="description" class="opacity-60 mt-1">{{ description }}</p>
        </slot>
      </div>

      <!-- Divider -->
      <div v-if="title || description || $slots.header" class="divider my-0"></div>

      <!-- Body -->
      <div :class="bodyClass">
        <slot></slot>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
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
  }
})
</script>
