import { ApplicationConfig, provideAppInitializer, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';

import { routes } from './app.routes';
import { iniciarSesion } from './nucleo';

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    // La app no arranca hasta que Keycloak confirma la sesion.
    provideAppInitializer(() => iniciarSesion()),
  ],
};
