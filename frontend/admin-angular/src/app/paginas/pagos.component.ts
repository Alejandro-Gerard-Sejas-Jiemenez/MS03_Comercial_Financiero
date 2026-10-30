import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, dinero, Dinero, llamar, tono, totalDe } from '../nucleo';

interface Pago {
  pago_id: string;
  reserva_id: string;
  cliente_id: string;
  estado: string;
  metodo: string;
  subtotal: Dinero;
  descuento: Dinero;
  cargo_servicio: Dinero;
}

interface Reembolso {
  reembolso_id: string;
  estado: string;
  monto: Dinero;
}

@Component({
  selector: 'app-pagos',
  imports: [FormsModule],
  template: `
    <header class="titulo">
      <h2>Pagos y reembolsos</h2>
      <select name="estado" [(ngModel)]="estado" (ngModelChange)="cargar()">
        <option value="">Todos los estados</option>
        @for (e of estados; track e) { <option [value]="e">{{ e }}</option> }
      </select>
    </header>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }
    @if (accion.exito(); as e) { <p class="aviso ok">{{ e }}</p> }

    @if (pagos().length === 0) {
      <p class="suave">No hay pagos con ese filtro.</p>
    } @else {
      <table>
        <thead><tr><th>Pago</th><th>Reserva</th><th>Método</th><th>Total</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          @for (p of pagos(); track p.pago_id) {
            <tr>
              <td>{{ p.pago_id.slice(0, 8) }}…</td>
              <td [title]="p.reserva_id">{{ p.reserva_id.slice(0, 8) }}…</td>
              <td>{{ p.metodo }}</td>
              <td>{{ dinero(totalDe(p)) }}</td>
              <td><span class="insignia {{ tono(p.estado) }}">{{ p.estado }}</span></td>
              <td><button class="secundario" (click)="verReembolsos(p)">Reembolsos</button></td>
            </tr>
            @if (abierto() === p.pago_id) {
              <tr>
                <td colspan="6">
                  @if (cargandoReembolsos()) {
                    <span class="suave">Cargando…</span>
                  } @else if (reembolsos().length === 0) {
                    <span class="suave">Este pago no tiene reembolsos.</span>
                  }
                  @for (r of reembolsos(); track r.reembolso_id) {
                    <div class="fila-reembolso">
                      <span>{{ r.reembolso_id.slice(0, 8) }}… · {{ dinero(r.monto) }}</span>
                      <span class="insignia {{ tono(r.estado) }}">{{ r.estado }}</span>
                      @if (r.estado === 'SOLICITADO') {
                        <button [disabled]="accion.ocupado()" (click)="revisar(r, true)">Aprobar</button>
                        <button class="peligro" [disabled]="accion.ocupado()" (click)="revisar(r, false)">Rechazar</button>
                      }
                      @if (r.estado === 'APROBADO') {
                        <button [disabled]="accion.ocupado()" (click)="procesar(r)">Procesar devolución</button>
                      }
                    </div>
                  }
                </td>
              </tr>
            }
          }
        </tbody>
      </table>
    }
  `,
  styles: `.fila-reembolso { display: flex; gap: 12px; align-items: center; padding: 6px 0; }`,
})
export class PagosComponent implements OnInit {
  readonly pagos = signal<Pago[]>([]);
  readonly abierto = signal<string | undefined>(undefined);
  readonly reembolsos = signal<Reembolso[]>([]);
  readonly cargandoReembolsos = signal(false);
  // Tras una accion se refresca el listado (el estado del pago cambia) y los reembolsos abiertos.
  readonly accion = new Accion(() => {
    void this.cargar();
    void this.recargarAbierto();
  });
  readonly dinero = dinero;
  readonly totalDe = totalDe;
  readonly tono = tono;
  readonly estados = ['PENDIENTE', 'CAPTURADO', 'FALLIDO', 'CANCELADO', 'REEMBOLSADO_PARCIAL', 'REEMBOLSADO'];
  estado = '';

  ngOnInit(): void {
    void this.cargar();
  }

  async cargar(): Promise<void> {
    try {
      this.pagos.set(await llamar<Pago[]>(`/pagos${this.estado ? `?estado=${this.estado}` : ''}`));
    } catch (e) {
      this.accion.error.set((e as Error).message);
    }
  }

  async verReembolsos(p: Pago): Promise<void> {
    this.abierto.set(this.abierto() === p.pago_id ? undefined : p.pago_id);
    this.reembolsos.set([]);
    this.cargandoReembolsos.set(true);
    await this.recargarAbierto();
  }

  private async recargarAbierto(): Promise<void> {
    const id = this.abierto();
    if (!id) return;
    try {
      this.reembolsos.set(await llamar<Reembolso[]>(`/pagos/${id}/reembolsos`));
    } catch (e) {
      this.accion.error.set((e as Error).message);
    } finally {
      this.cargandoReembolsos.set(false);
    }
  }

  revisar(r: Reembolso, aprobado: boolean): void {
    void this.accion.correr(aprobado ? 'Reembolso aprobado' : 'Reembolso rechazado', () =>
      llamar(`/reembolsos/${r.reembolso_id}/revision`, { metodo: 'POST', cuerpo: { aprobado, detalle: aprobado ? 'Aprobado por administración' : 'Rechazado por administración' } }),
    );
  }

  procesar(r: Reembolso): void {
    void this.accion.correr('Devolución procesada con la pasarela', () => llamar(`/reembolsos/${r.reembolso_id}/procesamiento`, { metodo: 'POST', idempotente: true }));
  }
}
