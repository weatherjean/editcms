<template>
  <dialog ref="modalRef" class="modal">
    <div class="modal-box max-w-4xl">
      <h3 class="font-bold text-lg mb-4">Select Media</h3>

      <!-- Upload Section -->
      <div class="mb-4">
        <input ref="fileInputRef" type="file" @change="handleUpload" class="hidden" accept="image/*">
        <button type="button" @click="triggerFileInput" class="btn btn-primary gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
          Upload New
        </button>
      </div>

      <!-- Media Grid -->
      <div v-if="mediaItems.length > 0" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-4 max-h-96 overflow-y-auto">
        <div v-for="item in mediaItems" :key="item.id"
             @click="selectItem(item.id)"
             class="cursor-pointer border-2 rounded-lg overflow-hidden hover:border-primary transition-colors"
             :class="{ 'border-primary ring-2 ring-primary': selectedMediaId === item.id }">
          <div class="aspect-square bg-base-200">
            <img :src="item.url" :alt="item.filename" class="w-full h-full object-cover">
          </div>
          <div class="p-2 bg-base-100">
            <p class="font-mono truncate" :title="item.filename">{{ item.filename }}</p>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-8 opacity-60">
        <p>No media files yet. Upload your first file above.</p>
      </div>

      <!-- Modal Actions -->
      <div class="modal-action">
        <button type="button" @click="close" class="btn">Close</button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop">
      <button type="button" @click="close">close</button>
    </form>
  </dialog>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  mediaItems: {
    type: Array,
    default: () => []
  },
  selectedMediaId: {
    type: [Number, String],
    default: null
  }
})

const emit = defineEmits(['select', 'close', 'upload'])

const modalRef = ref(null)
const fileInputRef = ref(null)

function open() {
  modalRef.value?.showModal()
}

function close() {
  modalRef.value?.close()
  emit('close')
}

function selectItem(mediaId) {
  emit('select', mediaId)
  close()
}

async function handleUpload(event) {
  const file = event.target.files[0]
  if (!file) return

  emit('upload', file)
  event.target.value = ''
}

function triggerFileInput() {
  fileInputRef.value?.click()
}

defineExpose({
  open,
  close
})
</script>
