import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { copyApiPlugin } from './scripts/copy-api.js'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    copyApiPlugin({
      src: 'api',
      dest: 'dist/api',
      include: [
        '.htaccess',
        'index.php',
        'config.example.php',
        'cli/**/*.php',
        'handlers/**/*.php',
        'helpers/**/*.php',
        'storage/.htaccess',
      ],
      exclude: [
        'config.php',
        'config.local.php',
        'storage/**/*.sqlite*',
        'storage/tmp/**',
        '**/.env*',
        '**/*.log',
        '**/*.bak',
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
    },
  },
})
