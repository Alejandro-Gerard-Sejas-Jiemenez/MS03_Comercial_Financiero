import Keycloak from 'keycloak-js'
import { KEYCLOAK } from './config'

export type Rol = 'ADMIN' | 'ANFITRION' | 'CLIENTE'

const keycloak = new Keycloak(KEYCLOAK)

/** Redirige al login de Keycloak si no hay sesion. Debe esperarse antes de pintar la app. */
export async function iniciarSesion(): Promise<void> {
  await keycloak.init({ onLoad: 'login-required', pkceMethod: 'S256', checkLoginIframe: false })
}

/** Token vigente; lo renueva si le quedan menos de 30 s. */
export async function token(): Promise<string> {
  await keycloak.updateToken(30)
  return keycloak.token ?? ''
}

export function usuarioId(): string {
  return keycloak.tokenParsed?.sub ?? ''
}

export function nombreUsuario(): string {
  return (keycloak.tokenParsed as { preferred_username?: string } | undefined)?.preferred_username ?? ''
}

export function roles(): Rol[] {
  const todos = keycloak.tokenParsed?.realm_access?.roles ?? []
  return todos.filter((r): r is Rol => r === 'ADMIN' || r === 'ANFITRION' || r === 'CLIENTE')
}

export function tieneRol(...buscados: Rol[]): boolean {
  const mios = roles()
  return buscados.some((r) => mios.includes(r))
}

export function cerrarSesion(): void {
  void keycloak.logout({ redirectUri: window.location.origin })
}
