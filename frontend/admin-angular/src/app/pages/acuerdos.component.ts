import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { fecha } from '../core/formato';
import { EstadoComponent } from '../ui/estado.component';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

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
  imports: [FormsModule, EstadoComponent, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Acuerdos comerciales" />

    <form class="form-grid" (ngSubmit)="crear()">
      <label class="block"><span class="label">Anfitrión (id)</span><input class="input" name="anf" required placeholder="uuid del anfitrión" [(ngModel)]="f.anfitrion" /></label>
      <label class="block"><span class="label">Inicio</span><input class="input" name="ini" type="date" required [(ngModel)]="f.inicio" /></label>
      <label class="block"><span class="label">Días para liquidar</span><input class="input" name="dias" type="number" min="0" [(ngModel)]="f.dias" /></label>
      <button class="btn btn-primary" [disabled]="accion.ocupado()">Crear borrador</button>
    </form>

    <label class="mb-4 block max-w-xl">
      <span class="label">Motivo (obligatorio para suspender, finalizar o rescindir)</span>
      <input class="input" name="motivo" [(ngModel)]="motivo" placeholder="p. ej. incumplimiento de políticas" />
    </label>
    <app-resultado [accion]="accion" />

    @if (acuerdos().length === 0) {
      <p class="py-6 text-sm text-ink-secondary">No hay acuerdos todavía.</p>
    } @else {
      <div class="card overflow-x-auto">
        <table class="table">
          <thead><tr><th>Anfitrión</th><th>Inicio</th><th>Días</th><th>Estado</th><th>Cambiar a</th></tr></thead>
          <tbody>
            @for (a of acuerdos(); track a.acuerdo_id) {
              <tr>
                <td [title]="a.anfitrion_id">{{ a.anfitrion_id.slice(0, 8) }}…</td>
                <td>{{ fecha(a.fecha_inicio) }}</td>
                <td>{{ a.dias_liquidacion }}</td>
                <td><app-estado [valor]="a.estado" /></td>
                <td>
                  <div class="flex flex-wrap gap-2">
                    @for (s of siguientes(a.estado); track s) {
                      <button class="btn btn-sm" [class]="s === 'ACTIVO' ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-secondary'" [disabled]="accion.ocupado()" (click)="cambiar(a, s)">{{ s }}</button>
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
export class AcuerdosComponent implements OnInit {
  readonly acuerdos = signal<Acuerdo[]>([]);
  readonly accion = new Accion(() => void this.cargar());
  readonly fecha = fecha;
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
      llamar(`/acuerdos/${a.acuerdo_id}`, { metodo: 'PUT', cuerpo: { estado, ...(this.motivo ? { motivo: this.motivo } : {}) } }),
    );
  }
}
