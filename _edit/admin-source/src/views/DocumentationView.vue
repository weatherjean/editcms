<template>
  <div class="max-w-4xl mx-auto">
    <div class="card bg-base-100 shadow-xl">
      <div class="card-body">
        <h2 class="card-title text-2xl mb-4">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
          </svg>
          API Documentation
        </h2>

        <!-- Loading -->
        <div v-if="loading" class="flex justify-center py-8">
          <span class="loading loading-spinner loading-lg"></span>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="alert alert-error">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{{ error }}</span>
        </div>

        <!-- Documentation Content -->
        <div v-else class="prose prose-sm max-w-none" v-html="renderedMarkdown" @click="handleLinkClick"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { marked } from 'marked'
import hljs from 'highlight.js/lib/core'
import javascript from 'highlight.js/lib/languages/javascript'
import json from 'highlight.js/lib/languages/json'
import php from 'highlight.js/lib/languages/php'
import bash from 'highlight.js/lib/languages/bash'
import sql from 'highlight.js/lib/languages/sql'
import 'highlight.js/styles/github-dark.css'

// Register languages with aliases
hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('js', javascript)
hljs.registerLanguage('json', json)
hljs.registerLanguage('php', php)
hljs.registerLanguage('bash', bash)
hljs.registerLanguage('shell', bash)
hljs.registerLanguage('sh', bash)
hljs.registerLanguage('sql', sql)

const loading = ref(true)
const error = ref(null)
const renderedMarkdown = ref('')

function slugify(text) {
  return text
    .toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '')
}
marked.use({
  gfm: true,
  breaks: true,
  renderer: {
    code(token) {
      const code = token.text
      const lang = token.lang

      if (lang && hljs.getLanguage(lang)) {
        try {
          const highlighted = hljs.highlight(code, { language: lang }).value
          return `<pre><code class="hljs language-${lang}">${highlighted}</code></pre>`
        } catch (err) {
          console.error('Highlight error:', err)
        }
      }

      const escaped = code
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
      return `<pre><code class="hljs">${escaped}</code></pre>`
    },
    heading(token) {
      const text = token.text
      const level = token.depth
      const slug = slugify(text)
      return `<h${level} id="${slug}">${text}</h${level}>`
    }
  }
})

async function loadDocumentation() {
  loading.value = true
  error.value = null

  try {
    const response = await fetch('/_edit/api/public/docs')

    if (!response.ok) {
      throw new Error(`Failed to load documentation: ${response.statusText}`)
    }

    const markdown = await response.text()
    renderedMarkdown.value = marked(markdown)
  } catch (err) {
    error.value = err.message || 'Failed to load documentation'
    console.error('Documentation load error:', err)
  } finally {
    loading.value = false
  }
}

function handleLinkClick(event) {
  const target = event.target

  if (target.tagName === 'A' && target.hash) {
    const hash = target.hash

    if (hash.startsWith('#')) {
      event.preventDefault()

      const targetId = hash.substring(1)
      const targetElement = document.getElementById(targetId)

      if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' })
        window.history.replaceState(null, '', `#/docs${hash}`)
      }
    }
  }
}

onMounted(() => {
  loadDocumentation()
})
</script>

<style>
/* Enhanced prose styles for documentation */
.prose {
  color: oklch(var(--bc));
}

.prose h1 {
  font-size: 1.875rem;
  font-weight: 700;
  margin-top: 2rem;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid oklch(var(--b3));
}

.prose h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.prose h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin-top: 1rem;
  margin-bottom: 0.5rem;
}

.prose p {
  margin-bottom: 1rem;
  line-height: 1.625;
}

/* Inline code (URLs, inline references) */
.prose code {
  background-color: oklch(var(--b2));
  padding: 0.125rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #e74c3c;
  font-weight: 500;
  border: 1px solid oklch(var(--b3));
}

/* Code blocks with syntax highlighting */
.prose pre {
  padding: 0;
  border-radius: 0.5rem;
  overflow-x: auto;
  margin-bottom: 1rem;
  line-height: 1.5;
  background-color: #0d1117;
  border: 1px solid #30363d;
}

.prose pre code {
  display: block;
  padding: 1rem;
  background-color: #0d1117;
  font-size: 0.875rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #e6edf3;
  border: none;
  font-weight: normal;
}

.prose ul, .prose ol {
  margin-bottom: 1rem;
  margin-left: 1.5rem;
}

.prose li {
  margin-bottom: 0.5rem;
}

.prose a {
  color: oklch(var(--p));
  text-decoration: underline;
}

.prose a:hover {
  text-decoration: none;
}

.prose blockquote {
  border-left: 4px solid oklch(var(--p));
  padding-left: 1rem;
  font-style: italic;
  margin: 1rem 0;
}

.prose table {
  width: 100%;
  margin-bottom: 1rem;
  border-collapse: collapse;
}

.prose th {
  background-color: oklch(var(--b2));
  padding: 0.5rem;
  text-align: left;
  font-weight: 600;
}

.prose td {
  padding: 0.5rem;
  border-top: 1px solid oklch(var(--b3));
}

.prose tr:nth-child(even) {
  background-color: oklch(var(--b2) / 0.5);
}

.prose strong {
  font-weight: 600;
}
</style>
