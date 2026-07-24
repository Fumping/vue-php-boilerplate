<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router = useRouter()
const { user, logout } = useAuth()
const error = ref('')

async function signOut() {
  try {
    await logout()
    await router.replace('/login')
  } catch {
    error.value = 'Uitloggen is niet gelukt. Vernieuw de pagina en probeer opnieuw.'
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-8">
    <div class="mx-auto max-w-3xl space-y-6">
      <header class="flex items-center justify-between rounded-lg bg-white p-6 shadow-sm">
        <div>
          <h1 class="text-3xl font-bold text-slate-800">Boilerplate</h1>
          <p class="mt-1 text-slate-600">
            Ingelogd als <strong>{{ user.username }}</strong>
          </p>
        </div>
        <button class="text-sm text-slate-700 underline" @click="signOut">Uitloggen</button>
      </header>

      <section v-if="user.role === 'admin'" class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Beheer</h2>
        <p class="mt-1 text-sm text-slate-500">Voeg gebruikers toe en beheer hun rollen.</p>
        <RouterLink
          to="/admin/users"
          class="mt-4 inline-block rounded bg-slate-900 px-4 py-2 text-white"
        >
          Gebruikers beheren
        </RouterLink>
      </section>

      <p v-if="error" role="alert" class="text-sm text-red-600">{{ error }}</p>
    </div>
  </main>
</template>
