<template>
  <div>
    <!-- Login Screen -->
    <main v-if="!isAuthenticated" class="container">
      <article style="max-width: 500px; margin: 4rem auto;">
        <header>
          <hgroup>
            <h1>_edit CMS</h1>
            <p>ACF-Style Headless CMS</p>
          </hgroup>
        </header>

        <!-- Login Form -->
        <form v-if="!showRegister" @submit.prevent="handleLogin">
          <label>
            Email
            <input type="email" v-model="loginForm.email" required>
          </label>
          <label>
            Password
            <input type="password" v-model="loginForm.password" required>
          </label>
          <button type="submit">Login</button>
          <p style="text-align: center; margin-top: 1rem;">
            No account? <a href="#" @click.prevent="showRegister = true">Register</a>
          </p>
        </form>

        <!-- Register Form -->
        <form v-else @submit.prevent="handleRegister">
          <label>
            Name
            <input type="text" v-model="registerForm.name" required>
          </label>
          <label>
            Email
            <input type="email" v-model="registerForm.email" required>
          </label>
          <label>
            Password
            <input type="password" v-model="registerForm.password" required>
          </label>
          <button type="submit">Register</button>
          <p v-if="!isFirstTimeSetup" style="text-align: center; margin-top: 1rem;">
            Have an account? <a href="#" @click.prevent="showRegister = false">Login</a>
          </p>
        </form>

        <div v-if="authError" style="margin-top: 1rem; padding: 1rem; background: var(--pico-del-background); color: var(--pico-del-color); border-radius: var(--pico-border-radius);">
          {{ authError }}
        </div>
      </article>
    </main>

    <!-- Admin Interface -->
    <div v-else>
      <!-- Header Navigation -->
      <nav class="container-fluid">
        <ul>
          <li><strong>_edit</strong></li>
        </ul>
        <ul>
          <!-- Content Dropdown -->
          <li>
            <details class="dropdown">
              <summary role="button" class="secondary">Content</summary>
              <ul>
                <li v-for="postType in postTypes" :key="postType.key">
                  <a href="#" @click.prevent="navigate('content-list', postType.key)">
                    {{ postType.label_plural }}
                  </a>
                </li>
              </ul>
            </details>
          </li>

          <!-- Manage Dropdown -->
          <li>
            <details class="dropdown">
              <summary role="button" class="secondary">Manage</summary>
              <ul>
                <li><a href="#" @click.prevent="navigate('media')">Media Library</a></li>
                <li><a href="#" @click.prevent="navigate('config')">Configuration</a></li>
              </ul>
            </details>
          </li>

          <!-- User Dropdown -->
          <li>
            <details class="dropdown">
              <summary role="button" class="secondary">{{ user?.name }}</summary>
              <ul>
                <li><a href="#" @click.prevent="handleLogout">Logout</a></li>
              </ul>
            </details>
          </li>
        </ul>
      </nav>

      <!-- Main Content Area -->
      <main class="container">
        <component :is="currentComponent"
                   :currentType="currentType"
                   :currentId="currentId"
                   :postTypes="postTypes"
                   :fieldGroups="fieldGroups"
                   @navigate="navigate"
                   @reload="loadData" />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuth } from './composables/useAuth'
import { useApi } from './composables/useApi'

// Components
import ConfigView from './views/ConfigView.vue'
import ContentListView from './views/ContentListView.vue'
import ContentEditorView from './views/ContentEditorView.vue'
import MediaView from './views/MediaView.vue'

// Auth
const { user, isAuthenticated, isFirstTimeSetup, checkFirstTimeSetup, verifyAuth, login, register, logout } = useAuth()
const { apiRequest } = useApi()

// Forms
const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '' })
const showRegister = ref(false)
const authError = ref(null)

// Data
const postTypes = ref([])
const fieldGroups = ref([])

// Navigation
const currentView = ref('config')
const currentType = ref(null)
const currentId = ref(null)

const currentComponent = computed(() => {
  switch (currentView.value) {
    case 'config':
      return ConfigView
    case 'content-list':
      return ContentListView
    case 'content-edit':
    case 'content-create':
      return ContentEditorView
    case 'media':
      return MediaView
    default:
      return ConfigView
  }
})

// Methods
async function handleLogin() {
  authError.value = null
  try {
    await login(loginForm.value.email, loginForm.value.password)
    loginForm.value = { email: '', password: '' }
    await init()
  } catch (error) {
    authError.value = error.message || 'Login failed'
  }
}

async function handleRegister() {
  authError.value = null
  try {
    await register(registerForm.value.name, registerForm.value.email, registerForm.value.password)
    registerForm.value = { name: '', email: '', password: '' }
    await init()
  } catch (error) {
    authError.value = error.message || 'Registration failed'
  }
}

function handleLogout() {
  logout()
  currentView.value = 'post-types'
}

async function loadPostTypes() {
  try {
    postTypes.value = await apiRequest('GET', '/post-types')
  } catch (error) {
    console.error('Failed to load post types:', error)
  }
}

async function loadFieldGroups() {
  try {
    fieldGroups.value = await apiRequest('GET', '/field-groups')
  } catch (error) {
    console.error('Failed to load field groups:', error)
  }
}

async function loadData() {
  await loadPostTypes()
  await loadFieldGroups()
}

function navigate(view, type = null, id = null) {
  currentView.value = view
  currentType.value = type
  currentId.value = id
}

async function init() {
  if (isAuthenticated.value) {
    await loadData()

    // Navigate to first post type or configuration
    if (postTypes.value.length > 0) {
      navigate('content-list', postTypes.value[0].key)
    } else {
      navigate('config')
    }
  }
}

// Lifecycle
onMounted(async () => {
  await checkFirstTimeSetup()

  if (isFirstTimeSetup.value) {
    showRegister.value = true
  } else {
    const authenticated = await verifyAuth()
    if (authenticated) {
      await init()
    }
  }
})
</script>
