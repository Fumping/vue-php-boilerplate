<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { apiGet, apiPost } from '../lib/api.js'

const users = ref([])
const username = ref('')
const password = ref('')
const role = ref('user')
const error = ref('')
const success = ref('')
const loading = ref(true)
const submitting = ref(false)

async function loadUsers() {
  users.value = await apiGet('/admin/users')
}

onMounted(async () => {
  try {
    await loadUsers()
  } catch {
    error.value = 'Users could not be loaded.'
  } finally {
    loading.value = false
  }
})

async function createUser() {
  error.value = ''
  success.value = ''
  submitting.value = true
  try {
    const created = await apiPost('/admin/users', {
      username: username.value,
      password: password.value,
      role: role.value,
    })
    users.value.push(created)
    success.value = `User ${created.username} has been added.`
    username.value = ''
    password.value = ''
    role.value = 'user'
  } catch (e) {
    const messages = {
      invalid_username: 'Use 3 to 50 lowercase letters, digits, dots, hyphens or underscores.',
      password_too_weak: 'The temporary password must be at least 12 characters.',
      username_taken: 'This username already exists.',
    }
    error.value = messages[e.message] ?? 'The user could not be added.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="min-h-screen bg-slate-50 p-8">
    <div class="mx-auto max-w-5xl space-y-8">
      <header class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-slate-800">Users</h1>
          <p class="text-sm text-slate-500">New users change their password on first login.</p>
        </div>
        <RouterLink to="/" class="text-sm text-slate-700 underline">Back</RouterLink>
      </header>

      <section class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-slate-800">Add user</h2>
        <form class="mt-4 grid gap-4 md:grid-cols-4" @submit.prevent="createUser">
          <input
            v-model="username"
            autocomplete="off"
            placeholder="username"
            minlength="3"
            maxlength="50"
            required
            class="rounded border border-slate-300 px-3 py-2"
          />
          <input
            v-model="password"
            type="password"
            autocomplete="new-password"
            placeholder="temporary password"
            minlength="12"
            maxlength="72"
            required
            class="rounded border border-slate-300 px-3 py-2"
          />
          <select v-model="role" class="rounded border border-slate-300 px-3 py-2">
            <option value="user">User</option>
            <option value="admin">Admin</option>
          </select>
          <button
            type="submit"
            :disabled="submitting"
            class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50"
          >
            Add
          </button>
        </form>
        <p v-if="error" role="alert" class="mt-3 text-sm text-red-600">{{ error }}</p>
        <p v-if="success" role="status" class="mt-3 text-sm text-green-700">{{ success }}</p>
      </section>

      <section class="overflow-hidden rounded-lg bg-white shadow-sm">
        <p v-if="loading" class="p-6 text-slate-500">Loading…</p>
        <table v-else class="w-full text-left text-sm">
          <thead class="bg-slate-100 text-slate-600">
            <tr>
              <th class="px-6 py-3">Username</th>
              <th class="px-6 py-3">Role</th>
              <th class="px-6 py-3">Status</th>
              <th class="px-6 py-3">Created</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="item in users" :key="item.id">
              <td class="px-6 py-4 font-medium text-slate-800">{{ item.username }}</td>
              <td class="px-6 py-4">{{ item.role }}</td>
              <td class="px-6 py-4">
                {{ item.mustChangePassword ? 'Must change password' : 'Active' }}
              </td>
              <td class="px-6 py-4">{{ item.createdAt }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </main>
</template>
