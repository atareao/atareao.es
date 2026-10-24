# Design: Valet Notifications

## Context

El sitio `atareao.es` gestiona sus integraciones internas a través del plugin `atareao-functionality`. Hasta ahora, las notificaciones de eventos (aprobación de comentarios y envío de formulario de contacto) se despachan a una sala de Matrix mediante la clase `\Atareao\MatrixConfig`.

Valet (`/data/rust/valet`) es el asistente personal de Lorenzo con backend en Rust (Axum + SQLite) y ofrece un endpoint HTTP de ingesta de notificaciones:
- `POST /api/notifications`
- Autenticación: `Authorization: Bearer <token>` (clave de aplicación con capacidad `ingest-keys`).
- Estructura de ingesta: `message` (obligatorio, máx 4096 bytes), `origin` (opcional, máx 64 bytes), `author` (opcional, máx 128 bytes), `level` (opcional: `info`, `warning`, `critical`).
- Respuesta exitosa: HTTP `201 Created` con JSON `{"id":"<uuid>"}`.

## Goals / Non-Goals

**Goals:**
- Proporcionar una clase desacoplada `\Atareao\ValetConfig` siguiendo los patrones ya consolidados en `MatrixConfig`.
- Añadir la pestaña «Valet» al hub `\Atareao\Settings` para configurar la URL y el token, probar el envío y persistir opciones de forma segura.
- Despachar notificaciones a Valet ante comentarios aprobados (inserción ya aprobada vía `comment_post` y aprobación a posteriori vía `transition_comment_status`) y mensajes de contacto (`ContactForm`).
- Garantizar que fallos de red o errores en Valet no bloqueen la navegación de los usuarios ni la aprobación de comentarios.

**Non-Goals:**
- Reemplazar Matrix: ambos canales coexisten y pueden estar activos simultáneamente o por separado.
- Leer o consultar el buzón de Valet desde WordPress (Valet es consumidor de las notificaciones; la lectura se hace en la interfaz de Valet).
- Modificar el esquema de base de datos de WordPress (se usan `wp_options`).

## Decisions

### 1. Ubicación y estructura del módulo
- **Decisión**: Crear `includes/class-valet-config.php` en el plugin `atareao-functionality` con namespace `\Atareao\ValetConfig`.
- **Razón**: Sigue la arquitectura del proyecto donde la lógica de negocio vive en el plugin y el tema es exclusivamente de presentación.
- **Alternativas consideradas**: Ponerlo en el tema (rechazado por violar la regla de separación de responsabilidades) o unificar Matrix y Valet en una clase genérica (rechazado para mantener la sencillez y el aislamiento de fallos).

### 2. Contrato de envío HTTP (`wp_remote_post`)
- **Decisión**: Usar la API HTTP de WordPress (`wp_remote_post`) con un timeout conservador de 10 segundos, cabeceras `Authorization: Bearer <token>` y `Content-Type: application/json`.
- **Razón**: Nativo en WordPress, respeta los proxies y hooks del entorno. Valet responde de manera sincrónica en menos de 50ms al insertar en SQLite.
- **Alternativas consideradas**: Despacho asíncrono con WP-Cron (descartado por añadir latencia y complejidad innecesaria para un volumen bajo de eventos).

### 3. Orden y URL en el Hub de Ajustes
- **Decisión**: Añadir `valet` como 6ª pestaña antes de `tema` (`matrix`, `pocketid`, `umami`, `mastodon`, `valet`, `tema`).
- **Razón**: Mantiene los servicios de comunicación/notificaciones agrupados y deja `tema` al final.

### 4. Coexistencia con Matrix en el Formulario de Contacto
- **Decisión**: En `ContactForm::handleSubmission()`, despachar a Matrix si Matrix tiene credenciales configuradas, y a Valet si Valet tiene credenciales configuradas. El envío se considera exitoso si al menos uno de los canales configurados responde con éxito.
- **Razón**: Permite migrar gradualmente, usar ambos a la vez o usar exclusivamente Valet.

