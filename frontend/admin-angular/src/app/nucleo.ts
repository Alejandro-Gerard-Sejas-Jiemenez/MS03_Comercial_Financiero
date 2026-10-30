import { signal } from '@angular/core';
import Keycloak from 'keycloak-js';

/** Direcciones del entorno. Para otro entorno se reemplaza este archivo al construir. */
export const API_URL = 'http://localhost:8000/api/v1';
export const KEYCLOAK = { url: 'http://localhost:8080', realm: 'alojate', clientId: 'ms03-comercial-financiero' };

// ---------- Sesion (Keycloak) ----------
const keycloak = new Keycloak(KEYCLOAK);

export async function iniciarSesion(): Promise<void> {
  await keycloak.init({ onLoad: 'login-required', pkceMethod: 'S256', checkLoginIframe: false });
}

export const sesion = {
  nombre: () => (keycloak.tokenParsed as { preferred_username?: string } | undefined)?.preferred_username ?? '',
  esAdmin: () => (keycloak.tokenParsed?.realm_access?.roles ?? []).includes('ADMIN'),
  salir: () => void keycloak.logout({ redirectUri: window.location.origin }),
};

async function token(): Promise<string> {
  await keycloak.updateToken(30);
  return keycloak.token ?? '';
}

// ---------- API ----------
export class ApiError extends Error {
  constructor(readonly status: number, readonly codigo: string, mensaje: string) {
    super(mensaje);
  }
}

export interface Opciones {
  metodo?: 'GET' | 'POST' | 'PATCH' | 'PUT';
  cuerpo?: unknown;
  /** Las operaciones de dinero exigen Idempotency-Key: se genera una por intento. */
  idempotente?: boolean;
}

export async function llamar<T>(ruta: string, { metodo = 'GET', cuerpo, idempotente }: Opciones = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', Authorization: `Bearer ${await token()}` };
  if (cuerpo !== undefined) headers['Content-Type'] = 'application/json';
  if (idempotente) headers['Idempotency-Key'] = crypto.randomUUID();

  const res = await fetch(`${API_URL}${ruta}`, { method: metodo, headers, body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo) });
  const texto = await res.text();
  const json = (texto ? JSON.parse(texto) : {}) as Record<string, unknown>;
  if (!res.ok) throw new ApiError(res.status, String(json['codigo'] ?? 'error'), String(json['mensaje'] ?? `Error ${res.status}`));
  return (json['data'] ?? json) as T;
}

// ---------- Dinero y fechas ----------
export interface Dinero {
  centavos: number;
  moneda: string;
  monto: string;
}

export function dinero(d?: Dinero | null): string {
  if (!d) return '—';
  return `${d.moneda} ${(d.centavos / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

/** Bs escritos por la persona ("1250.50") a centavos enteros, sin coma flotante. */
export function aCentavos(texto: string): number {
  const limpio = texto.trim().replace(',', '.');
  if (!/^\d+(\.\d{1,2})?$/.test(limpio)) throw new Error('Monto invalido: use numeros con hasta 2 decimales');
  const [enteros, decimales = ''] = limpio.split('.');
  return Number(enteros) * 100 + Number(decimales.padEnd(2, '0'));
}

/**
 * La API manda fechas como `2026-01-01T00:00:00+00:00`. Se lee el dia del propio texto: pasarlo por `Date`
 * lo corre un dia hacia atras en zonas al oeste de UTC (Bolivia es UTC-4).
 */
export function fecha(iso?: string | null): string {
  if (!iso) return '—';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
}

export function totalDe(p: { subtotal?: Dinero; descuento?: Dinero; cargo_servicio?: Dinero }): Dinero | undefined {
  if (!p.subtotal) return undefined;
  const centavos = p.subtotal.centavos - (p.descuento?.centavos ?? 0) + (p.cargo_servicio?.centavos ?? 0);
  return { centavos, moneda: p.subtotal.moneda, monto: (centavos / 100).toFixed(2) };
}

// ---------- Estado de una accion (error / exito / ocupado) ----------
export class Accion {
  readonly error = signal<string | undefined>(undefined);
  readonly exito = signal<string | undefined>(undefined);
  readonly ocupado = signal(false);

  constructor(private readonly alTerminar?: () => void) {}

  async correr(textoExito: string, accion: () => Promise<unknown>): Promise<void> {
    this.ocupado.set(true);
    this.error.set(undefined);
    this.exito.set(undefined);
    try {
      await accion();
      this.exito.set(textoExito);
      this.alTerminar?.();
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.ocupado.set(false);
    }
  }
}

/** Clase CSS de una insignia segun el estado. */
export function tono(estado?: string): string {
  const v = estado ?? '';
  if (/CAPTURADO|ACTIV|APROBAD|PAGAD|VERIFICAD|COMPLETAD|EXITOSA/.test(v)) return 'ok';
  if (/PENDIENTE|BORRADOR|GENERAD|SOLICITAD|PAUSAD|RETENID|SUSPENDID/.test(v)) return 'espera';
  if (/FALLIDO|CANCEL|RECHAZ|ANULAD|VENCID|RESCINDID|INACTIV/.test(v)) return 'mal';
  return 'neutro';
}
