import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { dinero, Dinero } from '../core/formato';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

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
  imports: [FormsModule, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Reportes de ingresos" />

    <form class="form-grid" (ngSubmit)="generar()">
      <label class="block"><span class="label">Desde</span><input class="input" name="desde" type="date" required [(ngModel)]="desde" /></label>
      <label class="block"><span class="label">Hasta</span><input class="input" name="hasta" type="date" required [(ngModel)]="hasta" /></label>
      <button class="btn btn-primary" [disabled]="accion.ocupado()">Generar</button>
    </form>
    <app-resultado [accion]="accion" />

    @if (resumen(); as r) {
      <div class="card mb-8 p-5">
        <h2 class="mb-4 text-lg font-semibold">Ingresos de la plataforma</h2>
        <dl class="grid grid-cols-[max-content_1fr] gap-x-8 gap-y-2.5 text-sm">
          @for (c of campos(r); track c[0]) {
            <dt class="text-ink-secondary capitalize">{{ c[0] }}</dt><dd>{{ c[1] }}</dd>
          }
        </dl>
      </div>
    }
    @if (categorias().length > 0) {
      <h2 class="mb-3 text-lg font-semibold">Por categoría de alojamiento</h2>
      <div class="card overflow-x-auto">
        <table class="table">
          <thead><tr><th>Categoría</th><th>Pagos</th><th>Total cobrado</th><th>Comisiones</th></tr></thead>
          <tbody>
            @for (c of categorias(); track $index) {
              <tr><td>{{ c.categoria_alojamiento ?? 'Sin categoría' }}</td><td>{{ c.cantidad_pagos }}</td><td>{{ dinero(c.total_cobrado) }}</td><td class="font-semibold">{{ dinero(c.total_comisiones) }}</td></tr>
            }
          </tbody>
        </table>
      </div>
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
