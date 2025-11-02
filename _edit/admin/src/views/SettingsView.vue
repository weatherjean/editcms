<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Settings</h1>
        <p class="text-sm opacity-60 mt-1">Configure email and system settings</p>
      </div>
    </div>

    <!-- Email Configuration -->
    <div class="card bg-base-100 border shadow">
      <div class="card-body">
        <div class="mb-4">
          <h2 class="card-title">📧 Email Configuration</h2>
          <p class="text-sm opacity-60">Configure the default sender for outgoing emails (used for contact forms, etc.)</p>
        </div>

        <form @submit.prevent="saveEmailSettings" class="space-y-4">
          <fieldset class="fieldset">
            <legend class="fieldset-legend">From Email</legend>
            <input
              type="email"
              v-model="emailConfig.from_email"
              required
              class="input w-full"
              placeholder="noreply@example.com"
            />
            <p class="text-xs opacity-60 mt-1">The email address that appears as the sender</p>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">From Name</legend>
            <input
              type="text"
              v-model="emailConfig.from_name"
              required
              class="input w-full"
              placeholder="My Website"
            />
            <p class="text-xs opacity-60 mt-1">The name that appears as the sender</p>
          </fieldset>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="loading loading-spinner loading-sm"></span>
              {{ saving ? 'Saving...' : 'Save Email Settings' }}
            </button>
            <button type="button" @click="testEmail" class="btn btn-outline" :disabled="testing">
              <svg v-if="!testing" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
              </svg>
              <span v-if="testing" class="loading loading-spinner loading-sm"></span>
              {{ testing ? 'Sending...' : 'Send Test Email' }}
            </button>
          </div>

          <div v-if="message" class="alert" :class="messageType === 'success' ? 'alert-success' : 'alert-error'">
            <svg v-if="messageType === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ message }}</span>
          </div>
        </form>
      </div>
    </div>

    <!-- Test Email Modal -->
    <dialog ref="testEmailDialog" class="modal">
      <div class="modal-box">
        <h3 class="font-bold text-lg mb-4">Send Test Email</h3>
        <form @submit.prevent="sendTestEmail" class="space-y-4">
          <fieldset class="fieldset">
            <legend class="fieldset-legend">To Email</legend>
            <input
              type="email"
              v-model="testEmailData.to"
              required
              class="input w-full"
              placeholder="test@example.com"
            />
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Subject</legend>
            <input
              type="text"
              v-model="testEmailData.subject"
              required
              class="input w-full"
              placeholder="Test Email"
            />
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Message</legend>
            <textarea
              v-model="testEmailData.message"
              required
              class="textarea w-full h-24"
              placeholder="This is a test email..."
            ></textarea>
          </fieldset>

          <div class="modal-action">
            <button type="button" class="btn" @click="closeTestEmailDialog">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="sending">
              <span v-if="sending" class="loading loading-spinner loading-sm"></span>
              {{ sending ? 'Sending...' : 'Send' }}
            </button>
          </div>
        </form>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'

const { apiRequest } = useApi()

const emailConfig = ref({
  from_email: '',
  from_name: ''
})

const testEmailData = ref({
  to: '',
  subject: 'Test Email from _edit CMS',
  message: 'This is a test email to verify your email configuration is working correctly.'
})

const message = ref('')
const messageType = ref('success')
const saving = ref(false)
const testing = ref(false)
const sending = ref(false)
const testEmailDialog = ref(null)

async function loadEmailSettings() {
  try {
    const response = await fetch('/_edit/config/email.json')
    if (response.ok) {
      const data = await response.json()
      emailConfig.value = data
    }
  } catch (error) {
    console.error('Failed to load email settings:', error)
  }
}

async function saveEmailSettings() {
  saving.value = true
  message.value = ''

  try {
    const blob = new Blob([JSON.stringify(emailConfig.value, null, 2)], { type: 'application/json' })
    const formData = new FormData()
    formData.append('file', blob, 'email.json')

    const response = await fetch('/_edit/api/config/email', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('edit_token')}`
      },
      body: formData
    })

    if (!response.ok) {
      throw new Error('Failed to save settings')
    }

    message.value = 'Email settings saved successfully!'
    messageType.value = 'success'
  } catch (error) {
    message.value = error.message || 'Failed to save settings'
    messageType.value = 'error'
  } finally {
    saving.value = false
  }
}

function testEmail() {
  testEmailDialog.value?.showModal()
}

function closeTestEmailDialog() {
  testEmailDialog.value?.close()
}

async function sendTestEmail() {
  sending.value = true

  try {
    const response = await fetch('/_edit/api/send-email', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(testEmailData.value)
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || 'Failed to send test email')
    }

    message.value = 'Test email sent successfully!'
    messageType.value = 'success'
    closeTestEmailDialog()
  } catch (error) {
    message.value = error.message || 'Failed to send test email'
    messageType.value = 'error'
  } finally {
    sending.value = false
  }
}

onMounted(() => {
  loadEmailSettings()
})
</script>
