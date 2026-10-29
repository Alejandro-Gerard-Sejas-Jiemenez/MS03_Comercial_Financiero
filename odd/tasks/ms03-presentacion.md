# MS-03 Presentacion (REST, mensajeria, JOBs)

Objetivo: implementar la capa Presentation sobre el backend existente, plan P1->P5 de `CLAUDE.md`.
Rama: main (crear rama de feature antes del primer commit) · Entrega: ask-on-risk · Runner: `php vendor/bin/phpunit`

## Specs

- **S1** — Usuario: "Excepciones tipadas en Domain (la más limpia, pero toca Domain)". Criterio: existen subclases de `DomainException` por categoria (no encontrado, conflicto, validacion, no autorizado); las 149 `throw new DomainException` se migran por paquete sin romper tests; mensajes sin cambio.
- **S2** — Usuario: "tabla de nueva migracion". Criterio: migracion 011 con tabla de idempotencia (clave, hash del body, respuesta, status). Misma clave + mismo body = misma respuesta; misma clave + body distinto = 422.
- **S3** — Usuario: "Opción A: Keycloak (JWT estándar)". Criterio: `KeycloakJwtAutenticador` valida firma (JWKS), `exp`, `iss`, `aud`; tests con claves generadas; Keycloak en docker-compose para desarrollo.
- **S4** — Presentation no contiene logica de negocio; Application/Domain intactos salvo S1.
- **S5** — Cada ruta con test feature y entrada en OpenAPI.
- **S6** — Cada paso cierra con `php vendor/bin/phpunit` en verde.

## Tasks

| ID | S# | Ruta | Estado | Evidencia |
| --- | --- | --- | --- | --- |
| T1 | S1 | inline | hecho | 4 excepciones tipadas en `src/Shared/Domain/Exception/` (heredan de `DomainException`); 148 throws migrados (28 no encontrado, 38 conflicto, 80 regla, 2 no permitido) en 42 archivos, incluidos Application/Handlers (necesario para mapear a 404/409). 104 tests siguen verdes |
| T2 | S2 | inline | hecho | migracion `2026_10_29_000011` (`idempotencia_http`, PK clave+ruta); ejercitada en sqlite por IdempotenciaTest. No corrida contra PostgreSQL |
| T3 | S3,S4 | inline | hecho | `KeycloakJwtAutenticador` + puerto + `Sesion`; firebase/php-jwt; config `keycloak`; Keycloak en docker-compose. 4 tests |
| T4 | S4,S5 | inline | hecho | middleware `sesion`/`rol`/`idempotente`, `ManejadorDeExcepciones`, `GET /api/v1/salud`, cableado en provider y bootstrap. 112 tests, 405 aserciones, 0 fallos |
| T5 | S5 | inline | pendiente | P2 REST por paquete |
| T6 | S5 | inline | pendiente | P3 OpenAPI |
| T7 | S4 | inline | pendiente | P4 mensajeria |
| T8 | S6 | inline | pendiente | P5 cierre |

## Log

**L1 — 2026-10-29, solicitud original:** analizar el proyecto contra `MS03_HANDOFF_CLAUDE_CODE.md` y analizar el plan de implementacion.
**L2 — 2026-10-29, decisiones del usuario (verbatim):** "Excepciones tipadas en Domain (la más limpia, pero toca Domain)"; "tabla de nueva migracion"; "Opción A: Keycloak (JWT estándar). La recomiendo."
Analisis: baseline 248 tests, 15 omitidos (PG). El codigo lanza 149 `DomainException` genericas, 7 `InvalidArgumentException`, 1 `RuntimeException`; sin tipado no se puede mapear a 404/409/422.
Justificacion de tocar Domain (regla 3): subclases de `DomainException` son retrocompatibles, los tests que esperan `DomainException` siguen pasando.

**L3 — 2026-10-29:** el usuario pidio reducir los tests a menos de la mitad. 248 -> 104 tests (363 aserciones), verde; se borraron 111 metodos redundantes y los 35 casos del contenedor se colapsaron en 2 tests. Tambien se elimino `test_no_hay_capa_de_presentacion_todavia`, que contradice P1. Baseline nuevo: 104.

**L4 — 2026-10-29, P1 ejecutado de un saque por indicacion del usuario.** Tests nuevos: 8 (104 -> 112). `pint` se corrio sin querer sobre todo el proyecto; se revirtio el formato en los archivos que no tenian otro cambio, quedan ajustes de estilo menores en archivos ya tocados. Pendiente: idempotencia no verificada en PostgreSQL real; Keycloak no levantado (realm/cliente/roles por crear a mano); sin commit.
