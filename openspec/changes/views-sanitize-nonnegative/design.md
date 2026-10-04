# Design: `post_views_count` no negativo

## Context

`class-metaboxes.php` registra `post_views_count` con un cierre que hoy hace `return intval($value);`. El cierre ya resuelve el `ArgumentCountError` de `sanitize_meta()` (que invoca con 4 argumentos). Falta la cota inferior.

## Decision

Sustituir el cuerpo del cierre por `return max(0, absint($value));`:

- `absint()` es la función de WordPress que convierte a entero no negativo (`abs(intval($value))`), por lo que ya garantiza la cota; `max(0, …)` es redundante pero explícito y fiel al follow-up SEC-BE-002.
- El cierre sigue tolerando los 4 argumentos (no declara más parámetros y PHP los ignora).
- `absint` está disponible en el runtime de WordPress; el arnés de stubs la define.

## Alternatives considered

- `intval` + comprobación manual de signo: equivalente pero más código.
- Registrar `type => 'integer'` confiando en core sin saneado: no acota el signo.

## Verification

Arnés externo de stubs (`/tmp/opencode/`, no versionado): `sanitize_meta('post','post_views_count', -7, ...)` → `0`; `'abc'` → `0`; `5` → `5`; y la guardia de que un `sanitize_callback` igual a `intval` produce `ArgumentCountError`. Estáticos: `just php-lint`, `just phpcs` (delta +0). E2E: escritura REST con `edit_posts` de un valor negativo persiste `0`.
