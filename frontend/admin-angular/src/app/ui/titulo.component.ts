import { Component, input } from '@angular/core';

/** Titulo de pantalla con descripcion opcional y un espacio para botones a la derecha. */
@Component({
  selector: 'app-titulo',
  template: `
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">{{ titulo() }}</h1>
        @if (descripcion(); as d) { <p class="mt-1 text-sm text-ink-secondary">{{ d }}</p> }
      </div>
      <div class="flex items-center gap-3"><ng-content /></div>
    </div>
  `,
})
export class TituloComponent {
  readonly titulo = input.required<string>();
  readonly descripcion = input<string>();
}
