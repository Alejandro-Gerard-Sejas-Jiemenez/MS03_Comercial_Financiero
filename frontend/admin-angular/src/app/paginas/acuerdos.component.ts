import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, fecha, llamar, tono } from '../nucleo';

interface Acuerdo {
  acuerdo_id: string;
  anfitrion_id: string;
  estado: string;
  dias_liquidacion: number;
  fecha_inicio: string;
  fecha_fin: string | null;
}

/** Transiciones que el dominio permite desde cada estado. */
const SIGUIENTES: Record<string, string[]> = {
  BORRADOR: ['ACTIVO', 'RESCINDIDO'],
  ACTIVO: ['SUSPENDIDO', 'FINALIZADO', 'RESCINDIDO'],
  SUSPENDIDO: ['ACTIVO', 'FINALIZADO', 'RESCINDIDO'],
};

@Component({
  selector: 'app-acuerdos',
  imports: [FormsModule],
  template: `
    <h2>Acuerdos comerciales</h2>
    <form class="formulario" (ngSubmit)="crear()">
      <label>Anfitrión (id) <input name="anf" required placeholder="uuid del anfitrión" [(ngModel)]="f.anfitrion" /></label>
      <label>Inicio <input name="ini" type="date" required [(ngModel)]="f.inicio" /></label>
      <label>Días para liquidar <input name="dias" type="number" min="0" [(ngModel)]="f.dias" /></label>
      <button [disabled]="accion.ocupado()">Crear borrador</button>
    </form>
    <label class="motivo">Motivo (obligatorio para suspender, finalizar o rescindir)
      <input name="motivo" [(ngModel)]="motivo" placeholder="p. ej. incumplimiento de políticas" />
    </label>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }
    @if (accion.exito(); as e) { <p class="aviso ok">{{ e }}</p> }

    @if (acuerdos().length === 0) {
      <p class="suave">No hay acuerdos todavía.</p>
    } @else {
      <table>
        <thead><tr><th>Anfitrión</th><th>Inicio</th><th>Días</th><th>Estado</th><th>Cambiar a</th></tr></thead>
        <tbody>
          @for (a of acuerdos(); track a.acuerdo_id) {
            <tr>
              <td [title]="a.anfitrion_id">{{ a.anfitrion_id.slice(0, 8) }}…</td>
              <td>{{ fecha(a.fecha_inicio) }}</td>
              <td>{{ a.dias_liquidacion }}</td>
              <td><span class="insignia {{ tono(a.estado) }}">{{ a.estado }}</span></td>
              <td class="acciones">
                @for (s of siguientes(a.estado); track s) {
                  <button [class]="s === 'ACTIVO' ? '' : 'secundario'" [disabled]="accion.ocupado()" (click)="cambiar(a, s)">{{ s }}</button>
                }
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
})
export class AcuerdosComponent implements OnInit {
  readonly acuerdos = signal<Acuerdo[]>([]);
  readonly accion = new Accion(() => void this.cargar());
  readonly fecha = fecha;
  readonly tono = tono;
  f = { anfitrion: '', inicio: '', dias: 7 };
  motivo = '';

  ngOnInit(): void {
    void this.cargar();
  }

  siguientes(estado: string): string[] {
    return SIGUIENTES[estado] ?? [];
  }

  async cargar(): Promise<void> {
    try {
      this.acuerdos.set(await llamar<Acuerdo[]>('/acuerdos'));
    } catch (e) {
      this.accion.error.set((e as Error).message);
    }
  }

  crear(): void {
    void this.accion.correr('Borrador creado', () =>
      llamar('/acuerdos', { metodo: 'POST', cuerpo: { anfitrion_id: this.f.anfitrion, fecha_inicio: this.f.inicio, dias_liquidacion: Number(this.f.dias) } }),
    );
  }

  cambiar(a: Acuerdo, estado: string): void {
    void this.accion.correr(`Acuerdo ${estado.toLowerCase()}`, () =>
      llamar(`/acuerdos/${a.acuerdo_id}/estado`, { metodo: 'POST', cuerpo: { estado, ...(this.motivo ? { motivo: this.motivo } : {}) } }),
    );
  }
}
