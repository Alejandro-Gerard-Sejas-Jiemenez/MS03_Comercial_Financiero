import Keycloak from 'keycloak-js'
import { KEYCLOAK } from '@/config'

export type Rol = 'ADMIN' | 'ANFITRION' | 'CLIENTE'

/**
 * Sesion que presta el shell single-spa. Dentro del shell hay UN solo login de Keycloak y
 * todos los microfrontends lo comparten; fuera del shell el portal inicia el suyo.
 */
export interface SesionExterna {
  token(): Promise<string>
  usuarioId(): string
  nombre(): string
  roles(): string[]
  salir(): void
}

let externa: SesionExterna | undefined
export function usarSesionExterna(sesion: SesionExterna): void {
  externa = sesion
}

const keycloak = new Keycloak(KEYCLOAK)

/** Modo independiente: redirige al login de Keycloak si no hay sesion. */
export async function iniciarSesion(): Promise<void> {
  await keycloak.init({ onLoad: 'login-required', pkceMethod: 'S256', checkLoginIframe: false })
}

/** Token vigente; lo renueva si le quedan menos de 30 s. */
export async function token(): Promise<string> {
  if (externa) return externa.token()
  await keycloak.updateToken(30)
  return keycloak.token ?? ''
}

export function usuarioId(): string {
  return externa ? externa.usuarioId() : (keycloak.tokenParsed?.sub ?? '')
}

export function nombreUsuario(): string {
  if (externa) return externa.nombre()
  return (keycloak.tokenParsed as { preferred_username?: string } | undefined)?.preferred_username ?? ''
}

export function roles(): Rol[] {
  const todos = externa ? externa.roles() : (keycloak.tokenParsed?.realm_access?.roles ?? [])
  return todos.filter((r): r is Rol => r === 'ADMIN' || r === 'ANFITRION' || r === 'CLIENTE')
}

export function tieneRol(...buscados: Rol[]): boolean {
  const mios = roles()
  return buscados.some((r) => mios.includes(r))
}

export function cerrarSesion(): void {
  if (externa) return externa.salir()
  void keycloak.logout({ redirectUri: window.location.origin })
}
