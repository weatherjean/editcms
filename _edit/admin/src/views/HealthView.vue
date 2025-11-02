<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold">System Health</h1>
        <p class="text-sm opacity-60 mt-1">Check system requirements and configuration</p>
      </div>
      <button @click="loadHealth" class="btn btn-sm gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
        </svg>
        Refresh
      </button>
    </div>

    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg"></span>
    </div>

    <div v-else class="space-y-6">
      <!-- Overall Status -->
      <div class="card bg-base-100 border shadow">
        <div class="card-body">
          <div class="flex items-center gap-4">
            <div class="flex-shrink-0">
              <div v-if="health?.status === 'ok'" class="w-16 h-16 rounded-full bg-success/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              <div v-else-if="health?.status === 'warning'" class="w-16 h-16 rounded-full bg-warning/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-warning" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div v-else class="w-16 h-16 rounded-full bg-error/10 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-error" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <h2 class="text-2xl font-bold">{{ health?.message || 'Loading...' }}</h2>
              <p class="text-sm opacity-60 mt-1">
                {{ health?.status === 'ok' ? 'Your system is properly configured' :
                   health?.status === 'warning' ? 'Some features may not work correctly' :
                   'Critical issues need attention' }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Individual Checks -->
      <div class="card bg-base-100 border shadow">
        <div class="card-body">
          <h2 class="card-title mb-4">System Checks</h2>

          <div class="space-y-3">
            <!-- PHP Version -->
            <div v-if="health?.checks?.php_version" class="flex items-start gap-3 p-3 rounded-lg bg-base-200">
              <div class="flex-shrink-0 mt-1">
                <svg v-if="health.checks.php_version.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-error" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold">PHP Version</div>
                <div class="text-sm opacity-60">{{ health.checks.php_version.message }}</div>
                <div class="text-xs font-mono opacity-40 mt-1">{{ health.checks.php_version.value }}</div>
              </div>
            </div>

            <!-- Extensions -->
            <div v-if="health?.checks?.extensions" class="flex items-start gap-3 p-3 rounded-lg bg-base-200">
              <div class="flex-shrink-0 mt-1">
                <svg v-if="health.checks.extensions.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-error" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold">PHP Extensions</div>
                <div class="text-sm opacity-60">{{ health.checks.extensions.message }}</div>
              </div>
            </div>

            <!-- Database -->
            <div v-if="health?.checks?.database_writable" class="flex items-start gap-3 p-3 rounded-lg bg-base-200">
              <div class="flex-shrink-0 mt-1">
                <svg v-if="health.checks.database_writable.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-warning" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold">Database Directory</div>
                <div class="text-sm opacity-60">{{ health.checks.database_writable.message }}</div>
              </div>
            </div>

            <!-- Uploads -->
            <div v-if="health?.checks?.uploads_writable" class="flex items-start gap-3 p-3 rounded-lg bg-base-200">
              <div class="flex-shrink-0 mt-1">
                <svg v-if="health.checks.uploads_writable.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-warning" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold">Uploads Directory</div>
                <div class="text-sm opacity-60">{{ health.checks.uploads_writable.message }}</div>
              </div>
            </div>

            <!-- Config -->
            <div v-if="health?.checks?.config_writable" class="flex items-start gap-3 p-3 rounded-lg bg-base-200">
              <div class="flex-shrink-0 mt-1">
                <svg v-if="health.checks.config_writable.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-success" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-warning" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="flex-1">
                <div class="font-semibold">Configuration Directory</div>
                <div class="text-sm opacity-60">{{ health.checks.config_writable.message }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Help Card -->
      <div v-if="health?.status !== 'ok'" class="card bg-base-100 border shadow border-warning/20">
        <div class="card-body">
          <h3 class="font-bold text-lg">Need Help?</h3>
          <div class="text-sm space-y-2">
            <p>If you're experiencing issues, here are some common solutions:</p>
            <ul class="list-disc list-inside space-y-1 opacity-80">
              <li><strong>Directory not writable:</strong> Use cPanel File Manager to set permissions to 755</li>
              <li><strong>Missing PHP extensions:</strong> Contact your hosting provider to enable required extensions</li>
              <li><strong>PHP version too old:</strong> Ask your host to upgrade to PHP 8.1 or newer</li>
            </ul>
            <p class="mt-4">For more help, check the <code class="bg-base-200 px-2 py-1 rounded">DEPLOYMENT.md</code> file in your installation.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const health = ref(null)
const loading = ref(false)

async function loadHealth() {
  loading.value = true
  try {
    const response = await fetch('/_edit/api/health')
    health.value = await response.json()
  } catch (error) {
    console.error('Failed to load health status:', error)
    health.value = {
      status: 'error',
      message: 'Failed to connect to API',
      checks: {}
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadHealth()
})
</script>
