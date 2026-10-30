import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { sesion } from './core/sesion';
import { LayoutComponent } from './layout/layout.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, LayoutComponent],
  template: `
    <div class="ms03-admin">
      @if (sesion.esAdmin()) {
        <app-layout><router-outlet /></app-layout>
      } @else {
        <main class="mx-auto max-w-xl p-6">
          <p class="alerta alerta-error" role="alert">Tu usuario no tiene el rol ADMIN. Los clientes y anfitriones usan el otro portal.</p>
        </main>
      }
    </div>
  `,
})
export class AppComponent {
  readonly sesion = sesion;
}
