import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { apiGet, apiPost, setCsrfToken } from '../../src/lib/api.js'

describe('lib/api', () => {
  beforeEach(() => {
    global.fetch = vi.fn()
    setCsrfToken('')
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('apiGet uses same-origin credentials without exposing a shared API key', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ hello: 'world' }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    const result = await apiGet('/hello')
    expect(result).toEqual({ hello: 'world' })

    const [url, init] = global.fetch.mock.calls[0]
    expect(url).toBe('/api/hello')
    expect(init.headers['X-Api-Key']).toBeUndefined()
    expect(init.credentials).toBe('same-origin')
    expect(init.method).toBe('GET')
  })

  it('apiPost stringifies body and sets JSON content-type', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    await apiPost('/items', { name: 'x' })

    const [, init] = global.fetch.mock.calls[0]
    expect(init.method).toBe('POST')
    expect(init.body).toBe(JSON.stringify({ name: 'x' }))
    expect(init.headers['Content-Type']).toBe('application/json')
  })

  it('adds the CSRF token to state-changing requests only', async () => {
    global.fetch.mockResolvedValue(
      new Response(JSON.stringify({ ok: true }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    setCsrfToken('csrf-test-token')

    await apiGet('/items')
    await apiPost('/items', { name: 'x' })

    expect(global.fetch.mock.calls[0][1].headers['X-CSRF-Token']).toBeUndefined()
    expect(global.fetch.mock.calls[1][1].headers['X-CSRF-Token']).toBe('csrf-test-token')
  })

  it('throws an Error with status and body on non-2xx', async () => {
    global.fetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'bad' }), {
        status: 400,
        headers: { 'Content-Type': 'application/json' },
      })
    )
    await expect(apiGet('/x')).rejects.toMatchObject({
      status: 400,
      body: { error: 'bad' },
      message: 'bad',
    })
  })

  it('returns null on 204', async () => {
    global.fetch.mockResolvedValueOnce(new Response(null, { status: 204 }))
    const result = await apiGet('/nothing')
    expect(result).toBeNull()
  })
})
