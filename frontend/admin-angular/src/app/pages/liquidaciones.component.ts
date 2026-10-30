import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { dinero, Dinero, fecha } from '../core/formato';
import { EstadoComponent } from '../ui/estado.component';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

interface Liquidacion {
  liquidacion_id: string;
  estado: string;
  periodo_desde: string;
  periodo_hasta: string;
  total_bruto: Dinero;
  total_comisiones: Dinero;
  total_neto: Dinero;
}

@Component({
  selector: 'app-liquidaciones',
  imports: [FormsModule, EstadoComponent, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Liquidaciones" descripcion="Una liquidación se genera cada noche. Aquí se aprueba, se rechaza o se registra su pago." />

    <form class="form-grid" (ngSubmit)="buscar()">
      <label class="block sm:col-span-2"><span class="label">Anfitrión (id)</span><input class="input" name="anf" required placeholder="uuid del anfitrión" [(ngModel)]="anfitrion" /></label>
      <button class="btn btn-primary">Ver liquidaciones</button>
    </form>
    <div class="form-grid">
      <label class="block sm:col-span-1 lg:col-span-2"><span class="label">Motivo (para rechazar)</span><input class="input" name="motivo" [(ngModel)]="motivo" /></label>
      <label class="block sm:col-span-1 lg:col-span-2"><span class="label">Referencia de la transferencia (para pagar)</span><input class="input" name="ref" [(ngModel)]="referencia" /></label>
    </div>
    <app-resultado [accion]="accion" />

    @if (buscado() && liquidaciones().length === 0) {
      <p class="py-6 text-sm text-ink-secondary">Ese anfitrión no tiene liquidaciones.</p>
    }
    @if (liquidaciones().length > 0) {
      <div class="card overflow-x-auto">
        <table class="table">
          <thead><tr><th>Periodo</th><th>Bruto</th><th>Comisiones</th><th>Neto</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            @for (l of liquidaciones(); track l.liquidacion_id) {
              <tr>
                <td>{{ fecha(l.periodo_desde) }} – {{ fecha(l.periodo_hasta) }}</td>
                <td>{{ dinero(l.total_bruto) }}</td>
                <td>{{ dinero(l.total_comisiones) }}</td>
                <td class="font-semibold">{{ dinero(l.total_neto) }}</td>
                <td><app-estado [valor]="l.estado" /></td>
                <td>
                  <div class="flex flex-wrap justify-end gap-2">
                    @if (l.estado === 'GENERADA') {
                      <button class="btn btn-primary btn-sm" [disabled]="accion.ocupado()" (click)="aprobar(l)">Aprobar</button>
                      <button class="btn btn-danger btn-sm" [disabled]="accion.ocupado()" (click)="rechazar(l)">Rechazar</button>
                    }
                    @if (l.estado === 'APROBADA') {
                      <button class="btn btn-primary btn-sm" [disabled]="accion.ocupado()" (click)="pagar(l)">Registrar pago</button>
                    }
                  </div>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class LiquidacionesComponent {
  readonly liquidaciones = signal<Liquidacion[]>([]);
  readonly buscado = signal(false);
  readonly accion = new Accion(() => void this.cargar());
  readonly dinero = dinero;
  readonly fecha = fecha;
  anfitrion = '';
  motivo = '';
  referencia = '';

  buscar(): void {
    void this.cargar();
  }

  private async cargar(): Promise<void> {
    if (!this.anfitrion) return;
    try {
      this.liquidaciones.set(await llamar<Liquidacion[]>(`/anfitriones/${this.anfitrion}/liquidaciones`));
      this.buscado.set(true);
    } catch (e) {
      this.accion.error.set((e as Error).message);
    }
  }

  aprobar(l: Liquidacion): void {
    void this.accion.correr('Liquidación aprobada', () => llamar(`/liquidaciones/${l.liquidacion_id}/aprobacion`, { metodo: 'POST' }));
  }

  rechazar(l: Liquidacion): void {
    void this.accion.correr('Liquidación rechazada', () => llamar(`/liquidaciones/${l.liquidacion_id}/rechazo`, { metodo: 'POST', cuerpo: { motivo: this.motivo } }));
  }

  pagar(l: Liquidacion): void {
    void this.accion.correr('Pago registrado', () => llamar(`/liquidaciones/${l.liquidacion_id}/pago`, { metodo: 'POST', cuerpo: { referencia_transferencia: this.referencia } }));
  }
}
