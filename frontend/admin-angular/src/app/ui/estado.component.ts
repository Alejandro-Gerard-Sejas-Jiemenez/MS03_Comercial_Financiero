import { Component, computed, input } from '@angular/core';
import { tono } from '../core/estado';

/** Insignia de un estado de la API: el color sale del significado del estado. */
@Component({
  selector: 'app-estado',
  template: `<span class="badge badge-{{ clase() }}">{{ texto() }}</span>`,
})
export class EstadoComponent {
  readonly valor = input<string | undefined>();
  readonly clase = computed(() => tono(this.valor()));
  readonly texto = computed(() => (this.valor() ?? '—').replaceAll('_', ' '));
}
