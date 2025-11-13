<template>
  <div>
    <div class="border border-base-300 rounded-lg overflow-hidden">
      <div ref="editorContainer"></div>
    </div>

    <!-- Media Modal Integration -->
    <MediaModal
      ref="mediaModalRef"
      :media-items="mediaItems"
      :selected-media-id="null"
      @select="handleMediaSelect"
      @upload="handleMediaUpload"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'
import MediaModal from './MediaModal.vue'

const props = defineProps({
  modelValue: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue'])

const editorContainer = ref(null)
const mediaModalRef = ref(null)
const mediaItems = ref([])
let quillInstance = null

onMounted(() => {
  if (editorContainer.value) {
    // Initialize Quill editor
    quillInstance = new Quill(editorContainer.value, {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          [{ 'indent': '-1'}, { 'indent': '+1' }],
          [{ 'align': [] }],
          ['blockquote', 'code-block'],
          ['link', 'image'],
          ['clean']
        ]
      },
      placeholder: 'Start typing...'
    })

    // Custom image handler - open media modal instead of default behavior
    const toolbar = quillInstance.getModule('toolbar')
    toolbar.addHandler('image', () => {
      loadMedia()
      mediaModalRef.value?.open()
    })

    // Set initial content
    if (props.modelValue) {
      quillInstance.root.innerHTML = props.modelValue
    }

    // Listen for changes
    quillInstance.on('text-change', () => {
      const html = quillInstance.root.innerHTML
      // Emit empty string if only contains empty paragraph
      if (html === '<p><br></p>') {
        emit('update:modelValue', '')
      } else {
        emit('update:modelValue', html)
      }
    })
  }
})

// Load media from API
async function loadMedia() {
  try {
    const token = localStorage.getItem('edit_token')
    const response = await fetch('/_edit/api/media', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      }
    })

    if (!response.ok) {
      throw new Error('Failed to load media')
    }

    const result = await response.json()
    // API returns array directly, not wrapped in {data: ...}
    mediaItems.value = Array.isArray(result) ? result : []
  } catch (error) {
    console.error('Failed to load media:', error)
    mediaItems.value = []
  }
}

// Handle media selection from modal
function handleMediaSelect(mediaId) {
  const selectedMedia = mediaItems.value.find(item => item.id === mediaId)
  if (selectedMedia && quillInstance) {
    const range = quillInstance.getSelection(true)
    quillInstance.insertEmbed(range.index, 'image', selectedMedia.url)
    quillInstance.setSelection(range.index + 1)
  }
}

// Handle new media upload
async function handleMediaUpload(file) {
  try {
    const formData = new FormData()
    formData.append('file', file)

    const token = localStorage.getItem('edit_token')

    // Use direct backend URL for file uploads (like MediaView does)
    const response = await fetch('http://localhost:8001/_edit/api/media', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    })

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({ error: 'Upload failed' }))
      throw new Error(errorData.error || 'Upload failed')
    }

    // Reload media items
    await loadMedia()

    const result = await response.json()

    // Insert the uploaded image
    if (result && result.url && quillInstance) {
      const range = quillInstance.getSelection(true)
      quillInstance.insertEmbed(range.index, 'image', result.url)
      quillInstance.setSelection(range.index + 1)
    }
  } catch (error) {
    console.error('Failed to upload media:', error)
    alert('Failed to upload image: ' + error.message)
  }
}

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (quillInstance && quillInstance.root.innerHTML !== newValue) {
    quillInstance.root.innerHTML = newValue || ''
  }
})

onBeforeUnmount(() => {
  if (quillInstance) {
    quillInstance = null
  }
})
</script>

<style scoped>
:deep(.ql-editor) {
  min-height: 200px;
}
</style>
