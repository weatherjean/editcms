<template>
  <dialog :open="isOpen" class="modal" :class="{ 'modal-open': isOpen }">
    <div class="modal-box">
      <h3 class="font-bold text-lg mb-4">{{ title }}</h3>
      <p class="py-4">{{ message }}</p>
      <div class="modal-action">
        <button @click="handleCancel" class="btn">
          {{ cancelText }}
        </button>
        <button
          @click="handleConfirm"
          class="btn"
          :class="buttonClass"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop" @submit.prevent="handleCancel">
      <button type="submit">close</button>
    </form>
  </dialog>
</template>

<script setup>
import { computed } from 'vue'
import { useConfirm } from '../composables/useConfirm'

const {
  isOpen,
  message,
  title,
  confirmText,
  cancelText,
  confirmVariant,
  handleConfirm,
  handleCancel
} = useConfirm()

const buttonClass = computed(() => {
  switch (confirmVariant.value) {
    case 'error': return 'btn-error'
    case 'warning': return 'btn-warning'
    case 'primary': return 'btn-primary'
    default: return 'btn-primary'
  }
})
</script>
