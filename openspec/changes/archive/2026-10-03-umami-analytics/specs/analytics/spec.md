# Analytics Delta

## Purpose

Esta capability integra la analítica del sitio con Umami desde el plugin `atareao-functionality`, sin depender de plugins de terceros: emite el script del tracker en el pie de página solo cuando está habilitado y configurado, respetando exclusiones duras y opcionales, y expone desde wp-admin los ajustes del script, sus exclusiones, el tracking de comentarios, la integridad opcional (SRI) y la migración desde el plugin legado. Garantiza además que la configuración sobrevive a la desactivación del plugin.

## ADDED Requirements

### Requirement: Inyección del script de Umami desde el plugin

El sistema SHALL emitir la etiqueta `<script>` del tracker de Umami en `wp_footer` únicamente cuando la analítica esté habilitada y tenga `script_url` y `website_id` no vacíos. Los atributos emitidos SHALL ser funcionalmente equivalentes a los del plugin legado `Integrate Umami` (mismo `src`, mismo `data-website-id` y los mismos modificadores), de modo que el dato recibido por Umami no cambie, pero con HTML válido: valores entrecomillados y escapados con `esc_url` para el `src` y `esc_attr` para el resto. El sistema SHALL aplicar una exclusión dura, independiente de la configuración, que impide la inyección en `is_admin()`, `is_feed()`, `is_preview()`, `is_customize_preview()`, peticiones REST e `is_robots()`. El sistema SHALL ofrecer exclusiones opcionales con default desactivado para no emitir en páginas 404 (`skip_404`) y en resultados de búsqueda (`skip_search`). Cuando `ignore_admins` esté activo, el sistema SHALL omitir la emisión para usuarios que puedan `manage_options`. Si el plugin legado está cargado **y con intención de emitir** (señal `class_exists('\Ancozockt\Umami\Manager')` con su configuración activa: `enabled` y `script_url` y `website_id` no vacíos), el sistema SHALL abstenerse de emitir para evitar el doble conteo durante la convivencia de ambos plugins; si el plugin legado está cargado pero inactivo o incompleto, el sistema SHALL emitir su propio script.

#### Scenario: Inyección con la configuración de producción

- **WHEN** la analítica está habilitada, `script_url` es `https://umami.atareao.es/script.js`, el `website_id` está configurado y `do_not_track` está activo
- **THEN** el HTML servido contiene una única etiqueta `<script>` con `src="https://umami.atareao.es/script.js"`, el `data-website-id` configurado y `data-do-not-track="true"`

#### Scenario: Analítica deshabilitada o incompleta

- **WHEN** la analítica está deshabilitada, o `script_url` está vacío, o `website_id` está vacío
- **THEN** no se emite ninguna etiqueta `<script>` de Umami

#### Scenario: Administrador con `ignore_admins` activo

- **WHEN** un usuario autenticado con capacidad `manage_options` carga el sitio y `ignore_admins` está activo
- **THEN** no se emite el script de Umami para ese usuario

#### Scenario: Páginas 404 y búsqueda con exclusiones opcionales

- **WHEN** la analítica está habilitada y `skip_404` está activo al cargar una página 404, o `skip_search` está activo al cargar una búsqueda
- **THEN** no se emite el script en ese contexto

#### Scenario: Contextos excluidos siempre

- **WHEN** la petición es del panel de administración, un feed, una previsualización de entrada, el personalizador, la API REST o `robots.txt`
- **THEN** el script de Umami nunca se emite, con independencia de la configuración

#### Scenario: Convivencia con el plugin legado

- **WHEN** el plugin «Integrate Umami» está activo y cargado y el sistema detecta su clase de gestión
- **THEN** el sistema no emite su propio script y el panel informa de que hay que desactivar el plugin legado

#### Scenario: Plugin legado cargado pero inactivo

- **WHEN** el plugin legado está cargado pero su analítica está desactivada o incompleta (`enabled`, `script_url` o `website_id` vacíos)
- **THEN** el sistema emite su propio script, porque el legado no va a emitir

#### Scenario: Modificadores del tracker

- **WHEN** `auto_track` está desactivado, o `cache` está activo, o `use_host_url` está activo con un `host_url` no vacío, o `exclude_search`/`exclude_hash` están activos
- **THEN** la etiqueta del script incluye respectivamente `data-auto-track="false"`, `data-cache="true"`, `data-host-url` con la URL configurada y `data-exclude-search`/`data-exclude-hash`

### Requirement: Panel de configuración y migración

