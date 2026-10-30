import { useParams } from 'react-router-dom'
import { llamar } from '../api'
import { tieneRol } from '../auth'
import { dinero, totalDe, type Dinero } from '../formato'
import { useCarga } from '../useCarga'
import { Aviso, Estado, Insignia, useAccion } from '../ui'

interface Detalle {
  pago: {
    pago_id: string
    reserva_id: string
    estado: string
    metodo: string
    subtotal: Dinero
    descuento: Dinero
    cargo_servicio: Dinero
    referencia_pasarela: string | null
  }
  transacciones: { transaccion_id: string; tipo: string; estado: string; monto: Dinero }[]
  reembolsos: { reembolso_id: string; estado: string; monto: Dinero }[]
  comprobante: { numero: string; tipo: string } | null
  total_reembolsado: Dinero
}

export default function DetallePago() {
  const { id = '' } = useParams()
  const { datos, cargando, error, recargar } = useCarga(() => llamar<Detalle>(`/pagos/${id}`), [id])
  const accion = useAccion(recargar)
  const p = datos?.pago
  const esCliente = tieneRol('CLIENTE', 'ADMIN')

  return (
    <section>
      <h2>Detalle del pago</h2>
      <Estado cargando={cargando} error={error}>
        {p && datos && (
          <>
            <div className="tarjeta">
              <dl>
                <dt>Estado</dt>
                <dd>
                  <Insignia valor={p.estado} />
                </dd>
                <dt>Método</dt>
                <dd>{p.metodo}</dd>
                <dt>Total</dt>
                <dd>{dinero(totalDe(p))}</dd>
                <dt>Reembolsado</dt>
                <dd>{dinero(datos.total_reembolsado)}</dd>
                <dt>Comprobante</dt>
                <dd>{datos.comprobante ? datos.comprobante.numero : '—'}</dd>
                <dt>Referencia de la pasarela</dt>
                <dd>{p.referencia_pasarela ?? '—'}</dd>
              </dl>
              {esCliente && (
                <div className="acciones">
                  {p.estado === 'PENDIENTE' && (
                    <>
                      <button disabled={accion.ocupado} onClick={() => accion.correr('Pago cobrado', () => llamar(`/pagos/${id}/cobro`, { metodo: 'POST', idempotente: true }))}>
                        Cobrar
                      </button>
                      <button className="secundario" disabled={accion.ocupado} onClick={() => accion.correr('Pago cancelado', () => llamar(`/pagos/${id}/cancelacion`, { metodo: 'POST', cuerpo: { motivo: 'Cancelado por el cliente' } }))}>
                        Cancelar
                      </button>
                    </>
                  )}
                  {p.estado === 'CAPTURADO' && (
                    <>
                      {!datos.comprobante && (
                        <button disabled={accion.ocupado} onClick={() => accion.correr('Comprobante emitido', () => llamar(`/pagos/${id}/comprobante`, { metodo: 'POST', cuerpo: { tipo: 'RECIBO' } }))}>
                          Emitir comprobante
                        </button>
                      )}
                      <button className="secundario" disabled={accion.ocupado} onClick={() => accion.correr('Reembolso solicitado', () => llamar('/reembolsos', { metodo: 'POST', cuerpo: { reserva_id: p.reserva_id, motivo: 'CANCELACION_CLIENTE' } }))}>
                        Pedir reembolso
                      </button>
                    </>
                  )}
                </div>
              )}
              {accion.error && <Aviso tipo="error">{accion.error}</Aviso>}
              {accion.exito && <Aviso tipo="ok">{accion.exito}</Aviso>}
            </div>

            <h3>Movimientos con la pasarela</h3>
            {datos.transacciones.length === 0 ? (
              <p className="suave">Sin movimientos.</p>
            ) : (
              <table>
                <tbody>
                  {datos.transacciones.map((t) => (
                    <tr key={t.transaccion_id}>
                      <td>{t.tipo}</td>
                      <td>
                        <Insignia valor={t.estado} />
                      </td>
                      <td>{dinero(t.monto)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}

            <h3>Reembolsos</h3>
            {datos.reembolsos.length === 0 ? (
              <p className="suave">Sin reembolsos.</p>
            ) : (
              <table>
                <tbody>
                  {datos.reembolsos.map((r) => (
                    <tr key={r.reembolso_id}>
                      <td>{r.reembolso_id.slice(0, 8)}…</td>
                      <td>
                        <Insignia valor={r.estado} />
                      </td>
                      <td>{dinero(r.monto)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </>
        )}
      </Estado>
    </section>
  )
}
