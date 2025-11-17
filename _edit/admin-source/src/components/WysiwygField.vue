<template>
  <div>
    <!-- Mode Toggle -->
    <div class="flex justify-end mb-2">
      <div class="btn-group">
        <button
          type="button"
          :class="['btn btn-sm', !isHtmlMode ? 'btn-active' : '']"
          @click="setMode(false)"
        >
          Visual
        </button>
        <button
          type="button"
          :class="['btn btn-sm', isHtmlMode ? 'btn-active' : '']"
          @click="setMode(true)"
        >
          HTML
        </button>
      </div>
    </div>

    <!-- Visual Editor -->
    <div v-show="!isHtmlMode" class="border border-base-300 rounded-lg overflow-hidden">
      <div ref="editorContainer"></div>
    </div>

    <!-- HTML Source Editor -->
    <div v-show="isHtmlMode">
      <textarea
        :value="htmlSource"
        @input="onHtmlChange"
        class="textarea textarea-bordered w-full font-mono text-sm"
        rows="15"
        placeholder="Enter HTML..."
      ></textarea>
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
import { useMedia } from '../composables/useMedia'
import { useToast } from '../composables/useToast'

const { fetchMedia, uploadFile } = useMedia()
const { error: showError } = useToast()

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
const isHtmlMode = ref(false)
const htmlSource = ref('')
let quillInstance = null

onMounted(() => {
  if (editorContainer.value) {
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

    const toolbar = quillInstance.getModule('toolbar')
    toolbar.addHandler('image', () => {
      loadMedia()
      mediaModalRef.value?.open()
    })

    if (props.modelValue) {
      quillInstance.root.innerHTML = props.modelValue
      htmlSource.value = props.modelValue
    }

    quillInstance.on('text-change', () => {
      const html = quillInstance.root.innerHTML
      if (html === '<p><br></p>') {
        emit('update:modelValue', '')
        htmlSource.value = ''
      } else {
        emit('update:modelValue', html)
        htmlSource.value = html
      }
    })
  }
})

function setMode(htmlMode) {
  if (htmlMode && quillInstance) {
    // Switching to HTML mode - sync from visual editor
    htmlSource.value = quillInstance.root.innerHTML
  } else if (!htmlMode && quillInstance) {
    // Switching to visual mode - sync from HTML editor
    quillInstance.root.innerHTML = htmlSource.value
  }
  isHtmlMode.value = htmlMode
}

function onHtmlChange(event) {
  htmlSource.value = event.target.value
  emit('update:modelValue', event.target.value)
}

async function loadMedia() {
  try {
    const result = await fetchMedia()
    mediaItems.value = Array.isArray(result) ? result : []
  } catch (err) {
    console.error('Failed to load media:', err)
    mediaItems.value = []
  }
}
function handleMediaSelect(mediaId) {
  const selectedMedia = mediaItems.value.find(item => item.id === mediaId)
  if (selectedMedia && quillInstance) {
    const range = quillInstance.getSelection(true)
    quillInstance.insertEmbed(range.index, 'image', selectedMedia.url)
    quillInstance.setSelection(range.index + 1)
  }
}

async function handleMediaUpload(file) {
  try {
    const result = await uploadFile(file)

    await loadMedia()

    if (result && result.url && quillInstance) {
      const range = quillInstance.getSelection(true)
      quillInstance.insertEmbed(range.index, 'image', result.url)
      quillInstance.setSelection(range.index + 1)
    }
  } catch (err) {
    console.error('Failed to upload media:', err)
    showError('Failed to upload image: ' + err.message)
  }
}
watch(() => props.modelValue, (newValue) => {
  if (quillInstance && !isHtmlMode.value && quillInstance.root.innerHTML !== newValue) {
    quillInstance.root.innerHTML = newValue || ''
    htmlSource.value = newValue || ''
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
