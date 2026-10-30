import Keycloak from 'keycloak-js'
import { registerApplication, start } from 'single-spa'

/**
 * Shell de microfrontends (single-spa).
 *
 * - Hace UN solo login con Keycloak y presta esa sesion a cada microfrontend.
 * - Monta el portal React en /portal y la administracion Angular en /admin.
 * - Los microfrontends son modulos ES construidos aparte (`npm run build:spa` en cada uno);
 *   sus direcciones salen de variables VITE_* para poder cambiarlas por entorno.
 */
const env = import.meta.env
const PORTAL_URL: string = env.VITE_PORTAL_URL ?? 'http://localhost:5174/spa.js'
const ADMIN_URL: string = env.VITE_ADMIN_URL ?? 'http://localhost:4201/main.js'
const ADMIN_ESTILOS: string = env.VITE_ADMIN_ESTILOS ?? 'http://localhost:4201/styles.css'

const keycloak = new Keycloak({
  url: env.VITE_KEYCLOAK_URL ?? 'http://localhost:8080',
  realm: env.VITE_KEYCLOAK_REALM ?? 'alojate',
  clientId: env.VITE_KEYCLOAK_CLIENT ?? 'ms03-comercial-financiero',
})

interface Modulo {
  bootstrap: (props: unknown) => Promise<void>
  mount: (props: unknown) => Promise<void>
  unmount: (props: unknown) => Promise<void>
}

/** Carga una hoja de estilos mientras el microfrontend esta montado. */
function conEstilos(cargar: () => Promise<Modulo>, href: string): () => Promise<Modulo> {
  return async () => {
    const modulo = await cargar()
    let enlace: HTMLLinkElement | undefined
    return {
      bootstrap: modulo.bootstrap,
      mount: async (props) => {
        enlace = Object.assign(document.createElement('link'), { rel: 'stylesheet', href })
        document.head.append(enlace)
        await modulo.mount(props)
      },
      unmount: async (props) => {
        await modulo.unmount(props)
        enlace?.remove()
      },
    }
  }
}

async function arrancar(): Promise<void> {
  await keycloak.init({ onLoad: 'login-required', pkceMethod: 'S256', checkLoginIframe: false })

  const roles: string[] = keycloak.tokenParsed?.realm_access?.roles ?? []
  const sesion = {
    token: async () => {
      await keycloak.updateToken(30)
      return keycloak.token ?? ''
    },
    usuarioId: () => keycloak.tokenParsed?.sub ?? '',
    nombre: () => (keycloak.tokenParsed as { preferred_username?: string } | undefined)?.preferred_username ?? '',
    roles: () => roles,
    salir: () => void keycloak.logout({ redirectUri: window.location.origin }),
  }

  registerApplication({
    name: 'portal',
    app: () => import(/* @vite-ignore */ PORTAL_URL) as Promise<Modulo>,
    activeWhen: '/portal',
    customProps: { domElement: document.getElementById('app-portal'), sesion, basename: '/portal' },
  })

  registerApplication({
    name: 'admin',
    app: conEstilos(() => import(/* @vite-ignore */ ADMIN_URL) as Promise<Modulo>, ADMIN_ESTILOS),
    activeWhen: '/admin',
    customProps: { domElement: document.getElementById('app-admin'), sesion, basename: '/admin' },
  })

  // En la raiz se entra por el rol: ADMIN a la administracion, el resto al portal.
  const ruta = window.location.pathname
  if (!ruta.startsWith('/portal') && !ruta.startsWith('/admin')) {
    window.history.replaceState(null, '', roles.includes('ADMIN') ? '/admin' : '/portal')
  }

  document.getElementById('estado-shell')?.remove()
  start()
}

arrancar().catch((e: unknown) => {
  const estado = document.getElementById('estado-shell')
  if (estado) estado.textContent = `No se pudo iniciar: ${String(e)}`
})
