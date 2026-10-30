import { token } from '@/api/auth'
import { API_URL } from '@/config'

/** Error de la API: siempre `{codigo, mensaje}`. */
export class ApiError extends Error {
  readonly status: number
  readonly codigo: string

  constructor(status: number, codigo: string, mensaje: string) {
    super(mensaje)
    this.status = status
    this.codigo = codigo
  }
}

interface Opciones {
  metodo?: 'GET' | 'POST' | 'PUT'
  cuerpo?: unknown
  /** Las operaciones de dinero exigen Idempotency-Key: se genera una por intento. */
  idempotente?: boolean
}

export async function llamar<T>(ruta: string, { metodo = 'GET', cuerpo, idempotente }: Opciones = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    Authorization: `Bearer ${await token()}`,
  }
  if (cuerpo !== undefined) headers['Content-Type'] = 'application/json'
  if (idempotente) headers['Idempotency-Key'] = crypto.randomUUID()

  const res = await fetch(`${API_URL}${ruta}`, {
    method: metodo,
    headers,
    body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo),
  })

  const texto = await res.text()
  const json = texto ? (JSON.parse(texto) as Record<string, unknown>) : {}
  if (!res.ok) {
    throw new ApiError(res.status, String(json.codigo ?? 'error'), String(json.mensaje ?? `Error ${res.status}`))
  }
  // Las respuestas exitosas vienen envueltas en {data}.
  return (json.data ?? json) as T
}
