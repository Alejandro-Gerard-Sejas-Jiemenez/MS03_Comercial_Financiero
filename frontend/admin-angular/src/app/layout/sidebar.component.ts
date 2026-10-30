import { Component, output } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { ChartColumn, CreditCard, Handshake, Landmark, LogOut, LucideAngularModule, Percent, Scale, Wallet } from 'lucide-angular';
import { sesion } from '../core/sesion';

/** Marca de la aplicacion; se usa en el menu y en la barra superior del movil. */
@Component({
  selector: 'app-marca',
  imports: [LucideAngularModule],
  template: `
    <div class="flex items-center gap-2.5">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-primary text-white">
        <lucide-icon [img]="iconos.Wallet" [size]="20" />
      </span>
      <span class="leading-tight">
        <span class="block text-lg font-bold tracking-tight text-ink-primary">Aloja-Te</span>
        <span class="block text-xs font-medium text-ink-secondary">Administración comercial</span>
      </span>
    </div>
  `,
})
export class MarcaComponent {
  readonly iconos = { Wallet };
}

/** Menu lateral: navegacion arriba y la persona con su boton de salir abajo. */
@Component({
  selector: 'app-sidebar',
  imports: [RouterLink, RouterLinkActive, LucideAngularModule, MarcaComponent],
  template: `
    <div class="flex h-full flex-col">
      <div class="hidden border-b border-line px-5 py-5 md:block"><app-marca /></div>

      <nav aria-label="Principal" class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        @for (e of enlaces; track e.ruta) {
          <a
            [routerLink]="e.ruta"
            routerLinkActive="!bg-brand-light !text-brand-primary"
            (click)="navegado.emit()"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-ink-secondary transition-colors hover:bg-surface-secondary hover:text-ink-primary"
          >
            <lucide-icon [img]="e.icono" [size]="18" class="shrink-0" />
            {{ e.texto }}
          </a>
        }
      </nav>

      <div class="border-t border-line p-4">
        <p class="truncate text-sm font-semibold text-ink-primary">{{ sesion.nombre() || 'Usuario' }}</p>
        <p class="mb-3 truncate text-xs text-ink-secondary">ADMIN</p>
        <button class="btn btn-secondary w-full" (click)="sesion.salir()">
          <lucide-icon [img]="iconos.LogOut" [size]="16" />
          Salir
        </button>
      </div>
    </div>
  `,
})
export class SidebarComponent {
  readonly navegado = output<void>();
  readonly sesion = sesion;
  readonly iconos = { LogOut };
  readonly enlaces = [
    { ruta: '/reglas', texto: 'Reglas de monetización', icono: Percent },
    { ruta: '/acuerdos', texto: 'Acuerdos', icono: Handshake },
    { ruta: '/pagos', texto: 'Pagos y reembolsos', icono: CreditCard },
    { ruta: '/conciliacion', texto: 'Conciliación', icono: Scale },
    { ruta: '/liquidaciones', texto: 'Liquidaciones', icono: Landmark },
    { ruta: '/reportes', texto: 'Reportes', icono: ChartColumn },
  ];
}
