import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { sesion } from './nucleo';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <header class="barra">
      <strong>Alójate · Administración comercial</strong>
      <nav>
        @for (e of enlaces; track e.ruta) {
          <a [routerLink]="e.ruta" routerLinkActive="active">{{ e.texto }}</a>
        }
      </nav>
      <span class="usuario">
        {{ sesion.nombre() }} · ADMIN
        <button class="secundario" (click)="sesion.salir()">Salir</button>
      </span>
    </header>
    <main>
      @if (sesion.esAdmin()) {
        <router-outlet />
      } @else {
        <p class="aviso error">Tu usuario no tiene el rol ADMIN. Los clientes y anfitriones usan el otro portal.</p>
      }
    </main>
  `,
})
export class AppComponent {
  readonly sesion = sesion;
  readonly enlaces = [
    { ruta: '/reglas', texto: 'Reglas de monetización' },
    { ruta: '/acuerdos', texto: 'Acuerdos' },
    { ruta: '/pagos', texto: 'Pagos y reembolsos' },
    { ruta: '/conciliacion', texto: 'Conciliación' },
    { ruta: '/liquidaciones', texto: 'Liquidaciones' },
    { ruta: '/reportes', texto: 'Reportes' },
  ];
}
