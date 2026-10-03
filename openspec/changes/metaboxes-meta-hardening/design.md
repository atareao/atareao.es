# Design: Registro REST de metadatos endurecido y `seo_description` de solo lectura

## Context

La auditoría de seguridad de 2026-10-03 dejó `registerMetaFields()` fuera de alcance en `rest-blocks-hardening` (SEC-BE-002b) y anotó la divulgación de `_genesis_description` por el campo `seo_description`. La motivación está en `proposal.md`; aquí se resume el estado actual con evidencia fichero:línea y las restricciones que condicionan el enfoque. Todo el trabajo vive en `wp-content/plugins/atareao-functionality/includes/class-metaboxes.php`.

| ID | Fichero:línea | Estado actual |
|---|---|---|
| ME-01 | `class-metaboxes.php:89-198` | `registerMetaFields()` define un campo REST `metadata` con claves curadas (`:91-107`) y registra post meta con `register_post_meta()`. **No está enganchado**: `init()` (`:20-40`) no lo añade a ningún hook. |
| ME-02 | `class-metaboxes.php:165-197` | `$app_types = array('application','software')` y a continuación `register_post_meta($app_types, '_download_url', …)`, `…('_repository_url', …)`, `…('_version', …)`. Core usa `$post_type` como clave de array → **`TypeError` fatal** («Cannot access offset of type array on array») en cada petición si se activa. Los tipos múltiples deben iterarse. |
| ME-03 | `class-metaboxes.php:166-197` | `_download_url`, `_repository_url` y `_version` declaran `show_in_rest => true`. Son metas internas (prefijo `_`) y quedarían divulgadas por REST. |
| ME-04 | `class-metaboxes.php:109-163` | Las metas públicas (`mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`) declaran `show_in_rest => true`. Solo `mp3-url` y `_download_url`/`_repository_url`/`_version` declaran `auth_callback`; las demás quedan con el `auth_callback` por defecto de core (`__return_true`), lo que abre la escritura REST a cualquier petición que pueda editar el post. |
| SEO-01 | `class-metaboxes.php:63-83` | `seo_description` se registra en `post`, `page`, `podcast`, `capitulo`, `tutorial`, `aplicacion`, `application` y `software`. `get_callback` devuelve `get_post_meta($id, '_genesis_description', true)` sin sanear; `update_callback` escribe `_genesis_description` con `sanitize_text_field`. `register_rest_field()` no declara `auth_callback`. |

**Restricciones del repo.** WordPress sobre PHP 8.3, PSR12, **sin framework de tests ni build tools**. La verificación es `just php-lint` (0 errores) + `just phpcs` (baseline theme+plugin: **752 errores / 429 warnings**, objetivo delta +0), un **arnés externo de stubs** en `/tmp/opencode/metaboxes-meta-harness/` (fuera del repo, no versionado) y E2E manual del usuario. No se renombra ni se borra ningún campo REST, tipo de post ni clave de meta. La implementación solo arranca tras la aprobación del change.

**Capability afectada.** `rest-metafields` no existe en `openspec/specs/` y se **crea** como capability nueva con su `## Purpose` en el delta. La capability `metaboxes` (delta en curso de `rest-blocks-hardening`) no se modifica aquí.

## Goals / Non-Goals

**Goals:**

- Activar `registerMetaFields()` sin el `TypeError` fatal que hoy lo hace inviable.
- Dejar de divulgar las metas internas `_download_url`, `_repository_url` y `_version` por REST.
- Exigir capacidad `edit_posts` para escribir las metas públicas vía REST, conservando la lectura.
- Acotar el campo `metadata` a claves públicas.
- Reducir `seo_description` a solo lectura saneada.
- Verificar todo sin framework de tests: lint, PSR12 con delta +0, arnés externo de stubs y E2E manual.

**Non-Goals:**

- No se cambia la política global de acceso REST ni se añade autenticación restrictiva a la lectura pública.
- No se renombran ni se eliminan campos REST, tipos de post ni claves de meta.
- No se toca el guardado de metaboxes (`saveMetaboxes`, `render*Metabox`) ni el bloque de podcast.
- No se introducen dependencias, build tools ni framework de tests.
- No se edita la spec `metaboxes` del change `rest-blocks-hardening`.

## Decisions

### Decisión 1 (ME-02): iterar los tipos en lugar de pasar un array

La causa del fatal es `register_post_meta($app_types, …)` con `$post_types` array. La corrección mínima es iterar: `foreach ($app_types as $t) { register_post_meta($t, …); }` para cada meta `_`, exactamente como ya se hace con `post_views_count` (`:154-163`). Así cada llamada recibe un string y el registro se completa.

**Consecuencias:** el método puede engancharse en `init` sin fatal. El arnés reproduce el `TypeError` si se volviera a pasar un array, como guardia de regresión.

**Alternativa descartada:** borrar el método y no registrar nada. Elimina el bug pero también capacidades que el editor puede aprovechar; el change opta por activar de forma segura.

