import { Component, input } from '@angular/core';
import { Accion } from '../core/accion';

/** Muestra el error o el exito de una accion (`Accion`), si los hay. */
@Component({
  selector: 'app-resultado',
  template: `
    @if (accion().error(); as e) { <p class="alerta alerta-error" role="alert">{{ e }}</p> }
    @if (accion().exito(); as e) { <p class="alerta alerta-ok" role="status">{{ e }}</p> }
  `,
})
export class ResultadoComponent {
  readonly accion = input.required<Accion>();
}
