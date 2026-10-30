import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'reglas' },
  { path: 'reglas', loadComponent: () => import('./pages/reglas.component').then((m) => m.ReglasComponent) },
  { path: 'acuerdos', loadComponent: () => import('./pages/acuerdos.component').then((m) => m.AcuerdosComponent) },
  { path: 'pagos', loadComponent: () => import('./pages/pagos.component').then((m) => m.PagosComponent) },
  { path: 'conciliacion', loadComponent: () => import('./pages/conciliacion.component').then((m) => m.ConciliacionComponent) },
  { path: 'liquidaciones', loadComponent: () => import('./pages/liquidaciones.component').then((m) => m.LiquidacionesComponent) },
  { path: 'reportes', loadComponent: () => import('./pages/reportes.component').then((m) => m.ReportesComponent) },
  { path: '**', redirectTo: 'reglas' },
];
