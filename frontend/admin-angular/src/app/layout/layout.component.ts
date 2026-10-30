import { Component, HostListener, signal } from '@angular/core';
import { LucideAngularModule, Menu, X } from 'lucide-angular';
import { MarcaComponent, SidebarComponent } from './sidebar.component';

/**
 * Escritorio (md y mas): menu lateral fijo a la izquierda y contenido a la derecha.
 * Movil: barra superior con el boton del menu; el menu se abre como panel lateral.
 */
@Component({
  selector: 'app-layout',
  imports: [LucideAngularModule, MarcaComponent, SidebarComponent],
  template: `
    <div class="min-h-screen bg-surface-secondary md:flex">
      <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface-base px-4 md:hidden">
        <app-marca />
        <button class="btn btn-ghost !p-2" aria-label="Abrir menú" [attr.aria-expanded]="abierto()" (click)="abierto.set(true)">
          <lucide-icon [img]="iconos.Menu" [size]="20" />
        </button>
      </header>

      @if (abierto()) {
        <div class="fixed inset-0 z-40 bg-ink-primary/40 md:hidden" (click)="abierto.set(false)" aria-hidden="true"></div>
      }

      <aside
        class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] border-r border-line bg-surface-base transition-transform duration-200 md:sticky md:top-0 md:z-auto md:h-screen md:w-64 md:max-w-none md:shrink-0 md:translate-x-0"
        [class.translate-x-0]="abierto()"
        [class.shadow-cardHover]="abierto()"
        [class.-translate-x-full]="!abierto()"
      >
        <button class="btn btn-ghost absolute right-2 top-2 !p-2 md:hidden" aria-label="Cerrar menú" (click)="abierto.set(false)">
          <lucide-icon [img]="iconos.X" [size]="20" />
        </button>
        <app-sidebar (navegado)="abierto.set(false)" />
      </aside>

      <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
        <div class="mx-auto max-w-6xl"><ng-content /></div>
      </main>
    </div>
  `,
})
export class LayoutComponent {
  readonly abierto = signal(false);
  readonly iconos = { Menu, X };

  @HostListener('window:keydown.escape')
  cerrar(): void {
    this.abierto.set(false);
  }
}
