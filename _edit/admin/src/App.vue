<template>
  <!-- Login Screen -->
  <div v-if="!isAuthenticated" class="hero min-h-screen bg-base-200/50">
    <div class="hero-content">
      <div class="card w-96 bg-base-100 shadow-xl">
        <div class="card-body">
          <h2 class="card-title text-center justify-center text-2xl">_edit</h2>
          <p class="text-center text-sm opacity-60 mb-4">Headless CMS Admin</p>

          <!-- Login Form -->
          <form v-if="!showRegister" @submit.prevent="handleLogin">
            <div class="form-control">
              <label class="label"><span class="label-text text-base">Email</span></label>
              <input type="email" v-model="loginForm.email" required class="input input-bordered text-base" placeholder="you@example.com" />
            </div>

            <div class="divider my-4"></div>

            <div class="form-control">
              <label class="label"><span class="label-text text-base">Password</span></label>
              <input type="password" v-model="loginForm.password" required class="input input-bordered text-base" placeholder="••••••••" />
            </div>

            <div class="divider my-4"></div>

            <button type="submit" class="btn btn-primary w-full text-base">Sign In</button>

            <div class="divider my-4"></div>

            <div class="text-center">
              <a href="#" @click.prevent="showRegister = true" class="link link-primary text-base">Create an account</a>
            </div>
          </form>

          <!-- Register Form -->
          <form v-else @submit.prevent="handleRegister">
            <div class="form-control">
              <label class="label"><span class="label-text text-base">Name</span></label>
              <input type="text" v-model="registerForm.name" required class="input input-bordered text-base" placeholder="John Doe" />
            </div>

            <div class="divider my-4"></div>

            <div class="form-control">
              <label class="label"><span class="label-text text-base">Email</span></label>
              <input type="email" v-model="registerForm.email" required class="input input-bordered text-base" placeholder="you@example.com" />
            </div>

            <div class="divider my-4"></div>

            <div class="form-control">
              <label class="label"><span class="label-text text-base">Password</span></label>
              <input type="password" v-model="registerForm.password" required class="input input-bordered text-base" placeholder="••••••••" />
            </div>

            <div class="divider my-4"></div>

            <button type="submit" class="btn btn-primary w-full text-base">Create Account</button>

            <div v-if="!isFirstTimeSetup" class="divider my-4"></div>

            <div v-if="!isFirstTimeSetup" class="text-center">
              <a href="#" @click.prevent="showRegister = false" class="link link-primary text-base">Back to login</a>
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
  <div v-else class="drawer lg:drawer-open">
    <input id="sidebar-drawer" type="checkbox" class="drawer-toggle" />

    <!-- Main Content -->
    <div class="drawer-content flex flex-col">
      <!-- Navbar -->
      <div class="navbar bg-base-100 border-b border-base-300 shadow-sm">
        <div class="flex-none lg:hidden">
          <label for="sidebar-drawer" class="btn btn-square btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block h-5 w-5 stroke-current">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
          </label>
        </div>
        <div class="flex-1 px-4">
          <span class="text-xl font-bold">_edit</span>
        </div>
        <div class="flex-none">
          <div class="dropdown dropdown-end">
            <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar placeholder">
              <div class="bg-neutral text-neutral-content w-10 rounded-full">
                <span>{{ user?.name?.charAt(0).toUpperCase() }}</span>
              </div>
            </div>
            <ul tabindex="0" class="menu dropdown-content bg-base-100 rounded-box z-[1] mt-3 w-52 p-2 shadow">
              <li class="menu-title">{{ user?.name }}</li>
              <li><a @click="handleLogout">Logout</a></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Page Content -->
      <main class="flex-1 overflow-y-auto bg-base-200 p-4 md:p-6 lg:p-8">
        <component :is="currentComponent"
                   :currentType="currentType"
                   :currentId="currentId"
                   :postTypes="postTypes"
                   :fieldGroups="fieldGroups"
                   @navigate="navigate"
                   @reload="loadData" />
      </main>
    </div>

    <!-- Sidebar -->
    <div class="drawer-side z-10">
      <label for="sidebar-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
      <aside class="bg-base-100 min-h-full w-64 border-r border-base-300/50 shadow-sm">
        <div class="sticky top-0">
          <div class="p-4 border-b border-base-300 lg:hidden">
            <span class="text-xl font-bold">_edit</span>
          </div>

          <ul class="menu p-4">
            <li class="menu-title">Content</li>
            <li v-for="postType in postTypes" :key="postType.key">
              <a @click="navigate('content-list', postType.key)" :class="{ 'active': currentView === 'content-list' && currentType === postType.key }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 opacity-30">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                {{ postType.label_plural }}
              </a>
            </li>
            <li>
              <a @click="navigate('media')" :class="{ 'active': currentView === 'media' }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 opacity-30">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Media Library
              </a>
            </li>

            <li class="menu-title mt-4">Manage</li>
            <li>
              <a @click="navigate('config')" :class="{ 'active': currentView === 'config' }">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 opacity-30">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                Configuration
              </a>
            </li>
          </ul>
        </div>
      </aside>
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
