import { ref, computed, watch } from 'vue'
import { useApi } from './useApi'

const user = ref(null)
const token = ref(localStorage.getItem('edit_token'))
const isFirstTimeSetup = ref(false)

// Sync token to localStorage automatically (single source of truth)
watch(token, (newToken) => {
  if (newToken) {
    localStorage.setItem('edit_token', newToken)
  } else {
    localStorage.removeItem('edit_token')
  }
})

export function useAuth() {
  const { apiRequest } = useApi()
  const isAuthenticated = computed(() => !!user.value && !!token.value)

  async function checkFirstTimeSetup() {
    try {
      const response = await apiRequest('GET', '/health')
      isFirstTimeSetup.value = response.first_time_setup === true
      return isFirstTimeSetup.value
    } catch (error) {
      console.error('Failed to check first time setup:', error)
      return false
    }
  }

  async function verifyAuth() {
    if (!token.value) return false

    try {
      const response = await apiRequest('GET', '/auth/me')
      if (response.user) {
        user.value = response.user
        return true
      }
    } catch (error) {
      localStorage.removeItem('edit_token')
      token.value = null
    }
    return false
  }

  async function login(email, password) {
    const response = await apiRequest('POST', '/auth/login', { email, password })
    handleAuthSuccess(response)
  }

  async function register(name, email, password) {
    const response = await apiRequest('POST', '/auth/register', { name, email, password })
    handleAuthSuccess(response)
  }

  function handleAuthSuccess(response) {
    token.value = response.token  // localStorage sync happens via watch
    user.value = response.user
  }

  async function logout() {
    try {
      await apiRequest('POST', '/auth/logout')
    } catch (error) {
      console.warn('Logout API call failed:', error)
    }

    token.value = null
    user.value = null
  }

  return {
    user,
    isAuthenticated,
    isFirstTimeSetup,
    checkFirstTimeSetup,
    verifyAuth,
    login,
    register,
    logout
  }
}
