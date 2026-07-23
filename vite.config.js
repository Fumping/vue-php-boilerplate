import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/',
  test: {
    environment: 'jsdom',
    globals: true,
    env: {
      VITE_API_URL: '/api',
      VITE_API_KEY: 'test-key',
    },
  },
})
