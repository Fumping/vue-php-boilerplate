<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const route = useRoute()
const router = useRouter()
const { login } = useAuth()

const username = ref('')
const password = ref('')
const error = ref('')
const submitting = ref(false)

function safeRedirect(value) {
  return typeof value === 'string' && value.startsWith('/') && !value.startsWith('//')
    ? value
    : '/'
}

async function submit() {
  error.value = ''
  submitting.value = true
  try {
    const user = await login(username.value, password.value)
    await router.replace(
      user.mustChangePassword ? '/change-password' : safeRedirect(route.query.redirect)
    )
  } catch (e) {
    error.value = e.status === 429
      ? 'Too many failed attempts. Try again in a few minutes.'
      : 'Invalid username or password.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <form
      class="w-full max-w-sm space-y-5 bg-white rounded-lg shadow p-8"
      @submit.prevent="submit"
    >
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Log in</h1>
        <p class="mt-1 text-sm text-slate-500">Use your username and password.</p>
      </div>

      <label class="block space-y-1">
        <span class="text-sm font-medium text-slate-700">Username</span>
        <input
          v-model="username"
          name="username"
          autocomplete="username"
          required
          class="w-full rounded border border-slate-300 px-3 py-2"
        />
      </label>

      <label class="block space-y-1">
        <span class="text-sm font-medium text-slate-700">Password</span>
        <input
          v-model="password"
          type="password"
          name="password"
          autocomplete="current-password"
          required
          class="w-full rounded border border-slate-300 px-3 py-2"
        />
      </label>

      <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>

      <button
        type="submit"
        :disabled="submitting"
        class="w-full rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
      >
        {{ submitting ? 'Working…' : 'Log in' }}
      </button>
    </form>
  </main>
</template>
