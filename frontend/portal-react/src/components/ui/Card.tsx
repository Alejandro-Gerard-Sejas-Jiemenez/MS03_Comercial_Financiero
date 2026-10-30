import type { ReactNode } from 'react'
import { cn } from '@/utils/cn'

interface CardProps {
  children: ReactNode
  className?: string
}

export function Card({ children, className }: CardProps) {
  return <div className={cn('card', className)}>{children}</div>
}

/** Lista de pares etiqueta / valor dentro de una tarjeta. */
export function Datos({ items }: { items: { k: ReactNode; v: ReactNode }[] }) {
  return (
    <dl className="grid grid-cols-[max-content_1fr] gap-x-8 gap-y-2.5 text-sm">
      {items.map(({ k: etiqueta, v: valor }, i) => (
        <div key={i} className="contents">
          <dt className="text-ink-secondary">{etiqueta}</dt>
          <dd className="text-ink-primary">{valor}</dd>
        </div>
      ))}
    </dl>
  )
}

export default Card
