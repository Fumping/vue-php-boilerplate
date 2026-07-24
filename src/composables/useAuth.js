import { readonly, ref } from 'vue'
import { apiGet, apiPost, setCsrfToken } from '../lib/api.js'

const user = ref(null)
const initialized = ref(false)
let loadingPromise = null

function applyAuthResponse(response) {
  user.value = response?.user ?? null
  setCsrfToken(response?.csrfToken ?? '')
  initialized.value = true
  return user.value
}

async function loadUser(force = false) {
  if (initialized.value && !force) return user.value
  if (loadingPromise) return loadingPromise

  loadingPromise = apiGet('/auth/me')
    .then(applyAuthResponse)
    .catch(() => {
      user.value = null
      setCsrfToken('')
      initialized.value = true
      return null
    })
    .finally(() => {
      loadingPromise = null
    })

  return loadingPromise
}

async function login(username, password) {
  const response = await apiPost('/auth/login', { username, password })
  return applyAuthResponse(response)
}

async function logout() {
  await apiPost('/auth/logout')
  user.value = null
  setCsrfToken('')
  initialized.value = true
}

async function changePassword(currentPassword, newPassword) {
  const response = await apiPost('/auth/change-password', {
    currentPassword,
    newPassword,
  })
  return applyAuthResponse(response)
}

export function useAuth() {
  return {
    user: readonly(user),
    initialized: readonly(initialized),
    loadUser,
    login,
    logout,
    changePassword,
  }
}
