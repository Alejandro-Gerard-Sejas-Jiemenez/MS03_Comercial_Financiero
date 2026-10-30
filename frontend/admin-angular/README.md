# Administración comercial (Microfrontend 2)

Angular 19 + TypeScript con Tailwind. Misma paleta, tipografía y menú lateral que `portal-react` y el frontend de MS-01. Solo para el rol **ADMIN**; consume la API de MS-03 con el token de Keycloak.

| Pantalla | Qué hace |
|---|---|
| Reglas de monetización | Crear y desactivar reglas de comisión y de cargo de servicio |
| Acuerdos | Crear borradores y cambiar de estado (activar, suspender, finalizar, rescindir) |
| Pagos y reembolsos | Filtrar pagos; aprobar, rechazar y procesar reembolsos |
| Conciliación | Ejecutar la conciliación con la pasarela y revisar diferencias |
| Liquidaciones | Aprobar, rechazar y registrar el pago de las liquidaciones de un anfitrión |
| Reportes | Ingresos de la plataforma y por categoría de alojamiento |

```bash
npm install
npm start        # http://localhost:4200
npm run build
```

Requiere la API en `http://localhost:8000` y Keycloak en `http://localhost:8080`
(`docker compose up -d`). Usuario de prueba: `admin1`, clave `clave123`. Las direcciones están
en `src/app/nucleo.ts`.

## Estructura

```text
src/app/
├── core/       api.ts, sesion.ts (Keycloak o shell), formato.ts, accion.ts, estado.ts
├── layout/     menú lateral (sidebar) y diseño responsivo
├── ui/         estado (insignia), resultado (error/éxito) y título de pantalla
└── pages/      una pantalla por componente (reglas, acuerdos, pagos, conciliación, liquidaciones, reportes)
```

## Diseño responsivo

La navegación es un **menú lateral**: fijo a la izquierda desde 768 px; en móvil, una barra superior con el botón de menú abre un panel que se cierra con la X, el fondo, Escape o al elegir una opción. Los formularios pasan de 4 columnas a 2 y a 1, y las tablas se desplazan dentro de su tarjeta. Los estilos están acotados a `.ms03-admin` y no se usa el reset global de Tailwind, porque la administración convive con el shell y con el portal.
