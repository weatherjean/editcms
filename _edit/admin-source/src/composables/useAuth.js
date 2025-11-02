import { ref, computed } from 'vue'
import { useApi } from './useApi'

const user = ref(null)
const token = ref(localStorage.getItem('edit_token'))
const isFirstTimeSetup = ref(false)

export function useAuth() {
  const { apiRequest } = useApi()
  const isAuthenticated = computed(() => !!user.value && !!token.value)

  async function checkFirstTimeSetup() {
    try {
      const response = await fetch('/_edit/api/auth/has-users')
      const data = await response.json()
      isFirstTimeSetup.value = !data.has_users
    } catch (error) {
      console.error('Failed to check first-time setup:', error)
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
    token.value = response.token
    user.value = response.user
    localStorage.setItem('edit_token', response.token)
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('edit_token')
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
