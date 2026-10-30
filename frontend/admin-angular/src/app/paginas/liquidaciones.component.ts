import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, dinero, Dinero, fecha, llamar, tono } from '../nucleo';

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
  imports: [FormsModule],
  template: `
    <h2>Liquidaciones</h2>
    <p class="suave">Una liquidación se genera cada noche. Aquí se aprueba, se rechaza o se registra su pago.</p>
    <form class="formulario" (ngSubmit)="buscar()">
      <label>Anfitrión (id) <input name="anf" required placeholder="uuid del anfitrión" [(ngModel)]="anfitrion" /></label>
      <button>Ver liquidaciones</button>
    </form>
    <div class="formulario">
      <label>Motivo (para rechazar) <input name="motivo" [(ngModel)]="motivo" /></label>
      <label>Referencia de la transferencia (para pagar) <input name="ref" [(ngModel)]="referencia" /></label>
    </div>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }
    @if (accion.exito(); as e) { <p class="aviso ok">{{ e }}</p> }

    @if (buscado() && liquidaciones().length === 0) {
      <p class="suave">Ese anfitrión no tiene liquidaciones.</p>
    }
    @if (liquidaciones().length > 0) {
      <table>
        <thead><tr><th>Periodo</th><th>Bruto</th><th>Comisiones</th><th>Neto</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          @for (l of liquidaciones(); track l.liquidacion_id) {
            <tr>
              <td>{{ fecha(l.periodo_desde) }} – {{ fecha(l.periodo_hasta) }}</td>
              <td>{{ dinero(l.total_bruto) }}</td>
              <td>{{ dinero(l.total_comisiones) }}</td>
              <td><strong>{{ dinero(l.total_neto) }}</strong></td>
              <td><span class="insignia {{ tono(l.estado) }}">{{ l.estado }}</span></td>
              <td class="acciones">
                @if (l.estado === 'GENERADA') {
                  <button [disabled]="accion.ocupado()" (click)="aprobar(l)">Aprobar</button>
                  <button class="peligro" [disabled]="accion.ocupado()" (click)="rechazar(l)">Rechazar</button>
                }
                @if (l.estado === 'APROBADA') {
                  <button [disabled]="accion.ocupado()" (click)="pagar(l)">Registrar pago</button>
                }
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
})
export class LiquidacionesComponent {
  readonly liquidaciones = signal<Liquidacion[]>([]);
  readonly buscado = signal(false);
  readonly accion = new Accion(() => void this.cargar());
  readonly dinero = dinero;
  readonly fecha = fecha;
  readonly tono = tono;
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
