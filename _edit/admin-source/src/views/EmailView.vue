<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">Email</h1>
        <p class="opacity-60 mt-1">Configure email settings and view send logs</p>
      </div>
    </div>

    <!-- Email Configuration -->
    <CardSection
      title="Email Configuration"
      description="Configure the default sender for outgoing emails (used for contact forms, etc.)"
      body-class="space-y-4"
    >

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
            <p class="opacity-60 mt-1">The email address that appears as the sender</p>
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
            <p class="opacity-60 mt-1">The name that appears as the sender</p>
          </fieldset>

          <!-- SMTP Configuration -->
          <div class="divider">SMTP Configuration (Required)</div>

          <p class="opacity-60 -mt-2 mb-4">SMTP is required for email delivery. Get credentials from your email service provider (Gmail, SendGrid, Mailgun, etc.)</p>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">SMTP Host</legend>
            <input
              type="text"
              v-model="emailConfig.smtp_host"
              required
              class="input w-full"
              placeholder="smtp.example.com"
            />
            <p class="opacity-60 mt-1">SMTP server hostname (e.g., smtp.gmail.com, smtp.sendgrid.net)</p>
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
              <p class="opacity-60 mt-1">Usually 587 (TLS) or 465 (SSL)</p>
            </fieldset>

            <fieldset class="fieldset">
              <legend class="fieldset-legend">Encryption</legend>
              <select v-model="emailConfig.smtp_encryption" required class="select w-full">
                <option value="tls">TLS</option>
                <option value="ssl">SSL</option>
                <option value="">None</option>
              </select>
              <p class="opacity-60 mt-1">Recommended: TLS</p>
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
            <p class="opacity-60 mt-1">SMTP authentication username</p>
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
            <p class="opacity-60 mt-1">SMTP authentication password</p>
          </fieldset>

          <fieldset class="fieldset">
            <legend class="fieldset-legend">Contact Form Recipient</legend>
            <input type="email" v-model="emailConfig.contact_recipient" required class="input w-full" placeholder="inquiries@example.com" />
            <p class="opacity-60 mt-1">Public contact forms send only to this address.</p>
          </fieldset>

          <div class="divider">Spam Protection</div>

          <p class="opacity-60 -mt-2 mb-4">
            ALTCHA protects contact forms using a challenge verified on your server. No external account is needed.
          </p>

          <label class="label cursor-pointer justify-start gap-4 mb-4">
            <input type="checkbox" v-model="emailConfig.captcha_enabled" class="toggle">
            <span class="label-text">Require ALTCHA verification for public forms</span>
          </label>

          <div class="flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving" class="loading loading-spinner loading-sm"></span>
              {{ saving ? 'Saving...' : 'Save Email Settings' }}
            </button>
            <button type="button" @click="testEmail" class="btn btn-outline" :disabled="testing">
              <svg v-if="!testing" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
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
    </CardSection>

    <!-- Email Logs -->
    <CardSection title="Email Send Logs" description="Recent email send attempts" body-class="">
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <h2 class="card-title">Email Send Logs</h2>
            <p class="opacity-60 mt-1">Recent email send attempts</p>
          </div>
          <div class="flex gap-2">
            <button @click="clearAllLogs" class="btn btn-error btn-outline gap-2" :disabled="emailLogs.length === 0">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
              </svg>
              Clear All
            </button>
            <button @click="loadLogs" class="btn gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              Refresh
            </button>
          </div>
        </div>
      </template>

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
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in emailLogs" :key="log.id">
                <td>
                  <div>{{ formatDate(log.created_at) }}</div>
                  <div class="opacity-60">{{ formatTime(log.created_at) }}</div>
                </td>
                <td>{{ log.to_address }}</td>
                <td>{{ log.subject }}</td>
                <td>
                  <span v-if="log.success" class="badge badge-success badge-sm">Success</span>
                  <span v-else class="badge badge-error badge-sm" :title="log.error_message">Failed</span>
                </td>
                <td class="text-right">
                  <div class="join">
                    <button @click="viewLog(log)" class="btn btn-ghost btn-sm join-item tooltip tooltip-left" data-tip="View Details">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                      </svg>
                    </button>
                    <button @click="deleteLog(log.id)" class="btn btn-ghost btn-error btn-sm join-item tooltip tooltip-left" data-tip="Delete">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="logsTotal > emailLogs.length" class="text-center mt-4">
          <p class="opacity-60">Showing {{ emailLogs.length }} of {{ logsTotal }} logs</p>
        </div>
    </CardSection>

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

    <!-- View Log Details Modal -->
    <dialog ref="viewLogDialog" class="modal">
      <div class="modal-box max-w-3xl">
        <h3 class="font-bold text-lg mb-4">Email Details</h3>
        <div v-if="selectedLog" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <div class="font-semibold opacity-60 text-sm">Date/Time</div>
              <div>{{ formatDate(selectedLog.created_at) }} {{ formatTime(selectedLog.created_at) }}</div>
            </div>
            <div>
              <div class="font-semibold opacity-60 text-sm">Status</div>
              <div>
                <span v-if="selectedLog.success" class="badge badge-success">Success</span>
                <span v-else class="badge badge-error">Failed</span>
              </div>
            </div>
          </div>

          <div>
            <div class="font-semibold opacity-60 text-sm">To</div>
            <div>{{ selectedLog.to_address }}</div>
          </div>

          <div>
            <div class="font-semibold opacity-60 text-sm">Subject</div>
            <div>{{ selectedLog.subject }}</div>
          </div>

          <div v-if="selectedLog.from_name">
            <div class="font-semibold opacity-60 text-sm">From Name</div>
            <div>{{ selectedLog.from_name }}</div>
          </div>

          <div v-if="selectedLog.reply_to">
            <div class="font-semibold opacity-60 text-sm">Reply To</div>
            <div>{{ selectedLog.reply_to }}</div>
          </div>

          <div>
            <div class="font-semibold opacity-60 text-sm">Message Type</div>
            <div>{{ selectedLog.is_html ? 'HTML' : 'Plain Text' }}</div>
          </div>

          <div>
            <div class="font-semibold opacity-60 text-sm">Message</div>
            <div class="p-3 bg-base-200 rounded whitespace-pre-wrap font-mono text-sm max-h-96 overflow-y-auto">{{ selectedLog.message || '(No message content saved)' }}</div>
          </div>

          <div v-if="!selectedLog.success && selectedLog.error_message">
            <div class="font-semibold opacity-60 text-sm">Error</div>
            <div class="p-3 bg-error/10 text-error rounded whitespace-pre-wrap font-mono text-sm">{{ selectedLog.error_message }}</div>
          </div>

          <div>
            <div class="font-semibold opacity-60 text-sm">IP Address</div>
            <div class="font-mono">{{ selectedLog.ip_address || '-' }}</div>
          </div>
        </div>

        <div class="modal-action">
          <button type="button" class="btn" @click="closeViewLogDialog">Close</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button @click="closeViewLogDialog">close</button>
      </form>
    </dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import CardSection from '../components/CardSection.vue'

