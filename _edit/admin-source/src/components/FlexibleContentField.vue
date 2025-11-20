<template>
  <div class="space-y-4">
    <!-- Block Items -->
    <div v-if="modelValue && modelValue.length > 0" class="space-y-4">
      <div v-for="(item, index) in modelValue" :key="index" class="card bg-base-100 border-2 border-base-300">
        <div class="card-body">
          <!-- Block Header -->
          <div class="flex items-center justify-between">
            <button
              type="button"
              @click="toggleCollapse(index)"
              class="flex items-center gap-2 text-left flex-1"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="w-5 h-5 transition-transform"
                :class="{ 'rotate-90': !collapsedBlocks[index] }"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
              <div>
                <h3 class="card-title">{{ getBlock(item.block_type)?.label || item.block_type }}</h3>
                <p v-if="getBlock(item.block_type)?.description" class="opacity-60 mt-1">
                  {{ getBlock(item.block_type)?.description }}
                </p>
              </div>
            </button>
            <div class="join">
              <button
                type="button"
                @click="moveBlock(index, -1)"
                :disabled="index === 0"
                class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                data-tip="Move Up"
              >
                <IconChevronUp />
              </button>
              <button
                type="button"
                @click="moveBlock(index, 1)"
                :disabled="index === modelValue.length - 1"
                class="btn btn-ghost btn-sm join-item tooltip tooltip-left"
                data-tip="Move Down"
              >
                <IconChevronDown />
              </button>
              <button
                type="button"
                @click="removeBlock(index)"
                class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left"
                data-tip="Remove Block"
              >
                <IconTrash />
              </button>
            </div>
          </div>

          <!-- Block Fields (Collapsible) -->
          <div v-show="!collapsedBlocks[index]">
            <div class="divider my-0"></div>

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
                  :relationship-data="relationshipData"
                  :label="field.label"
                  :instructions="field.instructions"
                  @selectMedia="(subFieldKey, subItem) => $emit('selectMedia', subFieldKey, subItem)"
                  @loadRelationship="(postType) => $emit('loadRelationship', postType)"
                />

                <!-- All Other Fields -->
                <FieldRenderer
                  v-else-if="item.fields"
                  :field="field"
                  v-model="item.fields[field.key]"
                  :relationship-items="relationshipData[field.config?.post_type]"
                  @selectMedia="(fieldKey) => $emit('selectMedia', fieldKey, item.fields)"
                  @loadRelationship="(postType) => $emit('loadRelationship', postType)"
                />
              </div>
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

    <!-- Add Block Button -->
    <button
      type="button"
      @click="openBlockSelector"
      class="btn btn-outline btn-block"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
      </svg>
      Add Block
    </button>

    <!-- Block Selector Modal -->
    <dialog ref="blockSelectorRef" class="modal">
      <div class="modal-box max-w-4xl">
        <h3 class="font-bold text-lg mb-4">Select Block Type</h3>

        <!-- Search/Filter -->
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search blocks..."
          class="input input-bordered w-full mb-4"
        />

        <!-- Block Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 max-h-96 overflow-y-auto">
          <button
            v-for="block in filteredBlocks"
            :key="block.key"
            type="button"
            @click="selectBlock(block.key)"
            class="card bg-base-200 hover:bg-base-300 transition-colors cursor-pointer border-2 border-transparent hover:border-primary"
          >
            <div class="card-body p-4 items-center text-center">
              <!-- Block Image or Icon -->
              <div class="w-full aspect-square mb-2 flex items-center justify-center bg-base-100 rounded">
                <img
                  v-if="block.image"
                  :src="block.image"
                  :alt="block.label"
                  class="w-full h-full object-cover rounded"
                />
                <svg
                  v-else
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-12 h-12 opacity-40"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                  />
                </svg>
              </div>

              <!-- Block Label -->
              <h4 class="font-semibold text-sm">{{ block.label }}</h4>

              <!-- Block Description -->
              <p v-if="block.description" class="text-xs opacity-60 line-clamp-2 mt-1">
                {{ block.description }}
              </p>
            </div>
          </button>
        </div>

        <!-- Empty State -->
        <div v-if="filteredBlocks.length === 0" class="text-center py-8 opacity-60">
          <p class="text-sm">No blocks found</p>
        </div>

        <div class="modal-action">
          <button type="button" @click="closeBlockSelector" class="btn">Cancel</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button type="button" @click="closeBlockSelector">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue'
import FieldRenderer from './FieldRenderer.vue'
import RepeaterField from './RepeaterField.vue'
import IconChevronUp from './icons/IconChevronUp.vue'
import IconChevronDown from './icons/IconChevronDown.vue'
import IconTrash from './icons/IconTrash.vue'
import { useConfirm } from '../composables/useConfirm'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  availableBlocks: {
    type: Array,
    default: () => []
  },
  relationshipData: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['update:modelValue', 'selectMedia', 'loadRelationship'])

const { confirm: confirmDialog } = useConfirm()

// Track collapsed state for each block
const collapsedBlocks = ref([])

// Block selector modal
const blockSelectorRef = ref(null)
const searchQuery = ref('')

// Initialize collapsed state when blocks change
watch(() => props.modelValue, (newValue) => {
  if (newValue && newValue.length > collapsedBlocks.value.length) {
    // New blocks added - keep existing states and add true for new blocks (collapsed by default)
    const diff = newValue.length - collapsedBlocks.value.length
    collapsedBlocks.value = [...collapsedBlocks.value, ...Array(diff).fill(true)]
  } else if (newValue && newValue.length < collapsedBlocks.value.length) {
    // Blocks removed - trim the array
    collapsedBlocks.value = collapsedBlocks.value.slice(0, newValue.length)
  }
}, { immediate: true })

// Filtered blocks based on search
const filteredBlocks = computed(() => {
  if (!searchQuery.value) {
    return props.availableBlocks
  }

  const query = searchQuery.value.toLowerCase()
  return props.availableBlocks.filter(block => {
    return (
      block.label?.toLowerCase().includes(query) ||
      block.key?.toLowerCase().includes(query) ||
      block.description?.toLowerCase().includes(query)
    )
  })
})

function toggleCollapse(index) {
  collapsedBlocks.value[index] = !collapsedBlocks.value[index]
}

function openBlockSelector() {
  searchQuery.value = ''
  blockSelectorRef.value?.showModal()
}

function closeBlockSelector() {
  blockSelectorRef.value?.close()
}

function selectBlock(blockKey) {
  addBlock(blockKey)
  closeBlockSelector()
}

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

async function removeBlock(index) {
  if (!props.modelValue) return

  const block = props.modelValue[index]
  const blockLabel = getBlock(block.block_type)?.label || block.block_type

  const confirmed = await confirmDialog(
    `Are you sure you want to remove this ${blockLabel} block? This action cannot be undone.`,
    {
      title: 'Remove Block',
      variant: 'error',
      confirmText: 'Remove'
    }
  )

  if (!confirmed) return

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

  // Also swap the collapsed states
  const tempCollapsed = collapsedBlocks.value[index]
  collapsedBlocks.value[index] = collapsedBlocks.value[newIndex]
  collapsedBlocks.value[newIndex] = tempCollapsed

  emit('update:modelValue', updated)
}
</script>
