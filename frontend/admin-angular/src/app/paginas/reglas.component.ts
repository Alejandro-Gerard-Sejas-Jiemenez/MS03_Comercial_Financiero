import { Component, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Accion, aCentavos, dinero, Dinero, fecha, llamar, tono } from '../nucleo';

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
  imports: [FormsModule],
  template: `
    <h2>Reglas de monetización</h2>
    <p class="suave">La comisión al anfitrión y el cargo de servicio al cliente se calculan con estas reglas.</p>
    <form class="formulario" (ngSubmit)="crear()">
      <label>Nombre <input name="nombre" required [(ngModel)]="f.nombre" /></label>
      <label>Concepto
        <select name="concepto" [(ngModel)]="f.concepto">
          <option value="COMISION_ANFITRION">Comisión al anfitrión</option>
          <option value="CARGO_SERVICIO_CLIENTE">Cargo de servicio al cliente</option>
        </select>
      </label>
      <label>Cálculo
        <select name="tipo" [(ngModel)]="f.tipo_calculo">
          <option value="PORCENTAJE">Porcentaje (%)</option>
          <option value="MONTO_FIJO">Monto fijo (Bs)</option>
        </select>
      </label>
      <label>{{ f.tipo_calculo === 'PORCENTAJE' ? 'Porcentaje' : 'Monto (Bs)' }}
        <input name="valor" required inputmode="decimal" [(ngModel)]="f.valor" />
      </label>
      <label>Vigente desde <input name="desde" type="date" required [(ngModel)]="f.desde" /></label>
      <button [disabled]="accion.ocupado()">Crear regla</button>
    </form>
    @if (accion.error(); as e) { <p class="aviso error">{{ e }}</p> }
    @if (accion.exito(); as e) { <p class="aviso ok">{{ e }}</p> }

    @if (reglas().length === 0) {
      <p class="suave">No hay reglas todavía: sin ellas no se cobra comisión ni cargo de servicio.</p>
    } @else {
      <table>
        <thead><tr><th>Nombre</th><th>Concepto</th><th>Valor</th><th>Vigencia</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          @for (r of reglas(); track r.regla_id) {
            <tr>
              <td>{{ r.nombre }}</td>
              <td>{{ r.concepto === 'COMISION_ANFITRION' ? 'Comisión' : 'Cargo de servicio' }}</td>
              <td>{{ r.tipo_calculo === 'PORCENTAJE' ? r.porcentaje + ' %' : dinero(r.monto_fijo) }}</td>
              <td>{{ fecha(r.vigente_desde) }} – {{ fecha(r.vigente_hasta) }}</td>
              <td><span class="insignia {{ tono(r.estado) }}">{{ r.estado }}</span></td>
              <td>
                @if (r.estado === 'ACTIVA') {
                  <button class="peligro" [disabled]="accion.ocupado()" (click)="desactivar(r)">Desactivar</button>
                }
              </td>
            </tr>
          }
        </tbody>
      </table>
    }
  `,
})
export class ReglasComponent implements OnInit {
  readonly reglas = signal<Regla[]>([]);
  readonly accion = new Accion(() => void this.cargar());
  readonly dinero = dinero;
  readonly fecha = fecha;
  readonly tono = tono;
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
    void this.accion.correr('Regla desactivada', () => llamar(`/reglas-monetizacion/${r.regla_id}/desactivacion`, { metodo: 'POST' }));
  }
}
