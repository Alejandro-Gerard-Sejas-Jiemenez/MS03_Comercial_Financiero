import { createRoot, type Root } from 'react-dom/client'
import App from '@/App'
import { usarSesionExterna, type SesionExterna } from '@/api/auth'
import estilos from './index.css?inline'

/**
 * Punto de entrada para el shell single-spa: exporta el ciclo de vida
 * (bootstrap / mount / unmount) en vez de arrancar solo. Se construye con
 * `npm run build:spa` y sale como un unico `spa.js`.
 */
interface Props {
  domElement: HTMLElement
  sesion: SesionExterna
  basename?: string
}

let raiz: Root | undefined
let estilo: HTMLStyleElement | undefined

export async function bootstrap(): Promise<void> {}

export async function mount(props: Props): Promise<void> {
  usarSesionExterna(props.sesion)
  // Los estilos viajan dentro del bundle y se quitan al desmontar.
  estilo = document.createElement('style')
  estilo.textContent = estilos
  document.head.append(estilo)

  raiz = createRoot(props.domElement)
  raiz.render(<App basename={props.basename ?? '/portal'} />)
}

export async function unmount(): Promise<void> {
  raiz?.unmount()
  raiz = undefined
  estilo?.remove()
  estilo = undefined
}
