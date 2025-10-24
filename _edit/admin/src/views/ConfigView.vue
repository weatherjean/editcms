<template>
  <div class="view-container">
    <div class="view-header">
      <h2>Configuration</h2>
      <p class="help-text">Edit your CMS configuration. This JSON file defines all post types and field groups.</p>
    </div>

    <div class="config-section">
      <JsonEditor
        title="CMS Configuration (config.json)"
        :modelValue="config"
        @save="saveConfig"
      />
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

<style scoped>
.config-section {
  margin-top: 20px;
}
</style>
