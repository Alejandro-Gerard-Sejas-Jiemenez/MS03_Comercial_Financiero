import { useEffect, useState, type ReactNode } from 'react'
import { Menu, X } from 'lucide-react'
import { Marca, Sidebar } from '@/components/layout/Sidebar'
import { cn } from '@/utils/cn'

/**
 * Escritorio (md y mas): menu lateral fijo a la izquierda y contenido a la derecha.
 * Movil y tablet pequena: barra superior con el boton del menu; el menu se abre como panel lateral.
 */
export function AppLayout({ children }: { children: ReactNode }) {
  const [abierto, setAbierto] = useState(false)

  // Pulsar Escape cierra el panel (al elegir una opcion lo cierra el propio menu).
  useEffect(() => {
    if (!abierto) return
    const alTeclear = (e: KeyboardEvent) => e.key === 'Escape' && setAbierto(false)
    window.addEventListener('keydown', alTeclear)
    return () => window.removeEventListener('keydown', alTeclear)
  }, [abierto])

  return (
    <div className="min-h-screen bg-surface-secondary md:flex">
      <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface-base px-4 md:hidden">
        <Marca />
        <button className="btn btn-ghost !p-2" aria-label="Abrir menú" aria-expanded={abierto} onClick={() => setAbierto(true)}>
          <Menu className="h-5 w-5" />
        </button>
      </header>

      {abierto && <div className="fixed inset-0 z-40 bg-ink-primary/40 md:hidden" onClick={() => setAbierto(false)} aria-hidden="true" />}

      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-line bg-surface-base transition-transform duration-200',
          'md:sticky md:top-0 md:z-auto md:h-screen md:w-64 md:max-w-none md:shrink-0 md:translate-x-0',
          abierto ? 'translate-x-0 shadow-cardHover' : '-translate-x-full',
        )}
      >
        <button className="btn btn-ghost absolute right-2 top-2 !p-2 md:hidden" aria-label="Cerrar menú" onClick={() => setAbierto(false)}>
          <X className="h-5 w-5" />
        </button>
        <Sidebar alNavegar={() => setAbierto(false)} />
      </aside>

      <main className="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
        <div className="mx-auto max-w-6xl">{children}</div>
      </main>
    </div>
  )
}

export default AppLayout
