import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from '@/App'
import { iniciarSesion } from '@/api/auth'

// Modo independiente: el portal pide su propio login. Dentro del shell single-spa
// se usa `spa-entry.tsx`, que comparte la sesion del shell.
iniciarSesion()
  .then(() => {
    createRoot(document.getElementById('root')!).render(
      <StrictMode>
        <App />
      </StrictMode>,
    )
  })
  .catch((e: unknown) => {
    document.getElementById('root')!.textContent = `No se pudo iniciar sesion: ${String(e)}`
  })
