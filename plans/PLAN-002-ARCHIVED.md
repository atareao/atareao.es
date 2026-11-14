# PLAN-002 — Aprovisionar el usuario de servicio de Valet desde la UI

- **Issue:** #175
- **Change OpenSpec:** `valet-reply-user-provisioning`
- **Rama de trabajo:** `fix/valet-reply-user-provisioning` (desde `development`)
- **Estado:** completado y archivado (2026-11-14)

> **Verificación:** `just test` → `Total passed: 1208, Total failed: 0` (exit 0);
> `openspec validate --all` → `38 passed, 0 failed`; change archivado como
> `2026-11-14-valet-reply-user-provisioning`; requisito fusionado en
> `openspec/specs/valet-notifications/spec.md`; `docs/valet-reply-user-provisioning.md` creado.

## 1. Objetivo

Eliminar el fallo silencioso de la vía de respuesta de Valet: hoy `atareao_valet_reply_user` se aprovisiona a mano (WP-CLI) y, si se olvida, `isServiceRequest()` es falso y el filtro `rest_endpoints` elimina `/wp/v2/comments`, devolviendo `404 rest_no_route` sin aviso. Se aprovisiona desde Ajustes → Valet y se avisa cuando la vía queda inerte.

## 2. Contexto

- `ValetConfig::getServiceUserId()` lee `get_option('atareao_valet_reply_user', 0)` (default `0`).
- `ValetConfig::isServiceRequest()` compara `get_current_user_id()` con ese ID; falla a cerrado.
- `atareao-functionality.php:150-214` elimina la colección `/wp/v2/comments` si `!isServiceRequest()`.
- `ValetConfig::renderConfigPage()` (`includes/class-valet-config.php:509-606`) sólo gestiona `atareao_valet_url` y `atareao_valet_token`, con nonce `atareao_valet_config` y `manage_options`.
- El plugin no usa `admin_notices`; imprime avisos inline (`notice notice-warning`).
- `tests/wp-stubs.php` NO tiene stubs de `get_users`.

## 3. Tareas (TDD)

- [x] 3.1 RED — añadir en `tests/wp-stubs.php` los stubs que falten (`get_users`); escribir en `tests/test-valet.php` los 5 escenarios del change.
- [x] 3.2 GREEN — implementar en `ValetConfig::renderConfigPage()`: `<select>` de usuarios + opción «— Ninguno —» (valor 0), guardado con `absint` (degradar a `0` si el ID no existe), y aviso `notice notice-warning` cuando URL+token están configurados y el usuario de servicio es `0 `.
- [x] 3.3 REFACTOR — PSR12 (`just phpcs` sobre los ficheros tocados) y re-ejecutar el harness.
- [x] 3.4 Verificar `just test` en verde y `openspec validate`.
- [x] 3.5 Archivar el change (`openspec archive valet-reply-user-provisioning`) y actualizar `openspec/specs/valet-notifications/spec.md `.
- [x] 3.6 Crear `docs/valet-reply-user-provisioning.md`.
- [x] 3.7 PR a `development` y cierre del issue #175.

## 4. Criterios de aceptación

- El usuario de servicio se aprovisiona desde Ajustes → Valet sin WP-CLI, y el filtro conserva el `POST` para esa identidad.
- Con `atareao_valet_reply_user = 0` y URL/token configurados aparece un aviso en el panel.
- El comportamiento por defecto sigue fallando a cerrado (sin usuario de servicio, colección eliminada).
- `just test` en verde; `openspec validate` en verde.

## 5. Riesgos

- Un `<select>` con todos los usuarios puede ser pesado en sitios grandes; aquí el censo es reducido. Alternativa: campo numérico + validación. Decisión actual: `<select>`.