import { useState, type FormEvent } from 'react'
import { ResultadoAccion, EstadoVista, TituloPagina } from '@/components/ui/Alert'
import { EstadoBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Field, FormGrid, Input, Select } from '@/components/ui/Field'
import { useAccion } from '@/hooks/useAccion'
import { useCarga } from '@/hooks/useCarga'
import { cambiarEstadoPromocion, crearPromocion, listarPromociones } from '@/services/promocionesApi'
import type { EstadoEditable } from '@/types'
import { fecha } from '@/utils/formato'

const VACIO = { nombre: '', tipo: 'CUPON', tipo_descuento: 'PORCENTAJE', valor: '10', codigo: '', desde: '', hasta: '', max_usos: '' }

export default function MisPromociones() {
  const { datos, cargando, error, recargar } = useCarga(listarPromociones)
  const accion = useAccion(recargar)
  const [f, setF] = useState(VACIO)
  const campo = (k: keyof typeof f) => ({ value: f[k], onChange: (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value }) })

  function crear(e: FormEvent) {
    e.preventDefault()
    void accion.correr('Promoción creada (queda en borrador)', async () => {
      await crearPromocion({
        nombre: f.nombre,
        tipo: f.tipo,
        tipoDescuento: f.tipo_descuento,
        valor: f.valor,
        desde: f.desde,
        hasta: f.hasta,
        codigo: f.codigo || undefined,
        maxUsos: f.max_usos ? Number(f.max_usos) : undefined,
      })
      setF(VACIO)
    })
  }

  const cambiar = (id: string, estado: EstadoEditable, texto: string) => accion.correr(texto, () => cambiarEstadoPromocion(id, estado))

  return (
    <section>
      <TituloPagina titulo="Mis promociones" descripcion="Crea cupones y descuentos. Quedan en borrador hasta que los actives." />

      <FormGrid onSubmit={crear}>
        <Field etiqueta="Nombre">
          <Input required {...campo('nombre')} />
        </Field>
        <Field etiqueta="Tipo">
          <Select {...campo('tipo')}>
            {['CUPON', 'TEMPORADA', 'FECHA_ESPECIAL', 'ESTADIA_PROLONGADA', 'ULTIMO_MINUTO'].map((t) => (
              <option key={t}>{t}</option>
            ))}
          </Select>
        </Field>
        <Field etiqueta="Descuento">
          <Select {...campo('tipo_descuento')}>
            <option value="PORCENTAJE">Porcentaje (%)</option>
            <option value="MONTO_FIJO">Monto fijo (Bs)</option>
          </Select>
        </Field>
        <Field etiqueta="Valor">
          <Input required inputMode="decimal" {...campo('valor')} />
        </Field>
        <Field etiqueta="Código del cupón">
          <Input {...campo('codigo')} />
        </Field>
        <Field etiqueta="Desde">
          <Input required type="date" {...campo('desde')} />
        </Field>
        <Field etiqueta="Hasta">
          <Input required type="date" {...campo('hasta')} />
        </Field>
        <Field etiqueta="Máx. usos">
          <Input type="number" min={1} {...campo('max_usos')} />
        </Field>
        <Button disabled={accion.ocupado}>Crear</Button>
      </FormGrid>
      <ResultadoAccion error={accion.error} exito={accion.exito} />

      <EstadoVista cargando={cargando} error={error} vacio={datos?.length === 0}>
        <Card className="overflow-x-auto">
          <table className="table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Descuento</th>
                <th>Vigencia</th>
                <th>Usos</th>
                <th>Estado</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {datos?.map((p) => (
                <tr key={p.promocion_id}>
                  <td>
                    <span className="font-medium">{p.nombre}</span>
                    {p.codigo && <span className="text-ink-secondary"> · {p.codigo}</span>}
                  </td>
                  <td>{p.tipo_descuento === 'PORCENTAJE' ? `${p.valor} %` : `Bs ${p.valor}`}</td>
                  <td>
                    {fecha(p.vigente_desde)} – {fecha(p.vigente_hasta)}
                  </td>
                  <td>
                    {p.usos_actuales}
                    {p.max_usos ? ` / ${p.max_usos}` : ''}
                  </td>
                  <td>
                    <EstadoBadge valor={p.estado} />
                  </td>
                  <td>
                    <div className="flex justify-end gap-2">
                      {(p.estado === 'BORRADOR' || p.estado === 'PAUSADA') && (
                        <Button tamano="sm" onClick={() => cambiar(p.promocion_id, 'ACTIVA', 'Promoción activada')}>
                          Activar
                        </Button>
                      )}
                      {p.estado === 'ACTIVA' && (
                        <Button tamano="sm" variante="secondary" onClick={() => cambiar(p.promocion_id, 'PAUSADA', 'Promoción pausada')}>
                          Pausar
                        </Button>
                      )}
                      {['BORRADOR', 'ACTIVA', 'PAUSADA'].includes(p.estado) && (
                        <Button tamano="sm" variante="danger" onClick={() => cambiar(p.promocion_id, 'CANCELADA', 'Promoción cancelada')}>
                          Cancelar
                        </Button>
                      )}
                    </div>
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