### 5. Cobertura de la aprobación a posteriori (`comment_post` + `transition_comment_status`)
- **Decisión**: Registrar dos hooks complementarios: `add_action('comment_post', array(__CLASS__, 'notifyOnComment'), 10, 2)` y `add_action('transition_comment_status', array(__CLASS__, 'notifyOnCommentTransition'), 10, 3)`. `notifyOnCommentTransition($new_status, $old_status, $comment)` solo despacha si `$new_status === 'approved'`, `$old_status !== 'approved'` y `is_object($comment)`.
- **Razón**: `comment_post` solo se dispara al **insertar** el comentario (con `comment_approved = 0` si queda pendiente), así que un comentario moderado y aprobado después desde el panel nunca notificaría; `transition_comment_status` es la acción que dispara `wp_set_comment_status()`. Los dos caminos son mutuamente excluyentes —un comentario insertado ya aprobado pasa por `comment_post`, y uno aprobado después pasa por la transición—, por lo que no hay doble aviso; además una transición repetida `approved → approved` (o hacia `hold`/`spam`/`trash`) se ignora. Ambos caminos comparten el helper privado `notifyApprovedComment()`.
- **Alternativas consideradas**: Usar solo `transition_comment_status` (rechazado: perdería la notificación de los comentarios insertados ya aprobados) o deduplicar por `comment_ID` con un transient (rechazado por complejidad innecesaria al ser los caminos excluyentes).

### 6. Presupuesto de bytes y recorte byte-safe del mensaje de comentario
- **Decisión**: Construir el mensaje garantizando por construcción que nunca supera `MESSAGE_MAX_BYTES` (4096 bytes): cabecera (`Comentario de <autor> en <host>`, salto de línea, permalink y salto de línea) más el contenido recortado al presupuesto restante; cuando se recorta se añade el sufijo `…` (U+2026). El recorte es byte-safe: `mb_strcut()` si está disponible y, en su defecto, `truncateBytes()` descarta la secuencia UTF-8 incompleta final.
- **Razón**: El límite duro de `sendValetNotification()` (mensaje > 4096 bytes ⇒ cadena de error) haría que un comentario largo se perdiera silenciosamente. Recortar antes de enviar preserva la notificación y respeta el contrato del endpoint, sin partir caracteres multibyte.
- **Alternativas consideradas**: Recortar con `substr()` (rechazado: puede partir un carácter UTF-8) o ampliar el límite del endpoint (fuera de alcance: lo impone Valet).

### 7. Enmascarado del token y guardado no destructivo
- **Decisión**: Renderizar el token con `type="password"`, `autocomplete="new-password"` y placeholder `••••••••`, sin atributo `value`, de modo que el valor guardado nunca se reimprime en el HTML. Un POST con el token vacío o solo espacios **conserva** el token anterior; solo se actualiza si llega un valor no vacío tras `trim`.
- **Razón**: No filtrar el token en el HTML del panel (defensa frente a exposición en pantalla o en el DOM). Como el formulario no reimprime el valor, un POST vacío no puede significar «borrar»; tratarlo como no-op evita borrados accidentales.
- **Alternativas consideradas**: Mostrar el token en texto plano (rechazado por exposición) o añadir un checkbox «borrar token» (rechazado por no ser necesario para el caso de uso).

### 8. Timeout HTTP de 10 segundos (mantenido)
- **Decisión**: Mantener el timeout de la petición HTTP en 10 segundos.
- **Razón**: Cada comentario o contacto puede desencadenar una petición síncrona; el limitador de tasa del formulario de contacto (`getRateLimit()` con transient por IP) acota la amplificación de un DoS de baja intensidad, por lo que no se necesita un timeout menor. Valet responde en menos de 50 ms en condiciones normales.
- **Alternativas consideradas**: Reducir el timeout (rechazado: no aporta protección real dado el rate limiter) o despachar por WP-Cron (rechazado en la decisión 2).

## Risks / Trade-offs

- **[Servidor Valet no disponible o red lenta]** → La petición HTTP tiene un timeout de 10s y captura `is_wp_error()`. Si falla, se registra o notifica sin provocar excepción fatal ni pantalla blanca.
- **[Token revocado o incorrecto]** → Valet responde `401 Unauthorized`. El método devuelve una cadena de error descriptiva al pulsar «Enviar mensaje de prueba» para que el administrador lo detecte de inmediato.
- **[Longitud de mensaje excesiva]** → Valet rechaza mensajes mayores de 4096 bytes. En la ruta de comentarios el módulo recorta el contenido del comentario a 4096 bytes antes de enviarlo; en el contrato público `sendValetNotification()` el mensaje se valida y, si excede el límite, se devuelve una cadena de error descriptiva sin realizar la petición, de modo que nunca se envía un payload inválido.
