import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { Alert, ResultadoAccion, TituloPagina } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Card, Datos } from '@/components/ui/Card'
import { Field, FormGrid, Input, Select } from '@/components/ui/Field'
import { useAccion } from '@/hooks/useAccion'
import { cotizar, crearYCobrarPago } from '@/services/pagosApi'
import type { Cotizacion } from '@/types'
import { aCentavos, dinero } from '@/utils/formato'

/** Cotiza con la API (que aplica cupon y cargo de servicio) y paga con ese mismo desglose. */
export default function NuevoPago() {
  const ir = useNavigate()
  const [f, setF] = useState({ anfitrion: '', reserva: '', subtotal: '', noches: '1', cupon: '', metodo: 'QR' })
  const [cotizacion, setCotizacion] = useState<Cotizacion>()
  const cotizando = useAccion()
  const pagando = useAccion()
  const campo = (k: keyof typeof f) => ({
    value: f[k],
    onChange: (e: { target: { value: string } }) => {
      setF({ ...f, [k]: e.target.value })
      setCotizacion(undefined)
    },
  })

  function alCotizar(e: FormEvent) {
    e.preventDefault()
    void cotizando.correr('Cotización lista', async () => {
      setCotizacion(
        await cotizar({
          anfitrionId: f.anfitrion,
          subtotalCentavos: aCentavos(f.subtotal),
          noches: Number(f.noches),
          cupon: f.cupon || undefined,
        }),
      )
    })
  }

  function alPagar() {
    if (!cotizacion) return
    void pagando.correr('Pago creado', async () => {
      const id = await crearYCobrarPago({ reservaId: f.reserva, anfitrionId: f.anfitrion, metodo: f.metodo, cotizacion })
      ir(`/pagos/${id}`)
    })
  }

  const d = cotizacion?.desglose

  return (
    <section>
      <TituloPagina titulo="Nuevo pago" descripcion="Cotiza primero: la API aplica el cupón y el cargo de servicio." />

      <FormGrid onSubmit={alCotizar}>
        <Field etiqueta="Anfitrión (id)">
          <Input required placeholder="uuid del anfitrión" {...campo('anfitrion')} />
        </Field>
        <Field etiqueta="Reserva (id)">
          <Input required placeholder="uuid de la reserva" {...campo('reserva')} />
        </Field>
        <Field etiqueta="Subtotal (Bs)">
          <Input required inputMode="decimal" placeholder="1000.00" {...campo('subtotal')} />
        </Field>
        <Field etiqueta="Noches">
          <Input required type="number" min={1} {...campo('noches')} />
        </Field>
        <Field etiqueta="Cupón (opcional)">
          <Input {...campo('cupon')} />
        </Field>
        <Field etiqueta="Método de pago">
          <Select {...campo('metodo')}>
            {['QR', 'TARJETA', 'TRANSFERENCIA', 'BILLETERA'].map((m) => (
              <option key={m}>{m}</option>
            ))}
          </Select>
        </Field>
        <Button disabled={cotizando.ocupado}>Cotizar</Button>
      </FormGrid>
      {cotizando.error && <Alert tipo="error">{cotizando.error}</Alert>}

      {d && (
        <Card className="p-5">
          <h2 className="mb-4 text-lg font-semibold">Desglose</h2>
          <Datos
            items={[
              { k: 'Subtotal', v: dinero(d.subtotal) },
              { k: 'Descuento', v: `− ${dinero(d.descuento)}` },
              { k: 'Cargo de servicio', v: dinero(d.cargo_servicio) },
              { k: <strong>Total</strong>, v: <strong>{dinero(d.total)}</strong> },
            ]}
          />
          <div className="mt-5">
            <Button onClick={alPagar} disabled={pagando.ocupado || !f.reserva}>
              Pagar ahora
            </Button>
          </div>
          <ResultadoAccion error={pagando.error} />
        </Card>
      )}
    </section>
  )
}
