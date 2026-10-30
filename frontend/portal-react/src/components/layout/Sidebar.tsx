import { NavLink } from 'react-router-dom'
import { CreditCard, Landmark, LogOut, Percent, Wallet, type LucideIcon } from 'lucide-react'
import { cerrarSesion, nombreUsuario, roles, tieneRol } from '@/api/auth'
import { cn } from '@/utils/cn'

interface Enlace {
  to: string
  etiqueta: string
  icono: LucideIcon
  visible: boolean
}

export function Marca() {
  return (
    <div className="flex items-center gap-2.5">
      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary text-white">
        <Wallet className="h-5 w-5" />
      </span>
      <span className="leading-tight">
        <span className="block text-lg font-bold tracking-tight text-ink-primary">Aloja-Te</span>
        <span className="block text-xs font-medium text-ink-secondary">Comercial y financiero</span>
      </span>
    </div>
  )
}

/** Menu lateral: navegacion por rol arriba y la persona con su boton de salir abajo. */
export function Sidebar({ alNavegar }: { alNavegar?: () => void }) {
  const anfitrion = tieneRol('ANFITRION')
  const cliente = tieneRol('CLIENTE')

  const enlaces: Enlace[] = [
    { to: '/pagos', etiqueta: 'Pagos', icono: CreditCard, visible: cliente || anfitrion },
    { to: '/promociones', etiqueta: 'Promociones', icono: Percent, visible: anfitrion },
    { to: '/metodos-cobro', etiqueta: 'Métodos de cobro', icono: Wallet, visible: anfitrion },
    { to: '/liquidaciones', etiqueta: 'Liquidaciones', icono: Landmark, visible: anfitrion },
  ].filter((e) => e.visible)

  return (
    <div className="flex h-full flex-col">
      <div className="hidden border-b border-line px-5 py-5 md:block">
        <Marca />
      </div>

      <nav aria-label="Principal" className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        {enlaces.map(({ to, etiqueta, icono: Icono }) => (
          <NavLink
            key={to}
            to={to}
            onClick={alNavegar}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                isActive ? 'bg-brand-light text-brand-primary' : 'text-ink-secondary hover:bg-surface-secondary hover:text-ink-primary',
              )
            }
          >
            <Icono className="h-[18px] w-[18px] shrink-0" />
            {etiqueta}
          </NavLink>
        ))}
      </nav>

      <div className="border-t border-line p-4">
        <p className="truncate text-sm font-semibold text-ink-primary">{nombreUsuario() || 'Usuario'}</p>
        <p className="mb-3 truncate text-xs text-ink-secondary">{roles().join(' · ') || 'sin rol'}</p>
        <button onClick={cerrarSesion} className="btn btn-secondary w-full">
          <LogOut className="h-4 w-4" />
          Salir
        </button>
      </div>
    </div>
  )
}

export default Sidebar
