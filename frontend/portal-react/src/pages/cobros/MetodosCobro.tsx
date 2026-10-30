import { useState, type FormEvent } from 'react'
import { usuarioId } from '@/api/auth'
import { EstadoVista, ResultadoAccion, TituloPagina } from '@/components/ui/Alert'
import { EstadoBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { Field, FormGrid, Input, Select } from '@/components/ui/Field'
import { useAccion } from '@/hooks/useAccion'
import { useCarga } from '@/hooks/useCarga'
import { hacerPrincipal, listarMetodosCobro, registrarMetodoCobro } from '@/services/metodosCobroApi'

const VACIO = { tipo: 'CUENTA_BANCARIA', titular: '', banco: '', numero: '' }

/** El numero de cuenta se envia una vez; la API lo guarda cifrado y solo devuelve los ultimos 4 digitos. */
export default function MetodosCobro() {
  const yo = usuarioId()
  const { datos, cargando, error, recargar } = useCarga(() => listarMetodosCobro(yo))
  const accion = useAccion(recargar)
  const [f, setF] = useState(VACIO)
  const campo = (k: keyof typeof f) => ({ value: f[k], onChange: (e: { target: { value: string } }) => setF({ ...f, [k]: e.target.value }) })

  function registrar(e: FormEvent) {
    e.preventDefault()
    void accion.correr('Método registrado: queda pendiente de verificación', async () => {
      await registrarMetodoCobro(yo, { tipo: f.tipo, titular: f.titular, numero: f.numero, banco: f.banco || undefined })
      setF(VACIO)
    })
  }

  return (
    <section>
      <TituloPagina titulo="Métodos de cobro" descripcion="Aquí recibes tus liquidaciones. Solo un método verificado y principal sirve para cobrar." />

      <FormGrid onSubmit={registrar}>
        <Field etiqueta="Tipo">
          <Select {...campo('tipo')}>
            <option value="CUENTA_BANCARIA">Cuenta bancaria</option>
            <option value="QR">QR</option>
            <option value="BILLETERA">Billetera</option>
          </Select>
        </Field>
        <Field etiqueta="Titular">
          <Input required {...campo('titular')} />
        </Field>
        <Field etiqueta="Banco">
          <Input {...campo('banco')} />
        </Field>
        <Field etiqueta="Número de cuenta">
          <Input required autoComplete="off" {...campo('numero')} />
        </Field>
        <Button disabled={accion.ocupado}>Registrar</Button>
      </FormGrid>
      <ResultadoAccion error={accion.error} exito={accion.exito} />

      <EstadoVista cargando={cargando} error={error} vacio={datos?.length === 0}>
        <Card className="overflow-x-auto">
          <table className="table">
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
                    <span className="font-medium">{m.titular}</span>
                    {m.es_principal && <span className="text-brand-primary"> · principal</span>}
                  </td>
                  <td>
                    {m.banco ? `${m.banco} ` : ''}•••• {m.ultimos_digitos ?? '----'}
                  </td>
                  <td>
                    <EstadoBadge valor={m.verificado ? 'VERIFICADO' : 'PENDIENTE'} />
                  </td>
                  <td className="text-right">
                    {!m.es_principal && (
                      <Button tamano="sm" variante="secondary" onClick={() => accion.correr('Ahora es el método principal', () => hacerPrincipal(m.metodo_id))}>
                        Hacer principal
                      </Button>
                    )}
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
