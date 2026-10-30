/** Estado de una accion que llama a la API: error, exito y ocupado. */
import { signal } from '@angular/core';

export class Accion {
  readonly error = signal<string | undefined>(undefined);
  readonly exito = signal<string | undefined>(undefined);
  readonly ocupado = signal(false);

  constructor(private readonly alTerminar?: () => void) {}

  async correr(textoExito: string, accion: () => Promise<unknown>): Promise<void> {
    this.ocupado.set(true);
    this.error.set(undefined);
    this.exito.set(undefined);
    try {
      await accion();
      this.exito.set(textoExito);
      this.alTerminar?.();
    } catch (e) {
      this.error.set((e as Error).message);
    } finally {
      this.ocupado.set(false);
    }
  }
}
