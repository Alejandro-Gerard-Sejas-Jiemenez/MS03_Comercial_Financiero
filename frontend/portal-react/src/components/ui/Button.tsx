import type { ButtonHTMLAttributes, ReactNode } from 'react'
import { Link, type LinkProps } from 'react-router-dom'
import { cn } from '@/utils/cn'

type Variante = 'primary' | 'secondary' | 'ghost' | 'danger'
type Tamano = 'sm' | 'md'

const variantes: Record<Variante, string> = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  ghost: 'btn-ghost',
  danger: 'btn-danger',
}

const tamanos: Record<Tamano, string> = {
  sm: '!px-3 !py-1.5 !text-xs',
  md: '',
}

interface Comunes {
  variante?: Variante
  tamano?: Tamano
  icono?: ReactNode
}

const clases = ({ variante = 'primary', tamano = 'md' }: Comunes, extra?: string) =>
  cn('btn', variantes[variante], tamanos[tamano], extra)

export function Button({ variante, tamano, icono, className, children, ...resto }: Comunes & ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button className={clases({ variante, tamano }, className)} {...resto}>
      {icono}
      {children}
    </button>
  )
}

/** Enlace con aspecto de boton. */
export function ButtonLink({ variante, tamano, icono, className, children, ...resto }: Comunes & LinkProps) {
  return (
    <Link className={clases({ variante, tamano }, className)} {...resto}>
      {icono}
      {children}
    </Link>
  )
}

export default Button
