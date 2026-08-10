<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">User Management</h1>
        <p class="opacity-60 mt-1">Manage admin users and permissions</p>
      </div>
      <button @click="showCreateModal = true" class="btn btn-primary gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Add User
      </button>
    </div>

    <div v-if="error && !showCreateModal && !showPasswordModal && !showDeleteModal" class="alert alert-error">{{ error }}</div>

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
                <th>Role</th>
                <th>Created</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>
                  <div class="flex items-center gap-3">
                    <div class="avatar placeholder">
                      <div class="w-10 rounded-full bg-primary text-primary-content">
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
                <td>
                  <select :value="user.role" @change="changeRole(user, $event.target.value)" class="select select-sm" :disabled="submitting">
                    <option value="editor">Editor</option>
                    <option value="admin">Administrator</option>
                  </select>
                </td>
                <td>{{ formatDate(user.created_at) }}</td>
                <td class="text-right">
                  <div class="join">
                    <button @click="openPasswordModal(user)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="Password">
                      <IconLock />
                    </button>
                    <button
                      v-if="user.id !== currentUserId"
                      @click="confirmDelete(user)"
                      class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left"
                      data-tip="Delete">
                      <IconTrash />
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

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Role</legend>
            <select v-model="createForm.role" class="select w-full">
              <option value="editor">Editor — content and media</option>
              <option value="admin">Administrator — full access</option>
            </select>
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
        <p class="opacity-60 mb-4">Changing password for: <strong>{{ selectedUser?.name }}</strong></p>
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
import IconLock from '../components/icons/IconLock.vue'
import IconTrash from '../components/icons/IconTrash.vue'

const { apiRequest } = useApi()
const { user: currentUser, logout } = useAuth()

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
  password: '',
  role: 'editor'
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

async function changeRole(account, role) {
  error.value = null
  submitting.value = true
  try {
    const result = await apiRequest('PUT', `/users/${account.id}/role`, { role })
    if (result.reauthenticate) {
      await logout()
      window.location.assign('/_edit/admin/')
    } else await loadUsers()
  } catch (err) {
    error.value = err.message
    await loadUsers()
  } finally {
    submitting.value = false
  }
}

async function handleChangePassword() {
  error.value = null

  if (passwordForm.value.password !== passwordForm.value.confirmPassword) {
    error.value = 'Passwords do not match'
    return
  }

  if (passwordForm.value.password.length < 8) {
    error.value = 'Password must be at least 8 characters'
    return
  }

  submitting.value = true

  try {
    const result = await apiRequest('PUT', `/users/${selectedUser.value.id}`, {
      password: passwordForm.value.password
    })
    closePasswordModal()
    if (result.reauthenticate) {
      await logout()
      window.location.assign('/_edit/admin/')
    }
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
  createForm.value = { name: '', email: '', password: '', role: 'editor' }
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
