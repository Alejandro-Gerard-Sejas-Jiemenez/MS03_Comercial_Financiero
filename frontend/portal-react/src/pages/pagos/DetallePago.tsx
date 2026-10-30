import { useParams } from 'react-router-dom'
import { tieneRol } from '@/api/auth'
import { EstadoVista, ResultadoAccion, TituloPagina } from '@/components/ui/Alert'
import { EstadoBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card, Datos } from '@/components/ui/Card'
import { useAccion } from '@/hooks/useAccion'
import { useCarga } from '@/hooks/useCarga'
import { cancelarPago, cobrarPago, detallePago, emitirComprobante, pedirReembolso } from '@/services/pagosApi'
import { dinero, totalDe } from '@/utils/formato'

export default function DetallePago() {
  const { id = '' } = useParams()
  const { datos, cargando, error, recargar } = useCarga(() => detallePago(id), [id])
  const accion = useAccion(recargar)
  const p = datos?.pago
  const esCliente = tieneRol('CLIENTE', 'ADMIN')

  return (
    <section>
      <TituloPagina titulo="Detalle del pago" />
      <EstadoVista cargando={cargando} error={error}>
        {p && datos && (
          <div className="space-y-8">
            <Card className="p-5">
              <Datos
                items={[
                  { k: 'Estado', v: <EstadoBadge valor={p.estado} /> },
                  { k: 'Método', v: p.metodo },
                  { k: 'Total', v: dinero(totalDe(p)) },
                  { k: 'Reembolsado', v: dinero(datos.total_reembolsado) },
                  { k: 'Comprobante', v: datos.comprobante ? datos.comprobante.numero : '—' },
                  { k: 'Referencia de la pasarela', v: p.referencia_pasarela ?? '—' },
                ]}
              />
              {esCliente && (
                <div className="mt-5 flex flex-wrap gap-2">
                  {p.estado === 'PENDIENTE' && (
                    <>
                      <Button disabled={accion.ocupado} onClick={() => accion.correr('Pago cobrado', () => cobrarPago(id))}>
                        Cobrar
                      </Button>
                      <Button variante="secondary" disabled={accion.ocupado} onClick={() => accion.correr('Pago cancelado', () => cancelarPago(id))}>
                        Cancelar
                      </Button>
                    </>
                  )}
                  {p.estado === 'CAPTURADO' && (
                    <>
                      {!datos.comprobante && (
                        <Button disabled={accion.ocupado} onClick={() => accion.correr('Comprobante emitido', () => emitirComprobante(id))}>
                          Emitir comprobante
                        </Button>
                      )}
                      <Button variante="secondary" disabled={accion.ocupado} onClick={() => accion.correr('Reembolso solicitado', () => pedirReembolso(p.reserva_id))}>
                        Pedir reembolso
                      </Button>
                    </>
                  )}
                </div>
              )}
              <ResultadoAccion error={accion.error} exito={accion.exito} />
            </Card>

            <div>
              <h2 className="mb-3 text-lg font-semibold">Movimientos con la pasarela</h2>
              {datos.transacciones.length === 0 ? (
                <p className="text-sm text-ink-secondary">Sin movimientos.</p>
              ) : (
                <Card className="overflow-x-auto">
                  <table className="table">
                    <tbody>
                      {datos.transacciones.map((t) => (
                        <tr key={t.transaccion_id}>
                          <td>{t.tipo}</td>
                          <td>
                            <EstadoBadge valor={t.estado} />
                          </td>
                          <td className="text-right font-medium">{dinero(t.monto)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </Card>
              )}
            </div>

            <div>
              <h2 className="mb-3 text-lg font-semibold">Reembolsos</h2>
              {datos.reembolsos.length === 0 ? (
                <p className="text-sm text-ink-secondary">Sin reembolsos.</p>
              ) : (
                <Card className="overflow-x-auto">
                  <table className="table">
                    <tbody>
                      {datos.reembolsos.map((r) => (
                        <tr key={r.reembolso_id}>
                          <td>{r.reembolso_id.slice(0, 8)}…</td>
                          <td>
                            <EstadoBadge valor={r.estado} />
                          </td>
                          <td className="text-right font-medium">{dinero(r.monto)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </Card>
              )}
            </div>
          </div>
        )}
      </EstadoVista>
    </section>
  )
}
