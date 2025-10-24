<template>
  <div class="json-editor">
    <div class="json-editor-header">
      <h3>{{ title }}</h3>
      <div class="json-editor-actions">
        <button type="button" @click="formatJson" class="btn btn-sm btn-secondary">Format</button>
        <button type="button" @click="saveJson" class="btn btn-sm btn-primary" :disabled="!isValid || !hasChanges">
          Save
        </button>
      </div>
    </div>

    <div class="json-editor-body">
      <div ref="editorContainer" class="monaco-container"></div>

      <div v-if="!isValid" class="json-error-message">
        <strong>Invalid JSON:</strong> {{ errorMessage }}
      </div>

      <div v-if="isValid && hasChanges" class="json-info-message">
        Unsaved changes
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import * as monaco from 'monaco-editor'
import editorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker'
import jsonWorker from 'monaco-editor/esm/vs/language/json/json.worker?worker'

// Configure Monaco environment
self.MonacoEnvironment = {
  getWorker(_, label) {
    if (label === 'json') {
      return new jsonWorker()
    }
    return new editorWorker()
  }
}

const props = defineProps({
  title: {
    type: String,
    required: true
  },
  modelValue: {
    type: [Array, Object],
    required: true
  }
})

const emit = defineEmits(['update:modelValue', 'save'])

const editorContainer = ref(null)
let editor = null

const jsonText = ref(JSON.stringify(props.modelValue, null, 2))
const originalJson = ref(JSON.stringify(props.modelValue, null, 2))
const isValid = ref(true)
const errorMessage = ref('')

const hasChanges = computed(() => {
  return jsonText.value !== originalJson.value
})

function validateJson() {
  try {
    JSON.parse(jsonText.value)
    isValid.value = true
    errorMessage.value = ''
    return true
  } catch (e) {
    isValid.value = false
    errorMessage.value = e.message
    return false
  }
}

function formatJson() {
  if (editor && validateJson()) {
    try {
      const parsed = JSON.parse(jsonText.value)
      const formatted = JSON.stringify(parsed, null, 2)
      editor.setValue(formatted)
      jsonText.value = formatted
    } catch (e) {
      // Already validated, should not happen
    }
  }
}

function saveJson() {
  if (!validateJson()) {
    return
  }

  try {
    const parsed = JSON.parse(jsonText.value)
    originalJson.value = jsonText.value
    emit('update:modelValue', parsed)
    emit('save', parsed)
  } catch (e) {
    errorMessage.value = e.message
    isValid.value = false
  }
}

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  const newJson = JSON.stringify(newValue, null, 2)
  if (newJson !== jsonText.value && editor) {
    jsonText.value = newJson
    originalJson.value = newJson
    editor.setValue(newJson)
    validateJson()
  }
}, { deep: true })

onMounted(() => {
  if (editorContainer.value) {
    editor = monaco.editor.create(editorContainer.value, {
      value: jsonText.value,
      language: 'json',
      theme: 'vs',
      automaticLayout: true,
      minimap: { enabled: false },
      fontSize: 14,
      lineNumbers: 'on',
      scrollBeyondLastLine: false,
      wordWrap: 'on',
      wrappingIndent: 'indent',
      formatOnPaste: true,
      formatOnType: true,
      tabSize: 2
    })

    // Update jsonText on editor changes
    editor.onDidChangeModelContent(() => {
      jsonText.value = editor.getValue()
      validateJson()
    })
  }
})

onBeforeUnmount(() => {
  if (editor) {
    editor.dispose()
  }
})
</script>

<style scoped>
.json-editor {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.json-editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #dee2e6;
  background: #f8f9fa;
}

.json-editor-header h3 {
  margin: 0;
  font-size: 18px;
  color: #2c3e50;
}

.json-editor-actions {
  display: flex;
  gap: 10px;
}

.json-editor-body {
  padding: 20px;
}

.monaco-container {
  width: 100%;
  height: 600px;
  border: 2px solid #dee2e6;
  border-radius: 4px;
  overflow: hidden;
}

.json-error-message {
  margin-top: 10px;
  padding: 12px 15px;
  background: #f8d7da;
  color: #721c24;
  border: 1px solid #f5c6cb;
  border-radius: 4px;
  font-size: 13px;
}

.json-info-message {
  margin-top: 10px;
  padding: 12px 15px;
  background: #d1ecf1;
  color: #0c5460;
  border: 1px solid #bee5eb;
  border-radius: 4px;
  font-size: 13px;
}
</style>
