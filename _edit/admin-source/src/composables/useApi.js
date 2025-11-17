import { ref } from 'vue'

export function useApi() {
  const loading = ref(false)
  const error = ref(null)

  async function apiRequest(method, endpoint, data = null) {
    loading.value = true
    error.value = null

    const token = localStorage.getItem('edit_token')
    const headers = {}

    if (token) {
      headers['Authorization'] = `Bearer ${token}`
    }

    const isFormData = data instanceof FormData
    if (!isFormData) {
      headers['Content-Type'] = 'application/json'
    }

    const options = {
      method,
      headers
    }

    if (data && (method === 'POST' || method === 'PUT')) {
      options.body = isFormData ? data : JSON.stringify(data)
    }

    try {
      const response = await fetch(`/_edit/admin-api${endpoint}`, options)

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: null }))

        let errorMessage = errorData.error
        if (!errorMessage) {
          switch (response.status) {
            case 401:
              errorMessage = 'Session expired. Please log in again.'
              localStorage.removeItem('edit_token')
              break
            case 403:
              errorMessage = 'You do not have permission for this action.'
              break
            case 404:
              errorMessage = 'Resource not found.'
              break
            case 409:
              errorMessage = 'Conflict: The resource is in use or already exists.'
              break
            case 429:
              errorMessage = 'Too many requests. Please try again later.'
              break
            case 500:
              errorMessage = 'Server error. Please try again later.'
              break
            default:
              errorMessage = `Request failed (${response.status})`
          }
        }

        throw new Error(errorMessage)
      }

      const result = await response.json()
      return result
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    loading,
    error,
    apiRequest
  }
}