El sistema SHALL exponer una página de ajustes propia bajo el menú Ajustes, accesible solo a usuarios con capacidad `manage_options`, que permita configurar el script, las exclusiones, el tracking de comentarios y la integridad. El guardado SHALL procesarse por POST, verificarse con un nonce y volver a comprobar `manage_options`, saneando cada campo según su tipo: URLs con `esc_url_raw`, identificadores y banderas con saneado equivalente a `sanitize_text_field` y normalización a `0`/`1`. Los valores no escalares SHALL normalizarse a `''` o `0` sin emitir avisos de conversión. La página SHALL ofrecer una acción de importación que lea la configuración del plugin legado —o, si este ya la borró, la copia propia que el sistema mantiene—, vuelque sus valores en los ajustes nuevos, no borre la configuración legada e informe del número de ajustes importados o de que no encontró nada.

#### Scenario: Guardado con saneado de los ajustes

- **WHEN** un administrador guarda el formulario con una URL y valores de banderas válidos o inválidos
- **THEN** los ajustes se almacenan saneados por tipo y el guardado se rechaza si el nonce no es válido

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` intenta acceder a la página o enviar el formulario de guardado o de importación
- **THEN** no puede ver la página ni modificar los ajustes

#### Scenario: Importación con configuración legada presente

- **WHEN** existe la configuración del plugin legado y un administrador pulsa importar
- **THEN** los valores se vuelcan en los ajustes nuevos, el ajuste legado permanece intacto y se informa del número de ajustes importados

#### Scenario: Importación sin configuración legada

- **WHEN** no existe la configuración del plugin legado y un administrador pulsa importar
- **THEN** no se modifica ningún ajuste y se informa de que no se encontró nada que importar

#### Scenario: Importación después de desactivar el plugin legado

- **WHEN** el plugin legado ya no está cargado y su opción fue borrada, pero existe la copia propia `atareao_umami_legacy_snapshot`
- **THEN** la importación recupera los ajustes desde la copia, informa del número importado y el aviso de apagón desaparece

#### Scenario: Aviso de analítica desactivada con configuración legada presente

- **WHEN** la analítica propia está deshabilitada, el plugin legado ya no está cargado (`class_exists('\Ancozockt\Umami\Manager')` es falso) y existe la configuración legada `integrate_umami_options`
- **THEN** el panel de ajustes muestra un aviso indicando que la analítica está desactivada y que importe o active los ajustes, de modo que no se pierda el registro de visitas en silencio

### Requirement: Integridad del script (SRI) opcional

El sistema SHALL permitir configurar de forma opcional un hash de integridad del script. Cuando el ajuste de integridad tenga valor, el sistema SHALL emitir el atributo `integrity` con ese hash junto a `crossorigin="anonymous"`; cuando esté vacío, el sistema SHALL no emitir ninguno de los dos atributos y comportarse como hasta ahora. Un valor de integridad presente pero no utilizable SHALL NOT romper el HTML ni impedir la carga del resto de la página.

#### Scenario: SRI configurado

- **WHEN** el ajuste de integridad contiene un hash `sha384-…`
- **THEN** la etiqueta del script incluye `integrity` con ese hash y `crossorigin="anonymous"`

#### Scenario: SRI no configurado

- **WHEN** el ajuste de integridad está vacío
- **THEN** la etiqueta del script no incluye `integrity` ni `crossorigin`

#### Scenario: Hash inválido

- **WHEN** el ajuste de integridad contiene un valor no válido o mal formado
- **THEN** el HTML sigue siendo válido y el resto de la página se sirve con normalidad

### Requirement: Eventos de comentarios y persistencia de la configuración

El sistema SHALL ofrecer un ajuste opcional, desactivado por defecto, para el tracking de comentarios. Cuando esté activo y la petición sea una entrada individual, el sistema SHALL añadir sobre el elemento ya existente del formulario de comentarios los atributos `data-umami-event="comment"`, `data-umami-event-post-id` con el identificador de la entrada y `data-umami-event-post-title` con el título truncado, sin cambiar el tipo del elemento ni eliminar su contenido ni su etiqueta. El título SHALL conservarse literal (sin expansión de backreferences de PCRE) y SHALL truncarse a 50 caracteres de forma segura con caracteres multibyte (sin partir secuencias UTF-8). La desactivación del plugin SHALL NOT eliminar la configuración de la analítica.

#### Scenario: Tracking de comentarios activo sobre un botón

- **WHEN** el tracking de comentarios está activo y se carga una entrada cuyo formulario usa `<button type="submit">`
- **THEN** el elemento sigue siendo un `<button>`, conserva su texto y recibe los atributos de evento de Umami

#### Scenario: Título del evento con caracteres especiales o multibyte

- **WHEN** el título de la entrada contiene `$`, `\` o caracteres multibyte
- **THEN** el atributo conserva el título truncado correctamente, sin expansión de backreferences y sin UTF-8 inválido

#### Scenario: Tracking de comentarios desactivado

- **WHEN** el tracking de comentarios está desactivado y se carga una entrada
- **THEN** el formulario de comentarios no contiene atributos `data-umami-event`

#### Scenario: Desactivación que conserva los ajustes

- **WHEN** el plugin se desactiva y después se vuelve a activar
- **THEN** los ajustes de la analítica siguen almacenados con sus valores previos
