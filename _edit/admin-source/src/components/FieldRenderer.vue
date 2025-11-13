<template>
  <FormField :label="field.label" :instructions="field.instructions" :required="field.required">
    <!-- Text Field -->
    <input v-if="field.type === 'text'"
           type="text"
           :value="modelValue"
           :required="field.required"
           @input="$emit('update:modelValue', $event.target.value)"
           class="input w-full">

    <!-- Textarea Field -->
    <textarea v-else-if="field.type === 'textarea'"
              :value="modelValue"
              :required="field.required"
              @input="$emit('update:modelValue', $event.target.value)"
              rows="5"
              class="textarea w-full"></textarea>

    <!-- Number Field -->
    <input v-else-if="field.type === 'number'"
           type="number"
           :value="modelValue"
           :required="field.required"
           :min="field.config?.min"
           :max="field.config?.max"
           :step="field.config?.step || '1'"
           @input="$emit('update:modelValue', parseFloat($event.target.value))"
           class="input w-full">

    <!-- Boolean Field -->
    <label v-else-if="field.type === 'boolean'" class="label cursor-pointer justify-start gap-4">
      <input type="checkbox"
             :checked="modelValue"
             @change="$emit('update:modelValue', $event.target.checked)"
             class="toggle">
      <span class="label-text">{{ field.label }}</span>
    </label>

    <!-- Select Field -->
    <select v-else-if="field.type === 'select'"
            :value="modelValue"
            :required="field.required"
            @change="$emit('update:modelValue', $event.target.value)"
            class="select w-full">
      <option value="">-- Select --</option>
      <option v-for="choice in parseSelectChoices(field.config?.choices)"
              :key="choice.value"
              :value="choice.value">
        {{ choice.label }}
      </option>
    </select>

    <!-- Date Field -->
    <input v-else-if="field.type === 'date'"
           type="date"
           :value="modelValue"
           :required="field.required"
           @input="$emit('update:modelValue', $event.target.value)"
           class="input w-full">

    <!-- DateTime Field -->
    <input v-else-if="field.type === 'datetime'"
           type="datetime-local"
           :value="modelValue"
           :required="field.required"
           @input="$emit('update:modelValue', $event.target.value)"
           class="input w-full">

    <!-- Slug Field -->
    <input v-else-if="field.type === 'slug'"
           type="text"
           :value="modelValue"
           :required="field.required"
           pattern="[a-z0-9\-]+"
           placeholder="lowercase-with-hyphens"
           @input="$emit('update:modelValue', $event.target.value)"
           class="input w-full">

    <!-- HTML/WYSIWYG Field -->
    <WysiwygField
      v-else-if="field.type === 'html' || field.type === 'wysiwyg'"
      :model-value="modelValue"
      @update:model-value="$emit('update:modelValue', $event)"
    />

    <!-- Media Field -->
    <MediaField
      v-else-if="field.type === 'media'"
      :model-value="modelValue"
      @select="$emit('selectMedia', field.key)"
      @remove="$emit('update:modelValue', null)"
    />

    <!-- Relationship Field -->
    <select v-else-if="field.type === 'relationship'"
            :value="modelValue"
            :required="field.required"
            @focus="$emit('loadRelationship', field.config?.post_type)"
            @change="$emit('update:modelValue', $event.target.value)"
            class="select w-full">
      <option value="">-- Select {{ field.config?.post_type || 'item' }} --</option>
      <option v-for="item in relationshipItems || []"
              :key="item.id"
              :value="item.id">
        {{ item.type }}: {{ item.slug }}
      </option>
    </select>

    <!-- Repeater Field - handled separately -->
    <slot v-else-if="field.type === 'repeater'" name="repeater"></slot>

    <!-- Unknown field type -->
    <div v-else class="alert alert-warning">
      <span>Unknown field type: {{ field.type }}</span>
    </div>
  </FormField>
</template>

<script setup>
import FormField from './FormField.vue'
import MediaField from './MediaField.vue'
import WysiwygField from './WysiwygField.vue'

defineProps({
  field: {
    type: Object,
    required: true
  },
  modelValue: {
    default: null
  },
  relationshipItems: {
    type: Array,
    default: () => []
  }
})

defineEmits(['update:modelValue', 'selectMedia', 'loadRelationship'])

function parseSelectChoices(choices) {
  if (!choices) return []

  if (typeof choices === 'object' && !Array.isArray(choices)) {
    return Object.entries(choices).map(([value, label]) => ({
      value,
      label
    }))
  }

  if (typeof choices === 'string') {
    return choices.split('\n')
      .filter(line => line.trim())
      .map(line => {
        const [value, label] = line.split(':').map(s => s.trim())
        return {
          value: value || label,
          label: label || value
        }
      })
  }

  return []
}
</script>
