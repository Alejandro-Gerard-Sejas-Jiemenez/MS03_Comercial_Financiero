import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes } from 'react'
import { cn } from '@/utils/cn'

/** Etiqueta + control, como los campos de los formularios de MS-01. */
export function Field({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="label">{etiqueta}</span>
      {children}
    </label>
  )
}

export function Input({ className, ...resto }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={cn('input', className)} {...resto} />
}

export function Select({ className, children, ...resto }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={cn('input', className)} {...resto}>
      {children}
    </select>
  )
}

/** Cuadricula responsive para los campos de un formulario. */
export function FormGrid({ children, onSubmit }: { children: ReactNode; onSubmit: (e: React.FormEvent) => void }) {
  return (
    <form onSubmit={onSubmit} className="card mb-6 grid grid-cols-1 items-end gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
      {children}
    </form>
  )
}
