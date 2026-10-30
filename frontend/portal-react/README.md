# Portal de clientes y anfitriones (Microfrontend 1)

React + TypeScript (Vite) con Tailwind. Consume la API de MS-03 con el token de Keycloak. La paleta (azul `#2563EB`, tipografía Inter) y la organización de carpetas siguen el frontend de MS-01, para que todos los microfrontends se vean igual.

| Rol | Pantallas |
|---|---|
| CLIENTE | Mis pagos, Nuevo pago (cotiza y paga), Detalle (cobrar, cancelar, comprobante, pedir reembolso) |
| ANFITRION | Pagos recibidos, Promociones, Métodos de cobro, Liquidaciones y saldo |

```bash
cp .env.example .env     # opcional: direcciones de la API y de Keycloak
npm install
npm run dev              # http://localhost:5173
npm test                 # pruebas de formato de dinero
npm run build
```

Requiere la API en `http://localhost:8000` y Keycloak en `http://localhost:8080`
(`docker compose up -d`). Usuarios de prueba: `cli1`, `anfi1`, `ambos1`, clave `clave123`.
El dinero se maneja en centavos enteros; los Bs que escribe la persona se convierten con `aCentavos`.

## Estructura

```text
src/
├── api/             client.ts (llamadas con el token) y auth.ts (sesión de Keycloak o del shell)
├── app/             AppRouter.tsx: rutas por rol
├── components/
│   ├── layout/        Sidebar (menú lateral) y AppLayout
│   └── ui/            Button, Card, Badge, Field, Alert
├── hooks/           useCarga (consultas) y useAccion (acciones con resultado)
├── pages/           pagos/, promociones/, cobros/, liquidaciones/
├── services/        una función por llamada a la API (pagosApi, promocionesApi, ...)
├── types/           tipos de lo que devuelve la API
└── utils/           formato de dinero y fechas, cn
```

Los estilos están acotados a `.ms03-portal` y no se usa el reset global de Tailwind, porque el portal convive con el shell y con la administración en la misma página.

## Diseño responsivo

La navegación es un **menú lateral**, no un encabezado:

| Ancho | Comportamiento |
|---|---|
| 768 px o más (tablet y escritorio) | Menú lateral fijo a la izquierda con la persona y el botón *Salir* abajo; el contenido ocupa el resto |
| Menos de 768 px (móvil) | Barra superior con la marca y el botón de menú; el menú se abre como panel lateral y se cierra con la X, el fondo, Escape o al elegir una opción |

Los formularios pasan de 4 columnas a 2 y a 1 según el ancho, y las tablas se desplazan dentro de su tarjeta en lugar de ensanchar la página.