### Decisión 2 (ME-03): `show_in_rest => false` para las metas `_`

Las metas con prefijo `_` son internas por convención de WordPress. Se registran con `show_in_rest => false`, de modo que ni las peticiones anónimas ni las autenticadas las devuelven. Se conserva su `sanitize_callback` y su `auth_callback` (por si en el futuro se usan en admin), pero dejan de ser superficie REST.

**Consecuencias:** `_download_url`, `_repository_url` y `_version` dejan de aparecer en las respuestas REST. Los metaboxes de admin las siguen gestionando.

**Alternativa descartada:** mantener `show_in_rest => true` y confiar en `auth_callback`. `auth_callback` gobierna la escritura; la lectura quedaría expuesta, que es justo el hallazgo.

### Decisión 3 (ME-04): `auth_callback` explícito en las metas públicas

Las metas públicas declaran `auth_callback` que exige `edit_posts` (el mismo criterio que ya usa `mp3-url`). Esto cubre la escritura REST. La lectura sigue el contrato vigente (metas no protegidas, `show_in_rest => true`). `post_views_count` es la más sensible porque el resto de metas no la declaraban.

**Consecuencias:** una petición sin `edit_posts` no puede modificar `post_views_count` ni el resto de metas públicas vía REST. Los editores legítimos no se ven afectados.

**Alternativa descartada:** `show_in_rest => false` para todas. Cierra la escritura pero elimina la lectura por REST de datos que el editor y el cliente MCP sí consumen.

### Decisión 4 (SEO-01): `seo_description` de solo lectura y saneado en salida

Se retira el `update_callback`, de modo que la escritura REST queda deshabilitada; la escritura de `_genesis_description` se hace desde el editor o el plugin de SEO. El `get_callback` sanea el valor con `sanitize_text_field` (o equivalente) en la salida. Es un cambio **BREAKING** de la escritura REST, sin consumidores conocidos.

**Consecuencias:** el campo sigue disponible para lectura (incluido el cliente MCP) y deja de ser un vector de escritura de una meta protegida. El nombre del campo y los tipos no cambian.

**Alternativas descartadas:** (a) mantener `update_callback`: conserva la escritura REST de una meta protegida sin un control explícito; (b) eliminar el campo: perdería la descripción SEO para consumidores de solo lectura.

### Decisión 5: capability nueva `rest-metafields` en lugar de reutilizar `metaboxes`

La spec `metaboxes` aún no existe en `openspec/specs/` (vive como delta en `rest-blocks-hardening`, pendiente de archivar). Modificarla obligaría a ordenar los archives y a copiar headers que todavía no existen. Se crea una capability nueva, autocontenida y sin dependencia de ese archive, que cubre el registro REST de metadatos y el campo `seo_description`.

**Consecuencias:** `openspec/specs/rest-metafields/spec.md` se crea al archivar este change. El requisito histórico de `metaboxes` («fuera de alcance») queda como nota de aquel change; si resulta ambiguo al consolidar, se limpia en un change posterior.

**Alternativa descartada:** declarar `metaboxes` como capability nueva en dos changes en vuelo: arriesga colisión de capability al archivar.

## Risks / Trade-offs

- **ME-01/ME-02 — activar el método introduce nueva superficie:** engancharlo hace públicos (lectura) los metadatos y habilita escritura REST. → Mitigación: metas `_` con `show_in_rest => false`, metas públicas con `auth_callback`, `metadata` acotado, `seo_description` de solo lectura; el arnés verifica cada control.
- **ME-03 — consumidores que leían metas `_` por REST:** hoy no están activas, así que no hay consumidor; si algún cliente las esperaba, dejará de verlas. → Mitigación: es el objetivo de seguridad; se documenta.
- **SEO-01 — BREAKING en la escritura:** un cliente que escribiera `seo_description` por REST dejará de poder. → Mitigación: no hay consumidores conocidos; la escritura se hace en el editor/plugin de SEO.
- **Activación en `init`:** hookear el registro podría alterar el orden de hooks. → Mitigación: `register_post_meta` en `init` es práctica estándar; verificar el ciclo real con el arnés (prioridad posterior a la del bootstrap, como en `theme-options`).
- **Verificación sin framework:** no hay tests automatizados en repo. → Mitigación: `just php-lint` + `just phpcs` (delta +0), arnés externo de stubs en `/tmp/opencode/metaboxes-meta-harness/` con `TOTAL/PASS/FAIL` y E2E manual.

## Migration Plan

No hay migración de datos: no se renombran ni borran campos, tipos ni metas. El despliegue es un cambio de código; el rollback es revertir el commit. Los metaboxes de admin siguen guardando las metas `_` con normalidad. No se requiere `search-replace` ni limpieza.

## Open Questions

Ninguna. El conjunto curado de `metadata` reutiliza el de `all_metadata` (`mp3-url`, `number`, `season`, `post_views_count`); no cambia el spec, que exige «conjunto curado sin claves protegidas».
