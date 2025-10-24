<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-3xl font-bold">Configuration</h1>
      <p class="text-sm opacity-60 mt-1">Define post types and field groups for your CMS</p>
    </div>

    <div class="card bg-base-100 border shadow">
      <div class="card-body">
        <JsonEditor
          title="config.json"
          :modelValue="config"
          @save="saveConfig"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi'
import JsonEditor from '../components/JsonEditor.vue'

const { apiRequest } = useApi()

const config = ref({
  post_types: [],
  field_groups: []
})

async function loadConfig() {
  try {
    config.value = await apiRequest('GET', '/config')
  } catch (error) {
    console.error('Failed to load configuration:', error)
    alert('Failed to load configuration: ' + error.message)
  }
}

async function saveConfig(data) {
  try {
    await apiRequest('PUT', '/config', data)
    alert('Configuration saved successfully! Reload the page to see changes.')
    await loadConfig() // Reload to ensure we have the latest
  } catch (error) {
    alert('Failed to save configuration: ' + error.message)
  }
}

onMounted(() => {
  loadConfig()
})
</script>
