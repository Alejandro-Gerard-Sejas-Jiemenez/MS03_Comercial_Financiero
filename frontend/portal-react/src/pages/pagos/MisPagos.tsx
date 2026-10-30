import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { tieneRol } from '@/api/auth'
import { EstadoVista, TituloPagina } from '@/components/ui/Alert'
import { EstadoBadge } from '@/components/ui/Badge'
import { ButtonLink } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Select } from '@/components/ui/Field'
import { useCarga } from '@/hooks/useCarga'
import { listarPagos } from '@/services/pagosApi'
import { dinero, totalDe } from '@/utils/formato'

export default function MisPagos() {
  // Quien es cliente y anfitrion a la vez elige con que papel mira sus pagos.
  const [como, setComo] = useState<'cliente' | 'anfitrion'>(tieneRol('CLIENTE') ? 'cliente' : 'anfitrion')
  const { datos, cargando, error } = useCarga(() => listarPagos(como), [como])

  return (
    <section>
      <TituloPagina titulo={como === 'cliente' ? 'Mis pagos' : 'Pagos recibidos'}>
        {tieneRol('CLIENTE') && tieneRol('ANFITRION') && (
          <Select value={como} onChange={(e) => setComo(e.target.value as 'cliente' | 'anfitrion')} aria-label="Ver como">
            <option value="cliente">Como cliente</option>
            <option value="anfitrion">Como anfitrión</option>
          </Select>
        )}
        {tieneRol('CLIENTE') && (
          <ButtonLink to="/pagos/nuevo" icono={<Plus className="h-4 w-4" />}>
            Nuevo pago
          </ButtonLink>
        )}
      </TituloPagina>

      <EstadoVista cargando={cargando} error={error} vacio={datos?.length === 0}>
        <Card className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>Reserva</th>
                <th>Método</th>
                <th>Total</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {datos?.map((p) => (
                <tr key={p.pago_id}>
                  <td title={p.reserva_id}>{p.reserva_id.slice(0, 8)}…</td>
                  <td>{p.metodo}</td>
                  <td className="font-medium">{dinero(totalDe(p))}</td>
                  <td>
                    <EstadoBadge valor={p.estado} />
                  </td>
                  <td className="text-right">
                    <Link to={`/pagos/${p.pago_id}`} className="font-medium text-brand-primary hover:text-brand-hover">
                      Ver
                    </Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </Card>
      </EstadoVista>
    </section>
  )
}
