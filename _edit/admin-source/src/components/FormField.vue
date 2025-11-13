<template>
  <fieldset class="fieldset">
    <legend class="fieldset-legend">
      {{ label }}
      <span v-if="required" class="text-error ml-1">*</span>
    </legend>
    <p v-if="instructions" class="opacity-60 mb-2">{{ instructions }}</p>
    <slot></slot>
  </fieldset>
</template>

<script setup>
defineProps({
  label: {
    type: String,
    required: true
  },
  instructions: {
    type: String,
    default: ''
  },
  required: {
    type: Boolean,
    default: false
  }
})
</script>
