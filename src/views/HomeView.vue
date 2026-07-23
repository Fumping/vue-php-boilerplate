<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'

const appName = 'Boilerplate'
const health = ref(null)
const error = ref(null)

const { apiGet } = useApi()

onMounted(async () => {
  try {
    health.value = await apiGet('/')
  } catch (e) {
    error.value = e.message
  }
})
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <div class="max-w-lg text-center space-y-4">
      <h1 class="text-4xl font-bold text-slate-800">{{ appName }}</h1>
      <p class="text-slate-600">Welkom bij je nieuwe app.</p>

      <div v-if="health" class="text-sm text-slate-700 bg-white rounded p-4 shadow-sm">
        <div>API status: <strong>{{ health.status }}</strong></div>
        <div>DB driver: <strong>{{ health.driver }}</strong></div>
      </div>

      <div v-else-if="error" class="text-sm text-red-600">
        API-fout: {{ error }}
      </div>

      <p class="text-sm text-slate-500">
        Zie <code class="bg-slate-200 px-1 rounded">README.md</code> voor volgende stappen.
      </p>
    </div>
  </main>
</template>
