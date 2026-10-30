import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { EstadoComponent } from '../ui/estado.component';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

interface Conciliacion {
  conciliacion_id: string;
  estado: string;
  fecha_corte: string;
  items?: { referencia_pasarela: string; resultado: string }[];
}

@Component({
  selector: 'app-conciliacion',
  imports: [FormsModule, EstadoComponent, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Conciliación con la pasarela" descripcion="Compara el reporte del día de la pasarela con nuestras transacciones. Las diferencias se revisan una por una." />

    <form class="form-grid" (ngSubmit)="ejecutar()">
      <label class="block"><span class="label">Fecha de corte (vacío = hoy)</span><input class="input" name="fecha" type="date" [(ngModel)]="fechaCorte" /></label>
      <button class="btn btn-primary" [disabled]="accion.ocupado()">Ejecutar conciliación</button>
    </form>
    <app-resultado [accion]="accion" />

    @if (resultado(); as c) {
      <div class="card mb-8 p-5">
        <dl class="grid grid-cols-[max-content_1fr] gap-x-8 gap-y-2.5 text-sm">
          <dt class="text-ink-secondary">Conciliación</dt><dd class="break-all">{{ c.conciliacion_id }}</dd>
          <dt class="text-ink-secondary">Estado</dt><dd><app-estado [valor]="c.estado" /></dd>
          <dt class="text-ink-secondary">Fecha de corte</dt><dd>{{ c.fecha_corte }}</dd>
        </dl>
        @if ((c.items ?? []).length > 0) {
          <div class="card mt-5 overflow-x-auto">
            <table class="table">
              <thead><tr><th>Referencia</th><th>Resultado</th></tr></thead>
              <tbody>
                @for (i of c.items; track i.referencia_pasarela) {
                  <tr><td>{{ i.referencia_pasarela }}</td><td>{{ i.resultado }}</td></tr>
                }
              </tbody>
            </table>
          </div>
        }
      </div>

      <h2 class="mb-3 text-lg font-semibold">Revisar una diferencia</h2>
      <form class="form-grid" (ngSubmit)="revisar(c)">
        <label class="block"><span class="label">Referencia de la pasarela</span><input class="input" name="ref" required [(ngModel)]="r.referencia" /></label>
        <label class="block"><span class="label">Observación</span><input class="input" name="obs" required [(ngModel)]="r.observacion" /></label>
        <label class="flex items-center gap-2.5 pb-2.5 text-sm font-medium">
          <input class="h-4 w-4 accent-brand-primary" name="cerrar" type="checkbox" [(ngModel)]="r.cerrar" />
          Cerrar la conciliación
        </label>
        <button class="btn btn-primary" [disabled]="accion.ocupado()">Registrar revisión</button>
      </form>
    }
  `,
})
export class ConciliacionComponent {
  readonly resultado = signal<Conciliacion | undefined>(undefined);
  readonly accion = new Accion();
  fechaCorte = '';
  r = { referencia: '', observacion: '', cerrar: false };

  ejecutar(): void {
    void this.accion.correr('Conciliación ejecutada', async () => {
      this.resultado.set(await llamar<Conciliacion>('/conciliaciones', { metodo: 'POST', cuerpo: this.fechaCorte ? { fecha_corte: this.fechaCorte } : {} }));
    });
  }

  revisar(c: Conciliacion): void {
    void this.accion.correr('Revisión registrada', async () => {
      this.resultado.set(
        await llamar<Conciliacion>(`/conciliaciones/${c.conciliacion_id}/revisiones`, {
          metodo: 'POST',
          cuerpo: { referencia_pasarela: this.r.referencia, observacion: this.r.observacion, cerrar: this.r.cerrar },
        }),
      );
    });
  }
}
