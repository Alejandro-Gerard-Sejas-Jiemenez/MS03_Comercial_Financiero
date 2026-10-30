# Portal de clientes y anfitriones (Microfrontend 1)

React + TypeScript (Vite). Consume la API de MS-03 con el token de Keycloak.

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
