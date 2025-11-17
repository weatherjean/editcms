<template>
  <CardSection title="Publish" body-class="space-y-4">
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
import CardSection from './CardSection.vue'

defineProps({
  slug: {
    type: String,
    required: true
  },
  status: {
    type: String,
    default: 'draft'
  }
})

defineEmits(['update:slug', 'update:status'])
</script>
