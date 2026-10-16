# Tasks: Integración de notificaciones con Valet

## 1. Test Harness & Baseline (RED)

- [x] 1.1 Crear script de prueba de caracterización y verificación de contratos para `\Atareao\ValetConfig` (validación de parámetros de `sendValetNotification`, credenciales vacías, longitud de mensaje, orígenes, niveles, payload JSON, cabeceras Bearer, y manejo de respuestas HTTP).
- [x] 1.2 Ejecutar el script y confirmar fallo esperado (RED) antes de implementar el código de producción.

## 2. Implementación de `ValetConfig` (GREEN)

- [x] 2.1 Crear `wp-content/plugins/atareao-functionality/includes/class-valet-config.php`:
  - Declarar namespace `Atareao`, comprobación `ABSPATH`.
  - Implementar `sendValetNotification($message, $origin = '', $author = '', $level = 'info')` con validación y petición HTTP POST autenticada.
  - Implementar `sendTestMessage()` enviando mensaje de comprobación al servidor Valet.
  - Implementar `renderConfigPage()` con formulario en tabla wp-admin, comprobación de `manage_options`, nonces, guardado y botón de prueba.
  - Implementar `notifyOnComment($comment_id, $comment_approved)` con filtro para comentarios aprobados (`1`).
  - Implementar `init()` enganchando `notifyOnComment` a `comment_post`.
- [x] 2.2 Registrar `ValetConfig` en `atareao-functionality.php` (`require_once` y llamada a `ValetConfig::init()`).
- [x] 2.3 Ejecutar el script de pruebas y verificar que pasan en GREEN.

## 3. Integración en el Hub de Ajustes y Formulario de Contacto (GREEN)

- [x] 3.1 Añadir la pestaña `valet` en `Settings::tabs()` en `wp-content/plugins/atareao-functionality/includes/class-settings.php` entre `mastodon` y `tema`.
- [x] 3.2 Integrar el despacho a Valet en `ContactForm::handleSubmission()` en `wp-content/plugins/atareao-functionality/includes/class-contact-form.php` cuando Valet esté configurado.
- [x] 3.3 Verificar que el script de pruebas de integración valida las 6 pestañas del hub y el flujo de contacto.

## 4. Refactor y Calidad (REFACTOR)

- [x] 4.1 Ejecutar `just php-lint` y comprobar sintaxis en todos los archivos modificados.
- [x] 4.2 Ejecutar `just phpcs` sobre los archivos modificados y asegurar cumplimiento PSR12 sin warnings ni errores.
- [x] 4.3 Actualizar `wp-content/plugins/atareao-functionality/README.md` documentando la nueva pestaña «Valet» y su configuración.

## 5. Correcciones de código pendientes (GREEN)

- [x] 5.1 Habilitar la notificación a Valet cuando un comentario se aprueba a posteriori enganchando la transición de estado `transition_comment_status` (además de `comment_post`), de modo que la aprobación desde el panel también despache.
- [x] 5.2 Recortar el contenido del comentario a 4096 bytes antes de enviarlo a Valet, para no superar el límite del endpoint de ingesta.
- [x] 5.3 No reimprimir el token de Valet en el HTML del panel de ajustes: mostrar el campo vacío o enmascarado sin revelar el valor guardado.
- [x] 5.4 Ejecutar el harness de pruebas `tests/test-valet.php` mediante la receta `just test` (que añade otro cambio) y confirmar que pasa en GREEN.

## 6. Consolidación y Cierre (ARCHIVE)

- [x] 6.1 Verificar estado con `openspec status --change valet-notifications`.
- [x] 6.2 Archivar el change con `openspec archive valet-notifications`.
- [x] 6.3 Confirmar tras el archive que se ha creado `openspec/specs/valet-notifications/spec.md`, que `openspec/specs/admin-settings/spec.md` refleja las seis pestañas con Valet y que `openspec validate --all` pasa (26/26). `just check-spec` falla por diseño al no quedar ningún change activo.
