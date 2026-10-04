# Proposal: `post_views_count` saneado a entero no negativo

## Why

El contador `post_views_count` se sanea con un cierre que llama a `intval()`, que admite valores negativos. Una escritura REST autenticada (`edit_posts`) o cualquier `update_post_meta` podría dejar el contador por debajo de cero, rompiendo la semántica de «número de visitas» y el ordenamiento por esa columna. La auditoría dejó este endurecimiento como follow-up (SEC-BE-002).

## What Changes

- El `sanitize_callback` de `post_views_count` pasa a coercer el valor a un **entero no negativo**: un valor negativo SHALL persistirse como `0` y un valor no numérico SHALL persistirse como `0`. Se conserva la tolerancia a los cuatro argumentos con que `sanitize_meta()` invoca al callback (sin funciones internas de PHP como `intval` directamente).
- Sin cambios en el contrato REST: nombres de campo, tipos de post, capacidad de escritura (`edit_posts`) y lectura se conservan.

## Capabilities

### New Capabilities

- Ninguna.

### Modified Capabilities

- `rest-metafields`: se endurece el requisito «Saneado compatible con la firma de `sanitize_meta()`» para exigir un entero no negativo en `post_views_count`.

## Fuera de alcance

- El saneado del resto de metas públicas (`mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`) no cambia.
- El incremento desde `atareao_track_view` no cambia: ya no puede decrementar el contador.
- El incremento de `post_views_count` server-side y el dedupe (capability `anti-abuse`) no se tocan.

## Impact

- **Archivos a modificar (solo tras aprobación, en la fase TDD):**
  - `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php` — cierre `sanitize_callback` de `post_views_count` (`max(0, absint($value))`).
- **Nuevas specs al archivar:** ninguna; se actualiza `openspec/specs/rest-metafields/spec.md`.
- **Contratos que NO se tocan:** nombres de campos REST, tipos de post, claves de meta, `auth_callback`, contrato de lectura/escritura.
- **Verificación:** sin framework de tests. `just php-lint` (0 errores) + `just phpcs` (delta +0) + arnés externo de stubs (`/tmp/opencode/…`, no versionado) + E2E.
