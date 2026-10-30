import { bootstrapApplication } from '@angular/platform-browser';
import { provideAppInitializer } from '@angular/core';
import { appConfig } from './app/app.config';
import { AppComponent } from './app/app.component';
import { iniciarSesion } from './app/core/sesion';

// Modo independiente: la app pide su propio login a Keycloak antes de arrancar.
bootstrapApplication(AppComponent, {
  providers: [...appConfig.providers, provideAppInitializer(() => iniciarSesion())],
}).catch((err) => console.error(err));
