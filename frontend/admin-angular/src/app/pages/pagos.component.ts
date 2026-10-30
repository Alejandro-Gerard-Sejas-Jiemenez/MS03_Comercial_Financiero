import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { dinero, Dinero, totalDe } from '../core/formato';
import { EstadoComponent } from '../ui/estado.component';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

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
  imports: [FormsModule, EstadoComponent, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Pagos y reembolsos">
      <select class="input" name="estado" aria-label="Filtrar por estado" [(ngModel)]="estado" (ngModelChange)="cargar()">
        <option value="">Todos los estados</option>
        @for (e of estados; track e) { <option [value]="e">{{ e }}</option> }
      </select>
    </app-titulo>
    <app-resultado [accion]="accion" />

    @if (pagos().length === 0) {
      <p class="py-6 text-sm text-ink-secondary">No hay pagos con ese filtro.</p>
    } @else {
      <div class="card overflow-x-auto">
        <table class="table">
          <thead><tr><th>Pago</th><th>Reserva</th><th>Método</th><th>Total</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            @for (p of pagos(); track p.pago_id) {
              <tr>
                <td>{{ p.pago_id.slice(0, 8) }}…</td>
                <td [title]="p.reserva_id">{{ p.reserva_id.slice(0, 8) }}…</td>
                <td>{{ p.metodo }}</td>
                <td class="font-medium">{{ dinero(totalDe(p)) }}</td>
                <td><app-estado [valor]="p.estado" /></td>
                <td class="text-right"><button class="btn btn-secondary btn-sm" (click)="verReembolsos(p)">Reembolsos</button></td>
              </tr>
              @if (abierto() === p.pago_id) {
                <tr>
                  <td colspan="6" class="bg-surface-secondary">
                    @if (cargandoReembolsos()) {
                      <span class="text-ink-secondary">Cargando…</span>
                    } @else if (reembolsos().length === 0) {
                      <span class="text-ink-secondary">Este pago no tiene reembolsos.</span>
                    }
                    @for (r of reembolsos(); track r.reembolso_id) {
                      <div class="flex flex-wrap items-center gap-3 py-1.5">
                        <span>{{ r.reembolso_id.slice(0, 8) }}… · {{ dinero(r.monto) }}</span>
                        <app-estado [valor]="r.estado" />
                        @if (r.estado === 'SOLICITADO') {
                          <button class="btn btn-primary btn-sm" [disabled]="accion.ocupado()" (click)="revisar(r, true)">Aprobar</button>
                          <button class="btn btn-danger btn-sm" [disabled]="accion.ocupado()" (click)="revisar(r, false)">Rechazar</button>
                        }
                        @if (r.estado === 'APROBADO') {
                          <button class="btn btn-primary btn-sm" [disabled]="accion.ocupado()" (click)="procesar(r)">Procesar devolución</button>
                        }
                      </div>
                    }
                  </td>
                </tr>
              }
            }
          </tbody>
        </table>
      </div>
    }
  `,
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
      // El detalle del pago ya trae sus reembolsos (no hay ruta aparte).
      this.reembolsos.set((await llamar<{ reembolsos: Reembolso[] }>(`/pagos/${id}`)).reembolsos);
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