const { apiRequest } = useApi()

const emailConfig = ref({
  from_email: '',
  from_name: '',
  smtp_host: '',
  smtp_port: '587',
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  contact_recipient: '',
  captcha_enabled: true
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
const viewLogDialog = ref(null)
const selectedLog = ref(null)

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
    await apiRequest('POST', '/email-test', testEmailData.value)

    message.value = 'Test email sent successfully!'
    messageType.value = 'success'
    closeTestEmailDialog()

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

function viewLog(log) {
  selectedLog.value = log
  viewLogDialog.value?.showModal()
}

function closeViewLogDialog() {
  viewLogDialog.value?.close()
  selectedLog.value = null
}

async function deleteLog(logId) {
  if (!confirm('Are you sure you want to delete this email log?')) {
    return
  }

  try {
    await apiRequest('DELETE', `/email-logs/${logId}`)
    emailLogs.value = emailLogs.value.filter(log => log.id !== logId)
    logsTotal.value--
    message.value = 'Email log deleted successfully'
    messageType.value = 'success'
  } catch (error) {
    message.value = error.message || 'Failed to delete email log'
    messageType.value = 'error'
  }
}

async function clearAllLogs() {
  if (!confirm('Are you sure you want to delete ALL email logs? This cannot be undone.')) {
    return
  }

  try {
    const result = await apiRequest('DELETE', '/email-logs')
    emailLogs.value = []
    logsTotal.value = 0
    message.value = result.message || 'All email logs cleared successfully'
    messageType.value = 'success'
  } catch (error) {
    message.value = error.message || 'Failed to clear email logs'
    messageType.value = 'error'
  }
}

onMounted(() => {
  loadEmailSettings()
  loadLogs()
})
</script>
