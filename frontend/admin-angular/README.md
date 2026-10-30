# Administración comercial (Microfrontend 2)

Angular 19 + TypeScript. Solo para el rol **ADMIN**; consume la API de MS-03 con el token de Keycloak.

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
