<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router = useRouter()
const { changePassword } = useAuth()

const currentPassword = ref('')
const newPassword = ref('')
const confirmation = ref('')
const error = ref('')
const submitting = ref(false)

async function submit() {
  error.value = ''
  if (newPassword.value !== confirmation.value) {
    error.value = 'De nieuwe wachtwoorden zijn niet gelijk.'
    return
  }
  if (newPassword.value.length < 12) {
    error.value = 'Gebruik minimaal 12 tekens.'
    return
  }

  submitting.value = true
  try {
    await changePassword(currentPassword.value, newPassword.value)
    await router.replace('/')
  } catch (e) {
    error.value = e.message === 'invalid_current_password'
      ? 'Het huidige wachtwoord klopt niet.'
      : 'Het wachtwoord kon niet worden gewijzigd.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 flex items-center justify-center p-8">
    <form
      class="w-full max-w-md space-y-5 bg-white rounded-lg shadow p-8"
      @submit.prevent="submit"
    >
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Nieuw wachtwoord instellen</h1>
        <p class="mt-1 text-sm text-slate-500">
          Je tijdelijke wachtwoord moet vóór gebruik van de applicatie worden vervangen.
        </p>
      </div>

      <label class="block space-y-1">
        <span class="text-sm font-medium text-slate-700">Huidig wachtwoord</span>
        <input
          v-model="currentPassword"
          type="password"
          autocomplete="current-password"
          required
          class="w-full rounded border border-slate-300 px-3 py-2"
        />
      </label>

      <label class="block space-y-1">
        <span class="text-sm font-medium text-slate-700">Nieuw wachtwoord</span>
        <input
          v-model="newPassword"
          type="password"
          autocomplete="new-password"
          minlength="12"
          maxlength="200"
          required
          class="w-full rounded border border-slate-300 px-3 py-2"
        />
      </label>

      <label class="block space-y-1">
        <span class="text-sm font-medium text-slate-700">Herhaal nieuw wachtwoord</span>
        <input
          v-model="confirmation"
          type="password"
          autocomplete="new-password"
          minlength="12"
          maxlength="200"
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
        Wachtwoord opslaan
      </button>
    </form>
  </main>
</template>
