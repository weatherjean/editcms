/**
 * Date formatting utilities
 */
export function useDate() {
  /**
   * Format a date string to localized date format
   * @param {string} dateString - ISO date string
   * @returns {string} Formatted date (e.g., "Jan 15, 2025")
   */
  function formatDate(dateString) {
    if (!dateString) return 'N/A'

    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  }

  /**
   * Format a date string to localized date and time format
   * @param {string} dateString - ISO date string
   * @returns {string} Formatted date and time (e.g., "Jan 15, 2025, 3:45 PM")
   */
  function formatDateTime(dateString) {
    if (!dateString) return 'N/A'

    const date = new Date(dateString)
    return date.toLocaleString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    })
  }

  /**
   * Format a date string to time only
   * @param {string} dateString - ISO date string
   * @returns {string} Formatted time (e.g., "3:45 PM")
   */
  function formatTime(dateString) {
    if (!dateString) return 'N/A'

    const date = new Date(dateString)
    return date.toLocaleTimeString('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true
    })
  }

  /**
   * Format a date string to relative time (e.g., "2 hours ago")
   * @param {string} dateString - ISO date string
   * @returns {string} Relative time
   */
  function formatRelative(dateString) {
    if (!dateString) return 'N/A'

    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffSeconds = Math.floor(diffMs / 1000)
    const diffMinutes = Math.floor(diffSeconds / 60)
    const diffHours = Math.floor(diffMinutes / 60)
    const diffDays = Math.floor(diffHours / 24)

    if (diffSeconds < 60) return 'just now'
    if (diffMinutes < 60) return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`

    // For older dates, just show the formatted date
    return formatDate(dateString)
  }

  return {
    formatDate,
    formatDateTime,
    formatTime,
    formatRelative
  }
}
