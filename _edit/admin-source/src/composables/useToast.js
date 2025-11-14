import { ref, readonly } from 'vue'

// Global toast state
const toasts = ref([])
let nextId = 0

/**
 * Global toast notification system
 *
 * Usage:
 *   const { success, error, warning, info } = useToast()
 *   success('Changes saved!')
 *   error('Failed to save changes')
 */
export function useToast() {
  /**
   * Show a toast message
   * @param {string} message - Message to display
   * @param {string} type - Toast type: 'success', 'error', 'warning', 'info'
   * @param {number} duration - Duration in milliseconds (default: 3000)
   */
  function show(message, type = 'info', duration = 3000) {
    const id = nextId++
    const toast = {
      id,
      message,
      type,
      show: true
    }

    toasts.value.push(toast)

    // Auto-dismiss after duration
    if (duration > 0) {
      setTimeout(() => {
        dismiss(id)
      }, duration)
    }

    return id
  }

  /**
   * Dismiss a specific toast
   * @param {number} id - Toast ID to dismiss
   */
  function dismiss(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  /**
   * Clear all toasts
   */
  function clear() {
    toasts.value = []
  }

  /**
   * Show success toast
   * @param {string} message - Message to display
   * @param {number} duration - Duration in milliseconds (default: 3000)
   */
  function success(message, duration = 3000) {
    return show(message, 'success', duration)
  }

  /**
   * Show error toast
   * @param {string} message - Message to display
   * @param {number} duration - Duration in milliseconds (default: 5000)
   */
  function error(message, duration = 5000) {
    return show(message, 'error', duration)
  }

  /**
   * Show warning toast
   * @param {string} message - Message to display
   * @param {number} duration - Duration in milliseconds (default: 4000)
   */
  function warning(message, duration = 4000) {
    return show(message, 'warning', duration)
  }

  /**
   * Show info toast
   * @param {string} message - Message to display
   * @param {number} duration - Duration in milliseconds (default: 3000)
   */
  function info(message, duration = 3000) {
    return show(message, 'info', duration)
  }

  return {
    toasts: readonly(toasts),
    show,
    success,
    error,
    warning,
    info,
    dismiss,
    clear
  }
}
