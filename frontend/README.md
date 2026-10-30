# Microfrontends de MS-03

Arquitectura del documento del proyecto (sección 5): microfrontends integrados por un shell
`single-spa`, con un único login de Keycloak (SSO) y la API de MS-03 detrás.

| Carpeta | Tecnología | Rol | Ruta en el shell |
|---|---|---|---|
| `shell/` | single-spa + Vite | Login, rutas y montaje | `http://localhost:9000` |
| `portal-react/` | React + TypeScript | CLIENTE y ANFITRION | `/portal/*` |
| `admin-angular/` | Angular + TypeScript | ADMIN | `/admin/*` |

Falta (no es de MS-03): la app móvil React Native, el API Gateway y el GraphQL BFF.

## Levantar todo integrado

```bash
docker compose up -d            # API, PostgreSQL, RabbitMQ y Keycloak (con el realm importado)

# Una terminal por servicio:
cd frontend/portal-react  && npm install && npm run build:spa && npm run serve:spa   # :5174
cd frontend/admin-angular && npm install && npm run build:spa && npm run serve:spa   # :4201
cd frontend/shell         && npm install && npm run dev                              # :9000
```

Abrir `http://localhost:9000`: el shell pide login y lleva al administrador a `/admin` y al resto a `/portal`.

## Cada microfrontend por separado (desarrollo)

```bash
cd frontend/portal-react  && npm run dev     # http://localhost:5173, con su propio login
cd frontend/admin-angular && npm start       # http://localhost:4200, con su propio login
```

## Usuarios de prueba (realm `alojate`, clave `clave123`)

`admin1` (ADMIN) · `anfi1` (ANFITRION) · `cli1` (CLIENTE) · `ambos1` (CLIENTE y ANFITRION)

## Cómo se integran

Cada microfrontend exporta `bootstrap`, `mount` y `unmount` (`src/spa-entry.tsx` y `src/spa.ts`)
y se construye como un único módulo ES (`spa.js` / `main.js`). El shell los importa por URL
(`VITE_PORTAL_URL`, `VITE_ADMIN_URL`), les pasa el contenedor, su ruta base y la **sesión de
Keycloak** (`token()`, roles, usuario, salir), de modo que no vuelven a iniciar sesión.

## Backend

La API permite estos orígenes por CORS: `COMERCIAL_CORS_ORIGINS` (5173, 4200, 9000 y 3000 por omisión).
Documentación interactiva de la API: `http://localhost:8000/docs`.
