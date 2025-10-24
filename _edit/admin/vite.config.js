import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue()],
  base: '/_edit/admin/',
  build: {
    outDir: '../admin-dist',
    emptyOutDir: true
  },
  server: {
    port: 5173,
    proxy: {
      '/_edit/api': {
        target: 'http://localhost:8001',
        changeOrigin: true
      }
    }
  },
  optimizeDeps: {
    include: ['monaco-editor']
  }
})
