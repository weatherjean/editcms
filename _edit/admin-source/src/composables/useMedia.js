import { ref } from 'vue'
import { useApi } from './useApi'

/**
 * Media management composable
 * Provides reusable media upload, fetch, and delete operations
 */
export function useMedia() {
  const { apiRequest } = useApi()
  const uploading = ref(false)
  const uploadProgress = ref(0)

  /**
   * Upload a file to the media library with progress tracking
   * @param {File} file - File to upload
   * @param {Function} onProgress - Optional callback for progress updates (0-100)
   * @returns {Promise<Object>} - Uploaded media object with URL
   * @throws {Error} - If upload fails
   */
  async function uploadFile(file, onProgress = null) {
    if (!file) {
      throw new Error('No file provided')
    }

    uploading.value = true
    uploadProgress.value = 0

    return new Promise((resolve, reject) => {
      const formData = new FormData()
      formData.append('file', file)

      const xhr = new XMLHttpRequest()
      const token = localStorage.getItem('edit_token')

      // Track upload progress
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percentComplete = Math.round((e.loaded / e.total) * 100)
          uploadProgress.value = percentComplete
          if (onProgress) {
            onProgress(percentComplete)
          }
        }
      })

      // Handle completion
      xhr.addEventListener('load', () => {
        uploading.value = false
        uploadProgress.value = 0

        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const media = JSON.parse(xhr.responseText)
            resolve(media)
          } catch (err) {
            reject(new Error('Invalid response from server'))
          }
        } else {
          try {
            const errorData = JSON.parse(xhr.responseText)
            reject(new Error(errorData.error || 'Upload failed'))
          } catch (err) {
            reject(new Error('Upload failed'))
          }
        }
      })

      // Handle errors
      xhr.addEventListener('error', () => {
        uploading.value = false
        uploadProgress.value = 0
        reject(new Error('Network error during upload'))
      })

      // Handle abort
      xhr.addEventListener('abort', () => {
        uploading.value = false
        uploadProgress.value = 0
        reject(new Error('Upload cancelled'))
      })

      // Send request
      xhr.open('POST', '/_edit/admin-api/media')
      xhr.setRequestHeader('Authorization', `Bearer ${token}`)
      xhr.send(formData)
    })
  }

  /**
   * Fetch all media items
   * @param {Object} options - Query options
   * @param {number} options.limit - Number of items to fetch
   * @param {number} options.offset - Offset for pagination
   * @returns {Promise<Array>} - Array of media objects
   */
  async function fetchMedia(options = {}) {
    const params = new URLSearchParams()
    if (options.limit) params.append('limit', options.limit)
    if (options.offset) params.append('offset', options.offset)

    const query = params.toString() ? `?${params}` : ''
    return await apiRequest('GET', `/media${query}`)
  }

  /**
   * Fetch a single media item by ID
   * @param {number} id - Media ID
   * @returns {Promise<Object>} - Media object
   */
  async function fetchMediaById(id) {
    return await apiRequest('GET', `/media/${id}`)
  }

  /**
   * Delete a media item
   * @param {number} id - Media ID to delete
   * @returns {Promise<void>}
   */
  async function deleteMedia(id) {
    return await apiRequest('DELETE', `/media/${id}`)
  }

  /**
   * Get media URL from path
   * @param {string} path - Media path (e.g., "2025/01/filename.jpg")
   * @returns {string} - Full media URL
   */
  function getMediaUrl(path) {
    return `/_edit/uploads/${path}`
  }

  return {
    uploading,
    uploadProgress,
    uploadFile,
    fetchMedia,
    fetchMediaById,
    deleteMedia,
    getMediaUrl
  }
}
