import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    tailwindcss()
  ],
  base: '/_edit/admin/',
  build: {
    outDir: '../admin/dist',
    emptyOutDir: true
  },
  server: {
    port: 5173,
    proxy: {
      '/_edit/api': {
        target: 'http://localhost:8000',
        changeOrigin: true
      },
      '/_edit/uploads': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  },
  optimizeDeps: {
    include: ['monaco-editor']
  }
})
