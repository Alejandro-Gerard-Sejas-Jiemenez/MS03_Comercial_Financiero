import Keycloak from 'keycloak-js';

/** Direcciones de Keycloak. Para otro entorno se reemplaza este archivo al construir. */
export const KEYCLOAK = { url: 'http://localhost:8080', realm: 'alojate', clientId: 'ms03-comercial-financiero' };

/**
 * Sesion que presta el shell single-spa: dentro del shell hay UN solo login de Keycloak que
 * comparten todos los microfrontends; fuera del shell esta app inicia el suyo.
 */
export interface SesionExterna {
  token(): Promise<string>;
  nombre(): string;
  roles(): string[];
  salir(): void;
}

let externa: SesionExterna | undefined;
export function usarSesionExterna(sesion: SesionExterna): void {
  externa = sesion;
}

const keycloak = new Keycloak(KEYCLOAK);

export async function iniciarSesion(): Promise<void> {
  await keycloak.init({ onLoad: 'login-required', pkceMethod: 'S256', checkLoginIframe: false });
}

export const sesion = {
  nombre: () => externa?.nombre() ?? (keycloak.tokenParsed as { preferred_username?: string } | undefined)?.preferred_username ?? '',
  esAdmin: () => (externa ? externa.roles() : (keycloak.tokenParsed?.realm_access?.roles ?? [])).includes('ADMIN'),
  salir: () => (externa ? externa.salir() : void keycloak.logout({ redirectUri: window.location.origin })),
};

export async function token(): Promise<string> {
  if (externa) return externa.token();
  await keycloak.updateToken(30);
  return keycloak.token ?? '';
}
