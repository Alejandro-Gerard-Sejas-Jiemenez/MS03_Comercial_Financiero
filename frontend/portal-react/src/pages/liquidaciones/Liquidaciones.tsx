import { usuarioId } from '@/api/auth'
import { EstadoVista, TituloPagina } from '@/components/ui/Alert'
import { EstadoBadge } from '@/components/ui/Badge'
import { Card } from '@/components/ui/Card'
import { useCarga } from '@/hooks/useCarga'
import { listarLiquidaciones, saldoDelAnfitrion } from '@/services/liquidacionesApi'
import { dinero, fecha } from '@/utils/formato'

export default function Liquidaciones() {
  const yo = usuarioId()
  const saldo = useCarga(() => saldoDelAnfitrion(yo))
  const historial = useCarga(() => listarLiquidaciones(yo))

  return (
    <section>
      <TituloPagina titulo="Liquidaciones y saldo" />

      <Card className="mb-8 p-5">
        <span className="text-sm text-ink-secondary">Saldo por cobrar</span>
        <EstadoVista cargando={saldo.cargando} error={saldo.error}>
          <p className="mt-1 text-3xl font-bold tracking-tight text-brand-primary">{dinero(saldo.datos)}</p>
        </EstadoVista>
      </Card>

      <h2 className="mb-3 text-lg font-semibold">Historial</h2>
      <EstadoVista cargando={historial.cargando} error={historial.error} vacio={historial.datos?.length === 0}>
        <Card className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>Periodo</th>
                <th>Bruto</th>
                <th>Comisiones</th>
                <th>Neto</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              {historial.datos?.map((l) => (
                <tr key={l.liquidacion_id}>
                  <td>
                    {fecha(l.periodo_desde)} – {fecha(l.periodo_hasta)}
                  </td>
                  <td>{dinero(l.total_bruto)}</td>
                  <td>{dinero(l.total_comisiones)}</td>
                  <td className="font-semibold">{dinero(l.total_neto)}</td>
                  <td>
                    <EstadoBadge valor={l.estado} />
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
