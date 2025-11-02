<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Email</h1>
        <p class="text-sm opacity-60 mt-1">Configure email settings and view send logs</p>
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

          <!-- SMTP Configuration -->
          <div class="divider">SMTP Configuration (Required)</div>

          <p class="text-sm opacity-60 -mt-2 mb-4">SMTP is required for email delivery. Get credentials from your email service provider (Gmail, SendGrid, Mailgun, etc.)</p>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">SMTP Host</legend>
            <input
              type="text"
              v-model="emailConfig.smtp_host"
              required
              class="input w-full"
              placeholder="smtp.example.com"
            />
            <p class="text-xs opacity-60 mt-1">SMTP server hostname (e.g., smtp.gmail.com, smtp.sendgrid.net)</p>
          </fieldset>

          <div class="grid grid-cols-2 gap-4">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">SMTP Port</legend>
              <input
                type="number"
                v-model="emailConfig.smtp_port"
                required
                class="input w-full"
                placeholder="587"
              />
              <p class="text-xs opacity-60 mt-1">Usually 587 (TLS) or 465 (SSL)</p>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Encryption</legend>
              <select v-model="emailConfig.smtp_encryption" required class="select w-full">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="">None</option>
              </select>
              <p class="text-xs opacity-60 mt-1">Recommended: TLS</p>
            </fieldset>
          </div>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">SMTP Username</legend>
            <input
              type="text"
              v-model="emailConfig.smtp_username"
              required
              class="input w-full"
              placeholder="username or email"
              autocomplete="off"
            />
            <p class="text-xs opacity-60 mt-1">SMTP authentication username</p>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">SMTP Password</legend>
            <input
              type="password"
              v-model="emailConfig.smtp_password"
              required
              class="input w-full"
              placeholder="••••••••"
              autocomplete="new-password"
            />
            <p class="text-xs opacity-60 mt-1">SMTP authentication password</p>
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

    <!-- Email Logs -->
    <div class="card bg-base-100 border shadow">
      <div class="card-body">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="card-title">📊 Email Send Logs</h2>
            <p class="text-sm opacity-60">Recent email send attempts</p>
          </div>
          <button @click="loadLogs" class="btn btn-sm gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Refresh
          </button>
        </div>

        <div v-if="loadingLogs" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-md"></span>
        </div>

        <div v-else-if="emailLogs.length === 0" class="text-center py-8 text-base-content/60">
          No email logs found. Send a test email to see it appear here.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Date</th>
                <th>To</th>
                <th>Subject</th>
                <th>Status</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in emailLogs" :key="log.id">
                <td class="text-sm">
                  <div>{{ formatDate(log.created_at) }}</div>
                  <div class="text-xs opacity-60">{{ formatTime(log.created_at) }}</div>
                </td>
                <td class="text-sm">{{ log.to_address }}</td>
                <td class="text-sm">{{ log.subject }}</td>
                <td>
                  <span v-if="log.success" class="badge badge-success badge-sm">Success</span>
                  <span v-else class="badge badge-error badge-sm" :title="log.error_message">Failed</span>
                </td>
                <td class="text-sm font-mono opacity-60">{{ log.ip_address || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="logsTotal > emailLogs.length" class="text-center mt-4">
          <p class="text-sm opacity-60">Showing {{ emailLogs.length }} of {{ logsTotal }} logs</p>
        </div>
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
  from_name: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls'
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

const emailLogs = ref([])
const loadingLogs = ref(false)
const logsTotal = ref(0)

async function loadEmailSettings() {
  try {
    const data = await apiRequest('GET', '/email-settings')
    emailConfig.value = data
  } catch (error) {
    console.error('Failed to load email settings:', error)
  }
}

async function saveEmailSettings() {
  saving.value = true
  message.value = ''

  try {
    await apiRequest('PUT', '/email-settings', emailConfig.value)
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
    // Step 1: Get a token
    const tokenResponse = await fetch('/_edit/api/send-email/token')
    if (!tokenResponse.ok) {
      throw new Error('Failed to get email token')
    }
    const tokenData = await tokenResponse.json()

    // Step 2: Send email with token
    const response = await fetch('/_edit/api/send-email', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        ...testEmailData.value,
        token: tokenData.token
      })
    })

    const data = await response.json()

    if (!response.ok) {
      throw new Error(data.error || 'Failed to send test email')
    }

    message.value = 'Test email sent successfully!'
    messageType.value = 'success'
    closeTestEmailDialog()

    // Reload logs to show the new send
    loadLogs()
  } catch (error) {
    message.value = error.message || 'Failed to send test email'
    messageType.value = 'error'
  } finally {
    sending.value = false
  }
}

async function loadLogs() {
  loadingLogs.value = true
  try {
    const data = await apiRequest('GET', '/email-logs?limit=50')
    emailLogs.value = data.logs
    logsTotal.value = data.total
  } catch (error) {
    console.error('Failed to load email logs:', error)
  } finally {
    loadingLogs.value = false
  }
}

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString()
}

function formatTime(dateString) {
  const date = new Date(dateString)
  return date.toLocaleTimeString()
}

onMounted(() => {
  loadEmailSettings()
  loadLogs()
})
</script>
