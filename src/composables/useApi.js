import { apiGet, apiPost, apiPatch, apiDelete } from '../lib/api.js'

export function useApi() {
  return { apiGet, apiPost, apiPatch, apiDelete }
}
