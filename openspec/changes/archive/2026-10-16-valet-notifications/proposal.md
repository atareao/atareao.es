## Why

Hoy `atareao.es` notifica eventos internos (comentarios aprobados y formulario de contacto) únicamente a Matrix. Valet (`/data/rust/valet`) es el asistente personal de IA auto-hospedado y local-first que dispone de una bandeja de ingesta de notificaciones HTTP (`POST /api/notifications`) autenticada mediante claves de aplicación Bearer.

Este cambio añade soporte para **Valet** como canal de notificaciones en el plugin `atareao-functionality`: almacenamiento de credenciales, pestaña «Valet» en el hub de ajustes con botón de prueba, envío autenticado a `/api/notifications`, notificación automática ante comentarios aprobados y entrega desde el formulario de contacto coexistiendo con Matrix.

## What Changes

- **Nueva capability `valet-notifications`**:
  - Almacena las opciones `atareao_valet_url` y `atareao_valet_token`.
  - Proporciona el contrato público de envío `\Atareao\ValetConfig::sendValetNotification($message, $origin, $author, $level)`.
  - Maneja el botón de prueba «Enviar mensaje de prueba» en la interfaz de administración.
  - Engancha en las acciones `comment_post` (comentario insertado ya aprobado) y `transition_comment_status` (aprobación a posteriori desde el panel, solo en la transición hacia `approved`) para despachar notificaciones de comentarios aprobados, sin doble notificación y recortando el texto del comentario a 4096 bytes (recorte byte-safe con sufijo `…`) cuando excede el presupuesto.
  - Es invocado por `\Atareao\ContactForm` para notificar envíos válidos del formulario de contacto.
- **Nueva clase `\Atareao\ValetConfig`** en `wp-content/plugins/atareao-functionality/includes/class-valet-config.php`, siguiendo el estándar del repositorio (`namespace Atareao;`, guarda `ABSPATH`, inicialización en `init()`, métodos estáticos).
- **Registro en el plugin**: `require_once ATAREAO_PLUGIN_DIR . 'includes/class-valet-config.php';` y llamada a `ValetConfig::init();` en `atareao-functionality.php`.
- **Sexta pestaña «Valet» en el hub de ajustes**:
  - Slug `valet`, etiqueta «Valet», ubicada entre «Mastodon» y «Tema» (orden: `matrix`, `pocketid`, `umami`, `mastodon`, `valet`, `tema`).
  - Render delegado en `ValetConfig::renderConfigPage()`.
  - Guarda por POST contra sí misma con nonce propio `atareao_valet_config` y capacidad `manage_options`; el token se muestra enmascarado (`type="password"`, sin atributo `value`, nunca se reimprime en el HTML) y un POST con el token vacío conserva el valor anterior.
- **Integración con `ContactForm`**:
  - Cuando se envía un formulario de contacto válido, si Valet está configurado (URL y token presentes), se entrega la notificación a Valet. Si Matrix está configurado, también se entrega a Matrix.
- **Documentación**:
  - `wp-content/plugins/atareao-functionality/README.md` actualizado documentando la pestaña «Valet» y su funcionamiento.

## Capabilities

### New Capabilities

- `valet-notifications`: configuración de Valet, almacenamiento de credenciales, envío de notificaciones autenticadas mediante `POST /api/notifications`, mensaje de prueba, notificación de comentarios aprobados y entrega de mensajes del formulario de contacto.

### Modified Capabilities

- `admin-settings`: el hub pasa de cinco a seis pestañas incorporando `valet` → «Valet», añade `ValetConfig::renderConfigPage()` a la delegación de renders y reconoce el guardado por POST con nonce `atareao_valet_config` más el botón de prueba.

## Impact

- **Archivos creados**:
  - `wp-content/plugins/atareao-functionality/includes/class-valet-config.php`
- **Archivos modificados**:
  - `wp-content/plugins/atareao-functionality/includes/class-settings.php`
  - `wp-content/plugins/atareao-functionality/includes/class-contact-form.php`
  - `wp-content/plugins/atareao-functionality/atareao-functionality.php`
  - `wp-content/plugins/atareao-functionality/README.md`
- **Especificaciones**:
  - Nueva spec: `openspec/specs/valet-notifications/spec.md` (vía `openspec/changes/valet-notifications/specs/valet-notifications/spec.md`).
  - Delta: `openspec/changes/valet-notifications/specs/admin-settings/spec.md` (modifica `admin-settings`).
- **Compatibilidad y seguridad**:
  - Sin rotura de contratos ni opciones existentes.
  - Saneado de inputs con `sanitize_text_field` y `esc_url_raw`.
  - Nonces y comprobación estricta de `manage_options`.
  - PHP 8.3, PSR12.
