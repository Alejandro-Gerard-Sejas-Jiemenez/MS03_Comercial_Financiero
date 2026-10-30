import 'zone.js';
import { APP_BASE_HREF } from '@angular/common';
import { ApplicationRef } from '@angular/core';
import { bootstrapApplication } from '@angular/platform-browser';
import { AppComponent } from './app/app.component';
import { appConfig } from './app/app.config';
import { SesionExterna, usarSesionExterna } from './app/core/sesion';

/**
 * Punto de entrada para el shell single-spa: exporta el ciclo de vida en vez de arrancar solo.
 * Se construye con `npm run build:spa` (configuracion `spa`) y sale como un unico `spa.js`.
 */
interface Props {
  domElement: HTMLElement;
  sesion: SesionExterna;
  basename?: string;
}

let aplicacion: ApplicationRef | undefined;

export async function bootstrap(): Promise<void> {}

export async function mount(props: Props): Promise<void> {
  usarSesionExterna(props.sesion);
  props.domElement.replaceChildren(document.createElement('app-root'));
  aplicacion = await bootstrapApplication(AppComponent, {
    providers: [...appConfig.providers, { provide: APP_BASE_HREF, useValue: props.basename ?? '/admin' }],
  });
}

export async function unmount(): Promise<void> {
  aplicacion?.destroy();
  aplicacion = undefined;
}
