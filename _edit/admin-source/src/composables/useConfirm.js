import { ref, readonly } from 'vue'

// Global confirm state
const isOpen = ref(false)
const message = ref('')
const title = ref('')
const confirmText = ref('Confirm')
const cancelText = ref('Cancel')
const confirmVariant = ref('primary')
let resolvePromise = null

/**
 * Global confirmation dialog system
 *
 * Usage:
 *   const { confirm } = useConfirm()
 *   const confirmed = await confirm('Are you sure?')
 *   if (confirmed) {
 *     // User clicked confirm
 *   }
 */
export function useConfirm() {
  /**
   * Show confirmation dialog
   * @param {string} messageText - Message to display
   * @param {Object} options - Configuration options
   * @param {string} options.title - Dialog title (default: 'Confirm')
   * @param {string} options.confirmText - Confirm button text (default: 'Confirm')
   * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
   * @param {string} options.variant - Confirm button variant: 'primary', 'error', 'warning' (default: 'primary')
   * @returns {Promise<boolean>} - Resolves to true if confirmed, false if cancelled
   */
  function confirm(messageText, options = {}) {
    message.value = messageText
    title.value = options.title || 'Confirm'
    confirmText.value = options.confirmText || 'Confirm'
    cancelText.value = options.cancelText || 'Cancel'
    confirmVariant.value = options.variant || 'primary'
    isOpen.value = true

    return new Promise((resolve) => {
      resolvePromise = resolve
    })
  }

  /**
   * Handle confirm button click
   */
  function handleConfirm() {
    isOpen.value = false
    if (resolvePromise) {
      resolvePromise(true)
      resolvePromise = null
    }
  }

  /**
   * Handle cancel button click or dialog close
   */
  function handleCancel() {
    isOpen.value = false
    if (resolvePromise) {
      resolvePromise(false)
      resolvePromise = null
    }
  }

  return {
    // State (readonly for consumers)
    isOpen: readonly(isOpen),
    message: readonly(message),
    title: readonly(title),
    confirmText: readonly(confirmText),
    cancelText: readonly(cancelText),
    confirmVariant: readonly(confirmVariant),

    // Methods
    confirm,
    handleConfirm,
    handleCancel
  }
}
