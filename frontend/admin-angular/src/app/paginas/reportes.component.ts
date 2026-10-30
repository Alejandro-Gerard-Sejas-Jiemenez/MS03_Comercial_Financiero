import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, dinero, Dinero, llamar } from '../nucleo';

interface Resumen {
  comisiones?: Dinero;
  cargos_servicio?: Dinero;
  total?: Dinero;
  [clave: string]: unknown;
}

interface PorCategoria {
  categoria_alojamiento: string | null;
  total_cobrado: Dinero;
  total_comisiones: Dinero;
  cantidad_pagos: number;
}

@Component({
  selector: 'app-reportes',
  imports: [FormsModule],
  template: `
    <h2>Reportes de ingresos</h2>
    <form class="formulario" (ngSubmit)="generar()">
      <label>Desde <input name="desde" type="date" required [(ngModel)]="desde" /></label>
      <label>Hasta <input name="hasta" type="date" required [(ngModel)]="hasta" /></label>
      <button [disabled]="accion.ocupado()">Generar</button>
    </form>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }

    @if (resumen(); as r) {
      <div class="tarjeta">
        <h3>Ingresos de la plataforma</h3>
        <dl>
          @for (c of campos(r); track c[0]) {
            <dt>{{ c[0] }}</dt><dd>{{ c[1] }}</dd>
          }
        </dl>
      </div>
    }
    @if (categorias().length > 0) {
      <h3>Por categoría de alojamiento</h3>
      <table>
        <thead><tr><th>Categoría</th><th>Pagos</th><th>Total cobrado</th><th>Comisiones</th></tr></thead>
        <tbody>
          @for (c of categorias(); track $index) {
            <tr><td>{{ c.categoria_alojamiento ?? 'Sin categoría' }}</td><td>{{ c.cantidad_pagos }}</td><td>{{ dinero(c.total_cobrado) }}</td><td><strong>{{ dinero(c.total_comisiones) }}</strong></td></tr>
          }
        </tbody>
      </table>
    }
  `,
})
export class ReportesComponent {
  readonly resumen = signal<Resumen | undefined>(undefined);
  readonly categorias = signal<PorCategoria[]>([]);
  readonly accion = new Accion();
  readonly dinero = dinero;
  desde = '';
  hasta = '';

  generar(): void {
    const q = `desde=${this.desde}&hasta=${this.hasta}`;
    void this.accion.correr('', async () => {
      this.resumen.set(await llamar<Resumen>(`/reportes/ingresos?${q}`));
      this.categorias.set(await llamar<PorCategoria[]>(`/reportes/ingresos-por-categoria?${q}`));
    });
  }

  /** El resumen trae dinero y datos sueltos: se muestra cada campo legible, sin asumir su forma exacta. */
  campos(r: Resumen): [string, string][] {
    return Object.entries(r).map(([k, v]) => [
      k.replaceAll('_', ' '),
      v && typeof v === 'object' && 'centavos' in v ? dinero(v as Dinero) : String(v ?? '—'),
    ]);
  }
}
