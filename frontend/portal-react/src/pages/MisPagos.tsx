import { useState } from 'react'
import { Link } from 'react-router-dom'
import { llamar } from '../api'
import { tieneRol } from '../auth'
import { dinero, totalDe, type Dinero } from '../formato'
import { useCarga } from '../useCarga'
import { Estado, Insignia } from '../ui'

interface Pago {
  pago_id: string
  reserva_id: string
  estado: string
  metodo: string
  subtotal: Dinero
  descuento: Dinero
  cargo_servicio: Dinero
}

export default function MisPagos() {
  // Quien es cliente y anfitrion a la vez elige con que papel mira sus pagos.
  const [como, setComo] = useState<'cliente' | 'anfitrion'>(tieneRol('CLIENTE') ? 'cliente' : 'anfitrion')
  const { datos, cargando, error } = useCarga(() => llamar<Pago[]>(`/pagos?como=${como}`), [como])

  return (
    <section>
      <header className="titulo">
        <h2>{como === 'cliente' ? 'Mis pagos' : 'Pagos recibidos'}</h2>
        {tieneRol('CLIENTE') && tieneRol('ANFITRION') && (
          <select value={como} onChange={(e) => setComo(e.target.value as 'cliente' | 'anfitrion')}>
            <option value="cliente">Como cliente</option>
            <option value="anfitrion">Como anfitrión</option>
          </select>
        )}
        {tieneRol('CLIENTE') && (
          <Link className="boton" to="/pagos/nuevo">
            Nuevo pago
          </Link>
        )}
      </header>
      <Estado cargando={cargando} error={error} vacio={datos?.length === 0}>
        <table>
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
                <td>{dinero(totalDe(p))}</td>
                <td>
                  <Insignia valor={p.estado} />
                </td>
                <td>
                  <Link to={`/pagos/${p.pago_id}`}>Ver</Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Estado>
    </section>
  )
}
