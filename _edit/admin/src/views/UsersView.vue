<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">User Management</h1>
        <p class="text-sm opacity-60 mt-1">Manage admin users and permissions</p>
      </div>
      <button @click="showCreateModal = true" class="btn btn-primary gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add User
      </button>
    </div>

    <!-- Users Table -->
    <div class="card bg-base-100 border shadow">
      <div class="card-body">
        <div v-if="loading" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-lg"></span>
        </div>

        <div v-else-if="users.length === 0" class="text-center py-8 text-base-content/60">
          No users found
        </div>

        <div v-else class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Created</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                      <div class="w-10 rounded-full bg-neutral text-neutral-content">
                        <span class="flex items-center justify-center h-full">{{ user.name.charAt(0).toUpperCase() }}</span>
                      </div>
                    </div>
                    <div>
                      <div class="font-bold">{{ user.name }}</div>
                      <div v-if="user.id === currentUserId" class="badge badge-sm badge-ghost">You</div>
                    </div>
                  </div>
                </td>
                <td>{{ user.email }}</td>
                <td>{{ formatDate(user.created_at) }}</td>
                <td class="text-right">
                  <div class="flex gap-2 justify-end">
                    <button @click="openPasswordModal(user)" class="btn btn-ghost btn-sm gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                      </svg>
                      Change Password
                    </button>
                    <button
                      v-if="user.id !== currentUserId"
                      @click="confirmDelete(user)"
                      class="btn btn-ghost btn-sm text-error gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                      Delete
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create User Modal -->
    <dialog :class="{'modal modal-open': showCreateModal}" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Add New User</h3>
        <form @submit.prevent="handleCreateUser" class="space-y-4">
          <fieldset class="fieldset">
            <legend class="fieldset-legend">Name</legend>
            <input type="text" v-model="createForm.name" required class="input w-full" />
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Email</legend>
            <input type="email" v-model="createForm.email" required class="input w-full" />
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Password</legend>
            <input type="password" v-model="createForm.password" required class="input w-full" />
          </fieldset>

          <div v-if="error" class="alert alert-error">
            <span>{{ error }}</span>
          </div>

          <div class="modal-action">
            <button type="button" @click="closeCreateModal" class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              <span v-if="submitting" class="loading loading-spinner"></span>
              Create User
            </button>
          </div>
        </form>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeCreateModal">close</button>
      </form>
    </dialog>

    <!-- Change Password Modal -->
    <dialog :class="{'modal modal-open': showPasswordModal}" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Change Password</h3>
        <p class="text-sm opacity-60 mb-4">Changing password for: <strong>{{ selectedUser?.name }}</strong></p>
        <form @submit.prevent="handleChangePassword" class="space-y-4">
          <fieldset class="fieldset">
            <legend class="fieldset-legend">New Password</legend>
            <input type="password" v-model="passwordForm.password" required class="input w-full" />
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Confirm Password</legend>
            <input type="password" v-model="passwordForm.confirmPassword" required class="input w-full" />
          </fieldset>

          <div v-if="error" class="alert alert-error">
            <span>{{ error }}</span>
          </div>

          <div class="modal-action">
            <button type="button" @click="closePasswordModal" class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="submitting">
              <span v-if="submitting" class="loading loading-spinner"></span>
              Update Password
            </button>
          </div>
        </form>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closePasswordModal">close</button>
      </form>
    </dialog>

    <!-- Delete Confirmation Modal -->
    <dialog :class="{'modal modal-open': showDeleteModal}" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Delete User</h3>
        <p class="mb-4">Are you sure you want to delete <strong>{{ selectedUser?.name }}</strong>? This action cannot be undone.</p>

        <div v-if="error" class="alert alert-error mb-4">
          <span>{{ error }}</span>
        </div>

        <div class="modal-action">
          <button type="button" @click="closeDeleteModal" class="btn">Cancel</button>
          <button @click="handleDeleteUser" class="btn btn-error" :disabled="submitting">
            <span v-if="submitting" class="loading loading-spinner"></span>
            Delete User
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeDeleteModal">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useApi } from '../composables/useApi'
import { useAuth } from '../composables/useAuth'

const { apiRequest } = useApi()
const { user: currentUser } = useAuth()

const users = ref([])
const loading = ref(false)
const submitting = ref(false)
const error = ref(null)

const showCreateModal = ref(false)
const showPasswordModal = ref(false)
const showDeleteModal = ref(false)
const selectedUser = ref(null)

const createForm = ref({
  name: '',
  email: '',
  password: ''
})

const passwordForm = ref({
  password: '',
  confirmPassword: ''
})

const currentUserId = computed(() => currentUser.value?.id)

async function loadUsers() {
  loading.value = true
  try {
    users.value = await apiRequest('GET', '/users')
  } catch (err) {
    error.value = err.message || 'Failed to load users'
  } finally {
    loading.value = false
  }
}

async function handleCreateUser() {
  error.value = null
  submitting.value = true

  try {
    const newUser = await apiRequest('POST', '/users', createForm.value)
    users.value.unshift(newUser)
    closeCreateModal()
  } catch (err) {
    error.value = err.message || 'Failed to create user'
  } finally {
    submitting.value = false
  }
}

function openPasswordModal(user) {
  selectedUser.value = user
  passwordForm.value = { password: '', confirmPassword: '' }
  error.value = null
  showPasswordModal.value = true
}

async function handleChangePassword() {
  error.value = null

  if (passwordForm.value.password !== passwordForm.value.confirmPassword) {
    error.value = 'Passwords do not match'
    return
  }

  if (passwordForm.value.password.length < 6) {
    error.value = 'Password must be at least 6 characters'
    return
  }

  submitting.value = true

  try {
    await apiRequest('PUT', `/users/${selectedUser.value.id}`, {
      password: passwordForm.value.password
    })
    closePasswordModal()
  } catch (err) {
    error.value = err.message || 'Failed to change password'
  } finally {
    submitting.value = false
  }
}

function confirmDelete(user) {
  selectedUser.value = user
  error.value = null
  showDeleteModal.value = true
}

async function handleDeleteUser() {
  error.value = null
  submitting.value = true

  try {
    await apiRequest('DELETE', `/users/${selectedUser.value.id}`)
    users.value = users.value.filter(u => u.id !== selectedUser.value.id)
    closeDeleteModal()
  } catch (err) {
    error.value = err.message || 'Failed to delete user'
  } finally {
    submitting.value = false
  }
}

function closeCreateModal() {
  showCreateModal.value = false
  createForm.value = { name: '', email: '', password: '' }
  error.value = null
}

function closePasswordModal() {
  showPasswordModal.value = false
  selectedUser.value = null
  passwordForm.value = { password: '', confirmPassword: '' }
  error.value = null
}

function closeDeleteModal() {
  showDeleteModal.value = false
  selectedUser.value = null
  error.value = null
}

function formatDate(dateString) {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

onMounted(() => {
  loadUsers()
})
</script>
