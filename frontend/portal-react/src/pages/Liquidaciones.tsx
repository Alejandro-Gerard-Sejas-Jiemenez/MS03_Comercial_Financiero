import { llamar } from '../api'
import { usuarioId } from '../auth'
import { dinero, fecha, type Dinero } from '../formato'
import { useCarga } from '../useCarga'
import { Estado, Insignia } from '../ui'

interface Liquidacion {
  liquidacion_id: string
  estado: string
  periodo_desde: string
  periodo_hasta: string
  total_bruto: Dinero
  total_comisiones: Dinero
  total_neto: Dinero
}

export default function Liquidaciones() {
  const yo = usuarioId()
  const saldo = useCarga(() => llamar<Dinero>(`/anfitriones/${yo}/saldo`))
  const historial = useCarga(() => llamar<Liquidacion[]>(`/anfitriones/${yo}/liquidaciones`))

  return (
    <section>
      <h2>Liquidaciones y saldo</h2>
      <div className="tarjeta saldo">
        <span className="suave">Saldo por cobrar</span>
        <Estado cargando={saldo.cargando} error={saldo.error}>
          <strong>{dinero(saldo.datos)}</strong>
        </Estado>
      </div>

      <h3>Historial</h3>
      <Estado cargando={historial.cargando} error={historial.error} vacio={historial.datos?.length === 0}>
        <table>
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
                <td>
                  <strong>{dinero(l.total_neto)}</strong>
                </td>
                <td>
                  <Insignia valor={l.estado} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Estado>
    </section>
  )
}
