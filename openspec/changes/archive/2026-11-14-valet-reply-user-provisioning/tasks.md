# Tasks: Aprovisionamiento del usuario de servicio de Valet desde Ajustes

> **Estado:** completado y archivado (2026-11-14). Todas las tareas cerradas.
>
> **Verificación del arnés:** `just test` → `Total passed: 1208, Total failed: 0` (exit 0). `openspec validate --all` → `38 passed, 0 failed`. El change se archivó como `2026-11-14-valet-reply-user-provisioning` y el requisito «Aprovisionamiento del usuario de servicio desde Ajustes» quedó fusionado en `openspec/specs/valet-notifications/spec.md`.

## 1. Phase 0 — Línea base (antes de tocar nada)

- [x] 1.1 Confirmar el baseline verde del arnés. **Verificación:** `just test` → `Total failed: 0`, exit 0. **Evidencia esperada:** salida literal.
- [x] 1.2 Confirmar el baseline estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs wp-content/plugins/atareao-functionality` registra el par errores/warnings de partida. **Evidencia esperada:** baseline anotado.

## 2. RED — Stubs del arnés

- [x] 2.1 Añadir a `tests/wp-stubs.php` el stub `get_users` que falte para poblar el `<select>` de usuarios, con la forma mínima que el módulo consume (id y campo de visualización). **Verificación:** `just test` sigue verde. **Evidencia esperada:** stub registrado sin romper escenarios existentes.
- [x] 2.2 Añadir a `tests/wp-stubs.php` el stub `wp_get_current_user` si algún escenario nuevo lo requiere para modelar la capacidad `manage_options`. **Verificación:** `just test` sigue verde. **Evidencia esperada:** stub registrado (o justificación de que no hace falta).

## 3. RED — Tests de los cinco escenarios

- [x] 3.1 **RED — Aprovisionamiento desde la UI.** Escenario «Aprovisionamiento desde la UI»: seleccionar un usuario y guardar → `atareao_valet_reply_user` = ese ID y `isServiceRequest()` verdadero para esa identidad. **Verificación:** `just test` en ROJO (la UI aún no escribe la opción). **Evidencia esperada:** assertion fallida por opción sin actualizar.
- [x] 3.2 **RED — Ningún usuario seleccionado.** Escenario «Ningún usuario seleccionado»: guardar con «— Ninguno —» → opción `0` y colección eliminada para todos. **Verificación:** ROJO. **Evidencia esperada:** assertion fallida.
- [x] 3.3 **RED — ID inexistente.** Escenario «ID inexistente»: POST con ID inexistente → opción degradada a `0`. **Verificación:** ROJO. **Evidencia esperada:** assertion fallida (se acepta basura o no se degrada).
- [x] 3.4 **RED — Aviso de vía inerte.** Escenario «Aviso de vía inerte»: URL y token configurados y usuario `0` → aviso `notice notice-warning`. **Verificación:** ROJO. **Evidencia esperada:** assertion fallida por aviso ausente.
- [x] 3.5 **RED — Sin aviso si está aprovisionado.** Escenario «Sin aviso si está aprovisionado»: usuario `>0` → no se muestra el aviso. **Verificación:** verde estable tras el GREEN (control de que el aviso depende de la condición). **Evidencia esperada:** assertion verde estable.

## 4. GREEN — Implementación mínima

- [x] 4.1 Añadir el `<select>` de usuarios (`get_users`) más la opción «— Ninguno —» (`0`) a `ValetConfig::renderConfigPage()` (`wp-content/plugins/atareao-functionality/includes/class-valet-config.php`), reutilizando el nonce `atareao_valet_config`, el campo `atareao_valet_save` y la capacidad `manage_options` existentes. **Verificación:** `just php-lint` → 0 errores. **Evidencia esperada:** control renderizado.
- [x] 4.2 En el guardado, validar el valor recibido con `absint` y persistirlo en `atareao_valet_reply_user`, degradando a `0` cualquier valor que no corresponda a un usuario existente. **Verificación:** tareas 3.1–3.3 en verde. **Evidencia esperada:** `just test` 0 failed.
- [x] 4.3 Añadir el aviso `notice notice-warning` cuando `atareao_valet_url` y `atareao_valet_token` estén configurados y `atareao_valet_reply_user` sea `0`. **Verificación:** tareas 3.4–3.5 en verde. **Evidencia esperada:** `just test` 0 failed.
- [x] 4.4 Confirmar el fallo a cerrado intacto: sin usuario de servicio, la colección `/wp/v2/comments` sigue eliminada para todos. **Verificación:** escenarios de superficie de `valet-replies` siguen verdes. **Evidencia esperada:** verdes.

## 5. REFACTOR

- [x] 5.1 `just php-lint` → 0 errores; `just phpcs wp-content/plugins/atareao-functionality` con delta +0 respecto del baseline de 1.2 (o cada violación nueva justificada). **Verificación:** ambos comandos pegados en el informe. **Evidencia esperada:** 0 errores de lint; phpcs sin violaciones nuevas.
- [x] 5.2 Reejecutar `just test` completo tras el refactor: 0 failed. **Verificación:** salida literal. **Evidencia esperada:** `Total failed: 0`, exit 0.

## 6. Validación OpenSpec y cierre

- [x] 6.1 `openspec validate valet-reply-user-provisioning` → exit 0. **Verificación:** salida literal pegada. **Evidencia esperada:** `Change 'valet-reply-user-provisioning' is valid`, `EXIT=0`.
- [x] 6.2 Confirmar que el header `### Requirement:` del delta es **literal** respecto del que se propagará a la canónica y que el delta **no** modifica requisitos existentes (solo `ADDED`). **Verificación:** `grep '^### Requirement:'` sobre el delta. **Evidencia esperada:** un único requisito nuevo.
- [x] 6.3 Actualizar `openspec/specs/valet-notifications/spec.md` **al archivar** (`openspec archive valet-reply-user-provisioning`), no antes. **Verificación:** el requisito nuevo aparece en la canónica tras el archivado. **Evidencia esperada:** requisito propagado.
- [x] 6.4 Marcar las casillas a medida que se completen y archivar **solo** cuando el usuario lo indique y todas estén cerradas. **Verificación:** `openspec list` ya no lista el change tras archivar. **Evidencia esperada:** pendiente.
