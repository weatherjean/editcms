<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <label class="label">
          <span class="label-text font-semibold">Flexible Content</span>
        </label>
        <p class="opacity-60 -mt-2 mb-2">Build your content with reusable blocks</p>
      </div>
    </div>

    <!-- Block Items -->
    <div v-if="modelValue && modelValue.length > 0" class="space-y-4">
      <div v-for="(item, index) in modelValue" :key="index" class="card bg-base-100 border-2 border-base-300">
        <div class="card-body">
          <!-- Block Header -->
          <div class="flex items-center justify-between mb-4">
            <div>
              <h3 class="font-semibold">{{ getBlock(item.block_type)?.label || item.block_type }}</h3>
              <p v-if="getBlock(item.block_type)?.description" class="opacity-60">
                {{ getBlock(item.block_type)?.description }}
              </p>
            </div>
            <div class="flex gap-1">
              <button
                type="button"
                @click="moveBlock(index, -1)"
                :disabled="index === 0"
                class="btn btn-ghost"
                title="Move Up"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                type="button"
                @click="moveBlock(index, 1)"
                :disabled="index === modelValue.length - 1"
                class="btn btn-ghost"
                title="Move Down"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                type="button"
                @click="removeBlock(index)"
                class="btn btn-error btn-ghost"
                title="Remove Block"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
          </div>

          <!-- Block Fields -->
          <div class="space-y-4">
            <div v-for="field in getBlock(item.block_type)?.fields || []" :key="field.key">
              <!-- Debug Info -->
              <div v-if="false" class="text-xs opacity-50">
                Field: {{ field.key }}, Type: {{ field.type }},
                Value type: {{ typeof item.fields?.[field.key] }},
                Is Array: {{ Array.isArray(item.fields?.[field.key]) }}
              </div>

              <!-- Repeater Field -->
              <RepeaterField
                v-if="field.type === 'repeater' && item.fields"
                v-model="item.fields[field.key]"
                :fields="field.config?.fields || []"
                @selectMedia="(subFieldKey, subItem) => $emit('selectMedia', subFieldKey, subItem)"
              />

              <!-- All Other Fields -->
              <FieldRenderer
                v-else-if="item.fields"
                :field="field"
                v-model="item.fields[field.key]"
                @selectMedia="(fieldKey) => $emit('selectMedia', fieldKey, item.fields)"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="card bg-base-100 border-2 border-dashed border-base-300">
      <div class="card-body items-center text-center py-12">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 opacity-40 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
        </svg>
        <h3 class="font-semibold mb-1">No blocks added yet</h3>
        <p class="opacity-60 mb-4">Choose a block type below to start building your content</p>
      </div>
    </div>

    <!-- Add Block Buttons -->
    <div class="flex flex-wrap gap-2">
      <button
        v-for="block in availableBlocks"
        :key="block.key"
        type="button"
        @click="addBlock(block.key)"
        class="btn btn-outline"
      >
        {{ block.label }}
      </button>
    </div>
  </div>
</template>

<script setup>
import FieldRenderer from './FieldRenderer.vue'
import RepeaterField from './RepeaterField.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  availableBlocks: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue', 'selectMedia'])

function getBlock(blockType) {
  return props.availableBlocks.find(b => b.key === blockType)
}

function addBlock(blockType) {
  const block = getBlock(blockType)
  if (!block) return

  const fields = {}
  block.fields.forEach(field => {
    if (field.type === 'repeater') {
      fields[field.key] = []
    } else {
      fields[field.key] = field.default_value !== undefined ? field.default_value : ''
    }
  })

  const newItem = {
    block_type: blockType,
    fields: fields
  }

  const currentValue = props.modelValue || []
  emit('update:modelValue', [...currentValue, newItem])
}

function removeBlock(index) {
  if (!props.modelValue) return

  const updated = [...props.modelValue]
  updated.splice(index, 1)
  emit('update:modelValue', updated)
}

function moveBlock(index, direction) {
  if (!props.modelValue) return

  const newIndex = index + direction
  if (newIndex < 0 || newIndex >= props.modelValue.length) return

  const updated = [...props.modelValue]
  const temp = updated[index]
  updated[index] = updated[newIndex]
  updated[newIndex] = temp

  emit('update:modelValue', updated)
}
</script>
