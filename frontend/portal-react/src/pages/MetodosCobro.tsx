import { useState, type FormEvent } from 'react'
import { llamar } from '../api'
import { usuarioId } from '../auth'
import { useCarga } from '../useCarga'
import { Aviso, Estado, Insignia, useAccion } from '../ui'

interface Metodo {
  metodo_id: string
  tipo: string
  banco: string | null
  titular: string
  ultimos_digitos: string | null
  es_principal: boolean
  verificado: boolean
}

const VACIO = { tipo: 'CUENTA_BANCARIA', titular: '', banco: '', numero: '' }

/** El numero de cuenta se envia una vez; la API lo guarda cifrado y solo devuelve los ultimos 4 digitos. */
export default function MetodosCobro() {
  const yo = usuarioId()
  const { datos, cargando, error, recargar } = useCarga(() => llamar<Metodo[]>(`/anfitriones/${yo}/metodos-cobro`))
  const accion = useAccion(recargar)
  const [f, setF] = useState(VACIO)
  const campo = (k: keyof typeof f) => ({ value: f[k], onChange: (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value }) })

  function registrar(e: FormEvent) {
    e.preventDefault()
    void accion.correr('Método registrado: queda pendiente de verificación', async () => {
      await llamar(`/anfitriones/${yo}/metodos-cobro`, {
        metodo: 'POST',
        cuerpo: { tipo: f.tipo, titular: f.titular, numero: f.numero, ...(f.banco ? { banco: f.banco } : {}) },
      })
      setF(VACIO)
    })
  }

  return (
    <section>
      <h2>Métodos de cobro</h2>
      <p className="suave">Aquí recibes tus liquidaciones. Solo un método verificado y principal sirve para cobrar.</p>
      <form onSubmit={registrar} className="formulario">
        <label>
          Tipo
          <select {...campo('tipo')}>
            <option value="CUENTA_BANCARIA">Cuenta bancaria</option>
            <option value="QR">QR</option>
            <option value="BILLETERA">Billetera</option>
          </select>
        </label>
        <label>
          Titular
          <input required {...campo('titular')} />
        </label>
        <label>
          Banco
          <input {...campo('banco')} />
        </label>
        <label>
          Número de cuenta
          <input required autoComplete="off" {...campo('numero')} />
        </label>
        <button disabled={accion.ocupado}>Registrar</button>
      </form>
      {accion.error && <Aviso tipo="error">{accion.error}</Aviso>}
      {accion.exito && <Aviso tipo="ok">{accion.exito}</Aviso>}

      <Estado cargando={cargando} error={error} vacio={datos?.length === 0}>
        <table>
          <thead>
            <tr>
              <th>Titular</th>
              <th>Cuenta</th>
              <th>Verificación</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {datos?.map((m) => (
              <tr key={m.metodo_id}>
                <td>
                  {m.titular}
                  {m.es_principal && <small className="suave"> · principal</small>}
                </td>
                <td>
                  {m.banco ? `${m.banco} ` : ''}•••• {m.ultimos_digitos ?? '----'}
                </td>
                <td>
                  <Insignia valor={m.verificado ? 'VERIFICADO' : 'PENDIENTE'} />
                </td>
                <td>
                  {!m.es_principal && (
                    <button className="secundario" onClick={() => accion.correr('Ahora es el método principal', () => llamar(`/metodos-cobro/${m.metodo_id}/principal`, { metodo: 'POST' }))}>
                      Hacer principal
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
