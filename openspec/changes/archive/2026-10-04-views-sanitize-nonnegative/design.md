# Design: `post_views_count` no negativo

## Context

`class-metaboxes.php` registra `post_views_count` con un cierre que hoy hace `return intval($value);`. El cierre ya resuelve el `ArgumentCountError` de `sanitize_meta()` (que invoca con 4 argumentos). Falta la cota inferior.

## Decision

Sustituir el cuerpo del cierre por `return max(0, intval($value));`:

- `intval()` convierte a entero (y devuelve 0 para no numéricos); `max(0, …)` colapsa los negativos a 0. Se usa `intval` y no `absint` porque `absint(-7)` es 7 (valor absoluto) y NO cumple «negativo → 0».
- El cierre sigue tolerando los 4 argumentos (no declara más parámetros y PHP los ignora).

## Alternatives considered

- `intval` + comprobación manual de signo: equivalente pero más código.
- Registrar `type => 'integer'` confiando en core sin saneado: no acota el signo.

## Verification

Arnés externo de stubs (`/tmp/opencode/`, no versionado): `sanitize_meta('post','post_views_count', -7, ...)` → `0`; `'abc'` → `0`; `5` → `5`; y la guardia de que un `sanitize_callback` igual a `intval` produce `ArgumentCountError`. Estáticos: `just php-lint`, `just phpcs` (delta +0). E2E: escritura REST con `edit_posts` de un valor negativo persiste `0`.
