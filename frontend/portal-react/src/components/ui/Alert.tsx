import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

export function Alert({ tipo, children }: { tipo: 'error' | 'ok'; children: ReactNode }) {
  return (
    <p
      role={tipo === 'error' ? 'alert' : 'status'}
      className={cn(
        'my-4 rounded-lg border px-4 py-3 text-sm',
        tipo === 'error' ? 'border-red-200 bg-red-50 text-danger' : 'border-green-200 bg-green-50 text-positive',
      )}
    >
      {children}
    </p>
  )
}

/** Resultado de una accion (`useAccion`): muestra el error o el exito si los hay. */
export function ResultadoAccion({ error, exito }: { error?: string; exito?: string }) {
  return (
    <>
      {error && <Alert tipo="error">{error}</Alert>}
      {exito && <Alert tipo="ok">{exito}</Alert>}
    </>
  )
}

/** Cargando / error / vacio / contenido de una consulta (`useCarga`). */
export function EstadoVista({ cargando, error, vacio, children }: { cargando: boolean; error?: string; vacio?: boolean; children: ReactNode }) {
  if (cargando) return <p className="py-6 text-sm text-ink-secondary">Cargando…</p>
  if (error) return <Alert tipo="error">{error}</Alert>
  if (vacio) return <p className="py-6 text-sm text-ink-secondary">No hay nada para mostrar todavía.</p>
  return <>{children}</>
}

export function TituloPagina({ titulo, descripcion, children }: { titulo: string; descripcion?: string; children?: ReactNode }) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">{titulo}</h1>
        {descripcion && <p className="mt-1 text-sm text-ink-secondary">{descripcion}</p>}
      </div>
      <div className="flex items-center gap-3">{children}</div>
    </div>
  )
}
