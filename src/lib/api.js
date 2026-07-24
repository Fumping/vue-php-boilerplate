const API_URL = (import.meta.env.VITE_API_URL ?? '/api').replace(/\/$/, '')

let csrfToken = ''

export function setCsrfToken(token) {
  csrfToken = typeof token === 'string' ? token : ''
}

function headers(init) {
  const result = { ...(init.headers ?? {}) }
  if (init.body !== undefined) {
    result['Content-Type'] = 'application/json'
  }
  if (csrfToken && !['GET', 'HEAD'].includes(init.method ?? 'GET')) {
    result['X-CSRF-Token'] = csrfToken
  }
  return result
}

async function request(path, init = {}) {
  const url = `${API_URL}${path}`
  const res = await fetch(url, {
    credentials: 'same-origin',
    ...init,
    headers: headers(init),
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

export function apiPost(path, body = {}) {
  return request(path, { method: 'POST', body: JSON.stringify(body) })
}

export function apiPatch(path, body) {
  return request(path, { method: 'PATCH', body: JSON.stringify(body) })
}

export function apiDelete(path) {
  return request(path, { method: 'DELETE' })
}
