const API_URL = (import.meta.env.VITE_API_URL ?? '/api').replace(/\/$/, '')
const API_KEY = import.meta.env.VITE_API_KEY ?? ''

function headers(extra = {}) {
  return {
    'Content-Type': 'application/json',
    'X-Api-Key': API_KEY,
    ...extra,
  }
}

async function request(path, init = {}) {
  const url = `${API_URL}${path}`
  const res = await fetch(url, {
    ...init,
    headers: { ...headers(), ...(init.headers ?? {}) },
  })

  if (res.status === 204) return null

  let body = null
  const contentType = res.headers.get('content-type') ?? ''
  if (contentType.includes('application/json')) {
    body = await res.json().catch(() => null)
  }

  if (!res.ok) {
    const err = new Error(body?.error || `HTTP ${res.status}`)
    err.status = res.status
    err.body = body
    throw err
  }

  return body
}

export function apiGet(path) {
  return request(path, { method: 'GET' })
}

export function apiPost(path, body) {
  return request(path, { method: 'POST', body: JSON.stringify(body) })
}

export function apiPatch(path, body) {
  return request(path, { method: 'PATCH', body: JSON.stringify(body) })
}

export function apiDelete(path) {
  return request(path, { method: 'DELETE' })
}
