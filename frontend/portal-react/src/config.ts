/** Direcciones del entorno. Se cambian con variables VITE_* al construir (ver .env.example). */
export const API_URL: string = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1'

export const KEYCLOAK = {
  url: import.meta.env.VITE_KEYCLOAK_URL ?? 'http://localhost:8080',
  realm: import.meta.env.VITE_KEYCLOAK_REALM ?? 'alojate',
  clientId: import.meta.env.VITE_KEYCLOAK_CLIENT ?? 'ms03-comercial-financiero',
}
