import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'reglas' },
  { path: 'reglas', loadComponent: () => import('./paginas/reglas.component').then((m) => m.ReglasComponent) },
  { path: 'acuerdos', loadComponent: () => import('./paginas/acuerdos.component').then((m) => m.AcuerdosComponent) },
  { path: 'pagos', loadComponent: () => import('./paginas/pagos.component').then((m) => m.PagosComponent) },
  { path: 'conciliacion', loadComponent: () => import('./paginas/conciliacion.component').then((m) => m.ConciliacionComponent) },
  { path: 'liquidaciones', loadComponent: () => import('./paginas/liquidaciones.component').then((m) => m.LiquidacionesComponent) },
  { path: 'reportes', loadComponent: () => import('./paginas/reportes.component').then((m) => m.ReportesComponent) },
  { path: '**', redirectTo: 'reglas' },
];
