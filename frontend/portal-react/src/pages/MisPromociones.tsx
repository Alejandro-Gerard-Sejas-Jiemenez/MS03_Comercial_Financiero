import { useState, type FormEvent } from 'react'
import { llamar } from '../api'
import { fecha } from '../formato'
import { useCarga } from '../useCarga'
import { Aviso, Estado, Insignia, useAccion } from '../ui'

interface Promocion {
  promocion_id: string
  nombre: string
  tipo: string
  tipo_descuento: string
  valor: string
  codigo: string | null
  estado: string
  vigente_desde: string
  vigente_hasta: string
  usos_actuales: number
  max_usos: number | null
}

const VACIO = { nombre: '', tipo: 'CUPON', tipo_descuento: 'PORCENTAJE', valor: '10', codigo: '', desde: '', hasta: '', max_usos: '' }

export default function MisPromociones() {
  const { datos, cargando, error, recargar } = useCarga(() => llamar<Promocion[]>('/promociones'))
  const accion = useAccion(recargar)
  const [f, setF] = useState(VACIO)
  const campo = (k: keyof typeof f) => ({ value: f[k], onChange: (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value }) })

  function crear(e: FormEvent) {
    e.preventDefault()
    void accion.correr('Promoción creada (queda en borrador)', async () => {
      await llamar('/promociones', {
        metodo: 'POST',
        cuerpo: {
          nombre: f.nombre,
          tipo: f.tipo,
          tipo_descuento: f.tipo_descuento,
          valor: f.valor,
          vigente_desde: f.desde,
          vigente_hasta: f.hasta,
          ...(f.codigo ? { codigo: f.codigo } : {}),
          ...(f.max_usos ? { max_usos: Number(f.max_usos) } : {}),
        },
      })
      setF(VACIO)
    })
  }

  const cambiar = (id: string, paso: 'activacion' | 'pausa' | 'cancelacion', texto: string) =>
    accion.correr(texto, () => llamar(`/promociones/${id}/${paso}`, { metodo: 'POST' }))

  return (
    <section>
      <h2>Mis promociones</h2>
      <form onSubmit={crear} className="formulario">
        <label>
          Nombre
          <input required {...campo('nombre')} />
        </label>
        <label>
          Tipo
          <select {...campo('tipo')}>
            {['CUPON', 'TEMPORADA', 'FECHA_ESPECIAL', 'ESTADIA_PROLONGADA', 'ULTIMO_MINUTO'].map((t) => (
              <option key={t}>{t}</option>
            ))}
          </select>
        </label>
        <label>
          Descuento
          <select {...campo('tipo_descuento')}>
            <option value="PORCENTAJE">Porcentaje (%)</option>
            <option value="MONTO_FIJO">Monto fijo (Bs)</option>
          </select>
        </label>
        <label>
          Valor
          <input required inputMode="decimal" {...campo('valor')} />
        </label>
        <label>
          Código del cupón
          <input {...campo('codigo')} />
        </label>
        <label>
          Desde
          <input required type="date" {...campo('desde')} />
        </label>
        <label>
          Hasta
          <input required type="date" {...campo('hasta')} />
        </label>
        <label>
          Máx. usos
          <input type="number" min={1} {...campo('max_usos')} />
        </label>
        <button disabled={accion.ocupado}>Crear</button>
      </form>
      {accion.error && <Aviso tipo="error">{accion.error}</Aviso>}
      {accion.exito && <Aviso tipo="ok">{accion.exito}</Aviso>}

      <Estado cargando={cargando} error={error} vacio={datos?.length === 0}>
        <table>
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
                  {p.nombre}
                  {p.codigo && <small className="suave"> · {p.codigo}</small>}
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
                  <Insignia valor={p.estado} />
                </td>
                <td className="acciones">
                  {(p.estado === 'BORRADOR' || p.estado === 'PAUSADA') && <button onClick={() => cambiar(p.promocion_id, 'activacion', 'Promoción activada')}>Activar</button>}
                  {p.estado === 'ACTIVA' && (
                    <button className="secundario" onClick={() => cambiar(p.promocion_id, 'pausa', 'Promoción pausada')}>
                      Pausar
                    </button>
                  )}
                  {['BORRADOR', 'ACTIVA', 'PAUSADA'].includes(p.estado) && (
                    <button className="peligro" onClick={() => cambiar(p.promocion_id, 'cancelacion', 'Promoción cancelada')}>
                      Cancelar
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Estado>
    </section>
  )
}
