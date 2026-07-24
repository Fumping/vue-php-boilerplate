import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { copyApiPlugin } from './scripts/copy-api.js'

export default defineConfig({
  plugins: [
    vue(),
    copyApiPlugin({
      src: 'api',
      dest: 'dist/api',
      exclude: [
        'config.php',
        'config.local.php',
        'storage/db.sqlite',
        'storage/db.sqlite-shm',
        'storage/db.sqlite-wal',
        'storage/db.sqlite-journal',
        'storage/tmp/**',
        '**/.env',
        '**/.env.local',
        '**/*.log',
      ],
    }),
  ],
  base: '/',
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    env: {
      VITE_API_URL: '/api',
      VITE_API_KEY: 'test-key',
    },
  },
})
