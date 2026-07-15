import axios from 'axios'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
})

/**
 * Sanctum SPA auth requires a CSRF cookie to be set before any
 * state-changing request (e.g. login). Must be called against the
 * app root, not the /api prefix.
 */
export function fetchCsrfCookie() {
  return axios.get(`${import.meta.env.VITE_APP_URL}/sanctum/csrf-cookie`, {
    withCredentials: true,
  })
}
