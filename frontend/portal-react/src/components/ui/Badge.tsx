import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

type Tono = 'neutral' | 'positive' | 'brand' | 'warning' | 'danger'

const tonos: Record<Tono, string> = {
  neutral: 'bg-surface-secondary text-ink-secondary border-line',
  positive: 'bg-green-50 text-positive border-green-200',
  brand: 'bg-brand-light text-brand-primary border-brand-primary/20',
  warning: 'bg-amber-50 text-amber-700 border-amber-200',
  danger: 'bg-red-50 text-danger border-red-200',
}

export function Badge({ children, tono = 'neutral', className }: { children: ReactNode; tono?: Tono; className?: string }) {
  return (
    <span className={cn('inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium', tonos[tono], className)}>
      {children}
    </span>
  )
}

/** Insignia de un estado de la API: el color sale del significado del estado. */
export function EstadoBadge({ valor }: { valor?: string }) {
  const v = valor ?? '—'
  const tono: Tono = /CAPTURADO|ACTIV|APROBAD|PAGAD|VERIFICAD|COMPLETAD/.test(v)
    ? 'positive'
    : /PENDIENTE|BORRADOR|GENERAD|SOLICITAD|PAUSAD|RETENID/.test(v)
      ? 'warning'
      : /FALLIDO|CANCEL|RECHAZ|ANULAD|VENCID/.test(v)
        ? 'danger'
        : 'neutral'
  return <Badge tono={tono}>{v.replaceAll('_', ' ')}</Badge>
}

export default Badge
