import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, llamar, tono } from '../nucleo';

interface Conciliacion {
  conciliacion_id: string;
  estado: string;
  fecha_corte: string;
  items?: { referencia_pasarela: string; resultado: string }[];
}

@Component({
  selector: 'app-conciliacion',
  imports: [FormsModule],
  template: `
    <h2>Conciliación con la pasarela</h2>
    <p class="suave">Compara el reporte del día de la pasarela con nuestras transacciones. Las diferencias se revisan una por una.</p>
    <form class="formulario" (ngSubmit)="ejecutar()">
      <label>Fecha de corte (vacío = hoy) <input name="fecha" type="date" [(ngModel)]="fechaCorte" /></label>
      <button [disabled]="accion.ocupado()">Ejecutar conciliación</button>
    </form>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }
    @if (accion.exito(); as e) { <p class="aviso ok">{{ e }}</p> }

    @if (resultado(); as c) {
      <div class="tarjeta">
        <dl>
          <dt>Conciliación</dt><dd>{{ c.conciliacion_id }}</dd>
          <dt>Estado</dt><dd><span class="insignia {{ tono(c.estado) }}">{{ c.estado }}</span></dd>
          <dt>Fecha de corte</dt><dd>{{ c.fecha_corte }}</dd>
        </dl>
        @if ((c.items ?? []).length > 0) {
          <table>
            <thead><tr><th>Referencia</th><th>Resultado</th></tr></thead>
            <tbody>
              @for (i of c.items; track i.referencia_pasarela) {
                <tr><td>{{ i.referencia_pasarela }}</td><td>{{ i.resultado }}</td></tr>
              }
            </tbody>
          </table>
        }
      </div>

      <h3>Revisar una diferencia</h3>
      <form class="formulario" (ngSubmit)="revisar(c)">
        <label>Referencia de la pasarela <input name="ref" required [(ngModel)]="r.referencia" /></label>
        <label>Observación <input name="obs" required [(ngModel)]="r.observacion" /></label>
        <label><span>Cerrar la conciliación</span><input name="cerrar" type="checkbox" [(ngModel)]="r.cerrar" /></label>
        <button [disabled]="accion.ocupado()">Registrar revisión</button>
      </form>
    }
  `,
})
export class ConciliacionComponent {
  readonly resultado = signal<Conciliacion | undefined>(undefined);
  readonly accion = new Accion();
  readonly tono = tono;
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
