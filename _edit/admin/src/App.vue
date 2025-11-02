<template>
  <!-- Loading Screen -->
  <div v-if="checkingAuth" class="hero min-h-screen bg-base-200">
    <div class="flex flex-col items-center gap-4">
      <span class="loading loading-spinner loading-lg"></span>
      <p class="text-sm opacity-60">Loading...</p>
    </div>
  </div>

  <!-- Login Screen -->
  <div v-else-if="!isAuthenticated" class="hero min-h-screen bg-base-200">
    <div class="hero-content">
      <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body gap-4">
          <h2 class="card-title justify-center text-2xl">_edit</h2>
          <p class="text-center text-sm opacity-60">Headless CMS Admin</p>

          <!-- Login Form -->
          <form v-if="!showRegister" @submit.prevent="handleLogin" class="space-y-4">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Email</legend>
              <input type="email" v-model="loginForm.email" required class="input w-full" placeholder="you@example.com" />
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Password</legend>
              <input type="password" v-model="loginForm.password" required class="input w-full" placeholder="••••••••" />
            </fieldset>

            <button type="submit" class="btn btn-primary w-full">Sign In</button>

            <div class="text-center">
              <a href="#" @click.prevent="showRegister = true" class="link link-primary">Create an account</a>
            </div>
          </form>

          <!-- Register Form -->
          <form v-else @submit.prevent="handleRegister" class="space-y-4">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Name</legend>
              <input type="text" v-model="registerForm.name" required class="input w-full" placeholder="John Doe" />
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Email</legend>
              <input type="email" v-model="registerForm.email" required class="input w-full" placeholder="you@example.com" />
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Password</legend>
              <input type="password" v-model="registerForm.password" required class="input w-full" placeholder="••••••••" />
            </fieldset>

            <button type="submit" class="btn btn-primary w-full">Create Account</button>

            <div v-if="!isFirstTimeSetup" class="text-center">
              <a href="#" @click.prevent="showRegister = false" class="link link-primary">Back to login</a>
            </div>
          </form>

          <div v-if="authError" class="alert alert-error mt-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ authError }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Admin Interface -->
  <div v-else class="flex flex-col min-h-screen">
    <!-- Header -->
    <div class="navbar bg-base-100 border-b shadow-sm">
      <div class="flex-1 px-4">
        <span class="text-xl font-bold">_edit</span>
      </div>
      <div class="flex-none gap-2">
        <button @click="router.push('/media')" class="btn btn-ghost btn-sm gap-2" :class="{ 'btn-active': route.name === 'media' }">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span class="hidden sm:inline">Media</span>
        </button>
        <button @click="router.push('/users')" class="btn btn-ghost btn-sm gap-2" :class="{ 'btn-active': route.name === 'users' }">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
          </svg>
          <span class="hidden sm:inline">Users</span>
        </button>
        <button @click="router.push('/config')" class="btn btn-ghost btn-sm gap-2" :class="{ 'btn-active': route.name === 'config' }">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
          </svg>
          <span class="hidden sm:inline">Configuration</span>
        </button>
        <button @click="router.push('/email')" class="btn btn-ghost btn-sm gap-2" :class="{ 'btn-active': route.name === 'email' }">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
          </svg>
          <span class="hidden sm:inline">Email</span>
        </button>
        <button @click="router.push('/health')" class="btn btn-ghost btn-sm gap-2" :class="{ 'btn-active': route.name === 'health' }">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
          </svg>
          <span class="hidden sm:inline">Health</span>
        </button>
        <div class="dropdown dropdown-end">
          <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
            <div class="w-10 rounded-full bg-neutral text-neutral-content">
              <span class="flex items-center justify-center h-full">{{ user?.name?.charAt(0).toUpperCase() }}</span>
            </div>
          </div>
          <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-[1] mt-3 w-52 p-2 shadow">
            <li class="menu-title">{{ user?.name }}</li>
            <li>
              <a @click="toggleTheme">
                <svg v-if="!isDarkMode" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                  <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
                </svg>
                {{ isDarkMode ? 'Light Mode' : 'Dark Mode' }}
              </a>
            </li>
            <li><a @click="handleLogout">Logout</a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Horizontal Navigation Menu -->
    <div class="bg-base-100 border-b overflow-x-auto">
      <div class="tabs tabs-boxed bg-transparent mx-4 gap-1 flex-nowrap min-w-max">
        <a v-for="postType in postTypes" :key="postType.key"
           @click="router.push(`/${postType.key}`)"
           class="tab whitespace-nowrap"
           :class="{ 'tab-active': route.params.type === postType.key }">
          {{ postType.label_plural }}
        </a>
      </div>
    </div>

    <!-- Page Content -->
    <main class="flex-1 overflow-y-auto bg-base-200 p-4 md:p-6 lg:p-8">
      <router-view
        :postTypes="postTypes"
        :fieldGroups="fieldGroups"
        @reload="loadData" />
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from './composables/useAuth'
import { useApi } from './composables/useApi'

// Router
const router = useRouter()
const route = useRoute()

// Auth
const { user, isAuthenticated, isFirstTimeSetup, checkFirstTimeSetup, verifyAuth, login, register, logout } = useAuth()
const { apiRequest } = useApi()

// Forms
const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '' })
const showRegister = ref(false)
const authError = ref(null)
const checkingAuth = ref(true) // Loading state while checking authentication

// Theme
const isDarkMode = ref(false)

// Data
const postTypes = ref([])
const fieldGroups = ref([])

// Current state from route
const currentType = computed(() => route.params.type || null)

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
  router.push('/config')
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

function toggleTheme() {
  isDarkMode.value = !isDarkMode.value
  const theme = isDarkMode.value ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', theme)
  localStorage.setItem('edit_dark_mode', isDarkMode.value ? 'true' : 'false')
}

function loadTheme() {
  const savedMode = localStorage.getItem('edit_dark_mode') === 'true'
  isDarkMode.value = savedMode
  const theme = savedMode ? 'dark' : 'light'
  document.documentElement.setAttribute('data-theme', theme)
}

async function init() {
  if (isAuthenticated.value) {
    await loadData()

    // Only redirect to default if user is on root path
    if (route.path === '/' || route.path === '') {
      if (postTypes.value.length > 0) {
        router.push(`/${postTypes.value[0].key}`)
      } else {
        router.push('/config')
      }
    }
    // Otherwise stay on current route
  }
}

// Lifecycle
onMounted(async () => {
  loadTheme()

  await checkFirstTimeSetup()

  if (isFirstTimeSetup.value) {
    showRegister.value = true
    checkingAuth.value = false
  } else {
    const authenticated = await verifyAuth()
    if (authenticated) {
      await init()
    }
    checkingAuth.value = false
  }
})
</script>
