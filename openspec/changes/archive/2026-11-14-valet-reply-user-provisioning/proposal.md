# Proposal: Aprovisionamiento del usuario de servicio de Valet desde Ajustes

## Why

Hoy la opción `atareao_valet_reply_user` —el **identificador del usuario de servicio** del asistente— se aprovisiona **a mano** con `just wp -- option update atareao_valet_reply_user 42`, un procedimiento documentado en el README del plugin pero fácil de **olvidar**.

El olvido es **silencioso**: sin esa opción, `ValetConfig::isServiceRequest()` es siempre falso y `atareao_functionality_disable_rest_comment_endpoint()` elimina `unset($endpoints['/wp/v2/comments'])` de la tabla REST. El consumidor Valet recibe entonces `404 rest_no_route` al intentar publicar una respuesta, sin ninguna señal en el sitio que explique **por qué**: la URL y el token de Valet están configurados, la colección está cerrada y no hay pista de que falte una tercera pieza. El fallo a cerrado es correcto por seguridad, pero la ausencia de **aviso** convierte un error de configuración trivial en una avería opaca.

Referencia: issue **#175**.

## What Changes

**1. Campo de selección del usuario de servicio en la pestaña «Valet».**

`ValetConfig::renderConfigPage()` pasa a presentar un `<select>` con los usuarios del sitio (`get_users`) más una opción «— Ninguno —» con valor `0`. Al guardar, el valor se valida con `absint` y se persiste en la opción `atareao_valet_reply_user`; un valor que no corresponda a un usuario existente se **degrada a `0`** (no se acepta basura). El guardado reutiliza el **nonce** `atareao_valet_config`, el campo `atareao_valet_save` y la capacidad `manage_options` **ya existentes**: no se introduce ningún mecanismo de guardado nuevo.

**2. Aviso visible cuando la vía de respuesta queda inerte.**

Cuando `atareao_valet_url` y `atareao_valet_token` están configurados y `atareao_valet_reply_user` es `0`, la página muestra un aviso `notice notice-warning` indicando que la vía de respuesta está inerte. El aviso desaparece en cuanto el usuario de servicio es válido (`>0`).

**3. Se conserva el fallo a cerrado.**

El comportamiento por defecto **no cambia**: sin usuario de servicio (opción `0`), la colección `/wp/v2/comments` **sigue eliminada** para todos los peticionarios. Este change solo añade el aprovisionamiento desde la UI y el aviso; no relaja ninguna garantía de la capability `valet-replies`.

## Capabilities

### Modified Capabilities

- **`valet-notifications`** — un **requisito nuevo** (`ADDED`) sobre el aprovisionamiento del usuario de servicio desde la pestaña «Valet». No se modifica ningún requisito existente: el requisito «Ajustes de Valet y guardado por POST» describe el guardado de URL y token, y este requisito nuevo es de **otra materia** (identidad de servicio y su aviso), por lo que se **añade** en lugar de reescribir aquel.

## Impact

### Ficheros afectados (previsibles)

- `wp-content/plugins/atareao-functionality/includes/class-valet-config.php` — **el grueso del change**: el `<select>` de usuarios, la lectura/validación con `absint` y la persistencia de `atareao_valet_reply_user` en `renderConfigPage()`, más el aviso `notice notice-warning` cuando la vía de respuesta queda inerte. Reutiliza el nonce, el campo de guardado y la capacidad ya existentes.
- `tests/test-valet.php` — los cinco escenarios nuevos del requisito.
- `tests/wp-stubs.php` — **hay que añadir los stubs que falten**, en particular `get_users` (y `wp_get_current_user` si el escenario lo requiere) para que el arnés pueda modelar el `<select>` y el aprovisionamiento.
- `openspec/specs/valet-notifications/spec.md` — se actualiza **al archivar**, por propagación del delta (no se edita a mano ahora).

### Alcance (no-goals)

- **No se implementa la «sonda de estado» opcional** del issue #175 (comprobación activa de la vía de respuesta). Este change solo añade el aprovisionamiento y el aviso **estático** de configuración incompleta.
- **No se toca el consumidor externo** (`/data/rust/valet`): ni código, ni specs, ni redacción.
- **No se relaja el fallo a cerrado** ni ninguna garantía de `valet-replies`: la colección `/wp/v2/comments` sigue eliminada para todos sin usuario de servicio.
- **No se cambia el rol ni la identidad de servicio** ni el mecanismo de credenciales (Application Password), que quedan como están.
