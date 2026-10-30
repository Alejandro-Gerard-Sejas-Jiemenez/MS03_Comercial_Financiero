import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion } from '../core/accion';
import { llamar } from '../core/api';
import { aCentavos, dinero, Dinero, fecha } from '../core/formato';
import { EstadoComponent } from '../ui/estado.component';
import { ResultadoComponent } from '../ui/resultado.component';
import { TituloComponent } from '../ui/titulo.component';

interface Regla {
  regla_id: string;
  nombre: string;
  concepto: string;
  tipo_calculo: string;
  porcentaje: string | null;
  monto_fijo: Dinero | null;
  estado: string;
  vigente_desde: string;
  vigente_hasta: string | null;
}

@Component({
  selector: 'app-reglas',
  imports: [FormsModule, EstadoComponent, ResultadoComponent, TituloComponent],
  template: `
    <app-titulo titulo="Reglas de monetización" descripcion="La comisión al anfitrión y el cargo de servicio al cliente se calculan con estas reglas." />

    <form class="form-grid" (ngSubmit)="crear()">
      <label class="block"><span class="label">Nombre</span><input class="input" name="nombre" required [(ngModel)]="f.nombre" /></label>
      <label class="block"><span class="label">Concepto</span>
        <select class="input" name="concepto" [(ngModel)]="f.concepto">
          <option value="COMISION_ANFITRION">Comisión al anfitrión</option>
          <option value="CARGO_SERVICIO_CLIENTE">Cargo de servicio al cliente</option>
        </select>
      </label>
      <label class="block"><span class="label">Cálculo</span>
        <select class="input" name="tipo" [(ngModel)]="f.tipo_calculo">
          <option value="PORCENTAJE">Porcentaje (%)</option>
          <option value="MONTO_FIJO">Monto fijo (Bs)</option>
        </select>
      </label>
      <label class="block"><span class="label">{{ f.tipo_calculo === 'PORCENTAJE' ? 'Porcentaje' : 'Monto (Bs)' }}</span>
        <input class="input" name="valor" required inputmode="decimal" [(ngModel)]="f.valor" />
      </label>
      <label class="block"><span class="label">Vigente desde</span><input class="input" name="desde" type="date" required [(ngModel)]="f.desde" /></label>
      <button class="btn btn-primary" [disabled]="accion.ocupado()">Crear regla</button>
    </form>
    <app-resultado [accion]="accion" />

    @if (reglas().length === 0) {
      <p class="py-6 text-sm text-ink-secondary">No hay reglas todavía: sin ellas no se cobra comisión ni cargo de servicio.</p>
    } @else {
      <div class="card overflow-x-auto">
        <table class="table">
          <thead><tr><th>Nombre</th><th>Concepto</th><th>Valor</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            @for (r of reglas(); track r.regla_id) {
              <tr>
                <td class="font-medium">{{ r.nombre }}</td>
                <td>{{ r.concepto === 'COMISION_ANFITRION' ? 'Comisión' : 'Cargo de servicio' }}</td>
                <td>{{ r.tipo_calculo === 'PORCENTAJE' ? r.porcentaje + ' %' : dinero(r.monto_fijo) }}</td>
                <td>{{ fecha(r.vigente_desde) }} – {{ fecha(r.vigente_hasta) }}</td>
                <td><app-estado [valor]="r.estado" /></td>
                <td class="text-right">
                  @if (r.estado === 'ACTIVA') {
                    <button class="btn btn-danger btn-sm" [disabled]="accion.ocupado()" (click)="desactivar(r)">Desactivar</button>
                  }
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
})
export class ReglasComponent implements OnInit {
  readonly reglas = signal<Regla[]>([]);
  readonly accion = new Accion(() => void this.cargar());
  readonly dinero = dinero;
  readonly fecha = fecha;
  f = { nombre: '', concepto: 'COMISION_ANFITRION', tipo_calculo: 'PORCENTAJE', valor: '10', desde: '' };

  ngOnInit(): void {
    void this.cargar();
  }

  async cargar(): Promise<void> {
    try {
      this.reglas.set(await llamar<Regla[]>('/reglas-monetizacion'));
    } catch (e) {
      this.accion.error.set((e as Error).message);
    }
  }

  crear(): void {
    void this.accion.correr('Regla creada', () =>
      llamar('/reglas-monetizacion', {
        metodo: 'POST',
        cuerpo: {
          nombre: this.f.nombre,
          concepto: this.f.concepto,
          tipo_calculo: this.f.tipo_calculo,
          vigente_desde: this.f.desde,
          ...(this.f.tipo_calculo === 'PORCENTAJE' ? { porcentaje: this.f.valor } : { monto_fijo_centavos: aCentavos(this.f.valor) }),
        },
      }),
    );
  }

  desactivar(r: Regla): void {
    void this.accion.correr('Regla desactivada', () => llamar(`/reglas-monetizacion/${r.regla_id}`, { metodo: 'PUT', cuerpo: { estado: 'INACTIVA' } }));
  }
}
