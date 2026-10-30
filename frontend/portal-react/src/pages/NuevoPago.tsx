import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { llamar } from '../api'
import { aCentavos, dinero, type Dinero } from '../formato'
import { Aviso, useAccion } from '../ui'

interface Cotizacion {
  desglose: { subtotal: Dinero; descuento: Dinero; cargo_servicio: Dinero; total: Dinero }
  promocion_id: string | null
}

/** Cotiza con la API (que aplica cupon y cargo de servicio) y paga con ese mismo desglose. */
export default function NuevoPago() {
  const ir = useNavigate()
  const [f, setF] = useState({ anfitrion: '', reserva: '', subtotal: '', noches: '1', cupon: '', metodo: 'QR' })
  const [cotizacion, setCotizacion] = useState<Cotizacion>()
  const cotizar = useAccion()
  const pagar = useAccion()
  const campo = (k: keyof typeof f) => ({ value: f[k], onChange: (e: { target: { value: string } }) => { setF({ ...f, [k]: e.target.value }); setCotizacion(undefined) } })

  function alCotizar(e: FormEvent) {
    e.preventDefault()
    void cotizar.correr('Cotización lista', async () => {
      setCotizacion(
        await llamar<Cotizacion>('/pagos/cotizaciones', {
          metodo: 'POST',
          cuerpo: {
            anfitrion_id: f.anfitrion,
            subtotal_centavos: aCentavos(f.subtotal),
            noches: Number(f.noches),
            ...(f.cupon ? { codigo_cupon: f.cupon } : {}),
          },
        }),
      )
    })
  }

  function alPagar() {
    if (!cotizacion) return
    void pagar.correr('Pago creado', async () => {
      const d = cotizacion.desglose
      const pago = await llamar<{ pago_id: string }>('/pagos', {
        metodo: 'POST',
        idempotente: true,
        cuerpo: {
          reserva_id: f.reserva,
          anfitrion_id: f.anfitrion,
          subtotal_centavos: d.subtotal.centavos,
          descuento_centavos: d.descuento.centavos,
          cargo_servicio_centavos: d.cargo_servicio.centavos,
          metodo: f.metodo,
          ...(cotizacion.promocion_id ? { promocion_id: cotizacion.promocion_id } : {}),
        },
      })
      await llamar(`/pagos/${pago.pago_id}/cobro`, { metodo: 'POST', idempotente: true })
      ir(`/pagos/${pago.pago_id}`)
    })
  }

  return (
    <section>
      <h2>Nuevo pago</h2>
      <form onSubmit={alCotizar} className="formulario">
        <label>
          Anfitrión (id)
          <input required placeholder="uuid del anfitrión" {...campo('anfitrion')} />
        </label>
        <label>
          Reserva (id)
          <input required placeholder="uuid de la reserva" {...campo('reserva')} />
        </label>
        <label>
          Subtotal (Bs)
          <input required inputMode="decimal" placeholder="1000.00" {...campo('subtotal')} />
        </label>
        <label>
          Noches
          <input required type="number" min={1} {...campo('noches')} />
        </label>
        <label>
          Cupón (opcional)
          <input {...campo('cupon')} />
        </label>
        <label>
          Método de pago
          <select {...campo('metodo')}>
            {['QR', 'TARJETA', 'TRANSFERENCIA', 'BILLETERA'].map((m) => (
              <option key={m}>{m}</option>
            ))}
          </select>
        </label>
        <button disabled={cotizar.ocupado}>Cotizar</button>
      </form>
      {cotizar.error && <Aviso tipo="error">{cotizar.error}</Aviso>}

      {cotizacion && (
        <div className="tarjeta">
          <h3>Desglose</h3>
          <dl>
            <dt>Subtotal</dt>
            <dd>{dinero(cotizacion.desglose.subtotal)}</dd>
            <dt>Descuento</dt>
            <dd>− {dinero(cotizacion.desglose.descuento)}</dd>
            <dt>Cargo de servicio</dt>
            <dd>{dinero(cotizacion.desglose.cargo_servicio)}</dd>
            <dt>
              <strong>Total</strong>
            </dt>
            <dd>
              <strong>{dinero(cotizacion.desglose.total)}</strong>
            </dd>
          </dl>
          <button onClick={alPagar} disabled={pagar.ocupado || !f.reserva}>
            Pagar ahora
          </button>
          {pagar.error && <Aviso tipo="error">{pagar.error}</Aviso>}
        </div>
      )}
    </section>
  )
}
