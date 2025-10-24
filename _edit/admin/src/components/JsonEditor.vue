<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 opacity-60" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
        </svg>
        <span class="font-mono text-sm font-semibold">{{ title }}</span>
      </div>
      <div class="join">
        <button type="button" @click="formatJson" class="btn btn-sm btn-neutral join-item gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
          </svg>
          Format
        </button>
        <button
          type="button"
          @click="saveJson"
          class="btn btn-sm btn-success join-item gap-2"
          :disabled="!isValid || !hasChanges"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path d="M7.707 10.293a1 1 0 10-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 11.586V6h5a2 2 0 012 2v7a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2h5v5.586l-1.293-1.293zM9 4a1 1 0 012 0v2H9V4z" />
          </svg>
          Save
        </button>
      </div>
    </div>

    <div ref="editorContainer" class="w-full h-[600px] border border-base-300 rounded-lg overflow-hidden"></div>

    <div v-if="!isValid" class="alert alert-error">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <div>
        <div class="font-bold">Invalid JSON</div>
        <div class="text-sm opacity-60">{{ errorMessage }}</div>
      </div>
    </div>

    <div v-if="isValid && hasChanges" class="alert alert-warning">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
      </svg>
      <span>You have unsaved changes</span>
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
