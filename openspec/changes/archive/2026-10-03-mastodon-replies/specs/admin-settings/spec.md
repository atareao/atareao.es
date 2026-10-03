# Admin Settings Delta

## MODIFIED Requirements

### Requirement: Punto de entrada único y pestañas por URL

El sistema SHALL registrar en wp-admin un único punto de entrada en el menú Ajustes mediante `add_options_page` con etiqueta de menú «Atareao», título de página «Ajustes de Atareao», slug `atareao-settings` y capacidad `manage_options`. El hub SHALL pintar el contenedor `.wrap`, su `<h1>` y una barra `<nav class="nav-tab-wrapper" aria-label="…">` con las cinco pestañas, en este orden y con estas etiquetas y slugs: `matrix` → «Matrix», `pocketid` → «PocketID», `umami` → «Umami», `mastodon` → «Mastodon» y `tema` → «Tema». La pestaña activa SHALL resolverse a partir del parámetro `tab` de la URL `options-general.php?page=atareao-settings&tab=<slug>`, sin JavaScript, contra una whitelist de slugs conocidos; cuando `tab` falte o no sea un slug conocido, el sistema SHALL mostrar la primera pestaña (`matrix`). La pestaña activa SHALL usar las clases `nav-tab`/`nav-tab-active` de core y SHALL marcar el enlace con `aria-current="page"`.

#### Scenario: Render del hub con las cuatro pestañas

- **WHEN** un administrador con capacidad `manage_options` abre `options-general.php?page=atareao-settings`
- **THEN** la página se sirve bajo el contenedor `.wrap` con un `<h1>`, un `nav-tab-wrapper` y exactamente las cinco pestañas «Matrix», «PocketID», «Umami», «Mastodon» y «Tema» en ese orden

#### Scenario: Pestaña válida selecciona contenido y la marca activa

- **WHEN** se solicita `options-general.php?page=atareao-settings&tab=umami`
- **THEN** se muestra el contenido de la pestaña «Umami» y su enlace es el único con `nav-tab-active` y `aria-current="page"`

#### Scenario: Pestaña ausente o desconocida

- **WHEN** se solicita el hub sin `tab` o con un `tab` que no pertenece a la whitelist (por ejemplo `tab=inexistente`)
- **THEN** el sistema muestra la pestaña «Matrix» como activa

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` intenta acceder al hub
- **THEN** no puede ver la página ni el contenido de ninguna pestaña

#### Scenario: Pestañas sin JavaScript

- **WHEN** la página se sirve sin JavaScript habilitado en el navegador
- **THEN** la navegación entre las cinco pestañas sigue funcionando mediante enlaces de URL

### Requirement: Delegación en los módulos y envoltorio único

El hub SHALL delegar el contenido de cada pestaña en la función de render del módulo correspondiente: `MatrixConfig::renderConfigPage()` para `matrix`, `PocketIDLogin::renderSettingsPage()` para `pocketid`, `Analytics::renderSettingsPage()` para `umami`, `MastodonReplies::renderSettingsPage()` para `mastodon` y `ThemeOptions::renderOptionsPage()` para `tema`. El hub SHALL ser el único dueño del envoltorio: los render de los módulos SHALL NOT imprimir el `.wrap` ni su `<h1>`, de modo que no se produzcan envoltorios anidados. Cada módulo SHALL conservar su propia lógica de guardado, sus verificaciones de nonce y capacidad, su saneado, sus avisos y el destino de su formulario; el hub SHALL NOT reimplementar ni interferir en esa lógica.

#### Scenario: Delegación del contenido por pestaña

- **WHEN** se abre cada una de las cinco pestañas
- **THEN** el contenido mostrado proviene de la función de render del módulo correspondiente y no de una copia en el hub

#### Scenario: Envoltorio único sin anidar

- **WHEN** se renderiza cualquiera de las cinco pestañas
- **THEN** la página contiene un único contenedor `.wrap` y un único `<h1>` (los del hub) y ningún envoltorio anidado procedente del módulo

#### Scenario: Los módulos conservan su guardado

- **WHEN** se envía el formulario de cualquiera de las pestañas
- **THEN** el guardado lo procesa el módulo dueño de esa pestaña con sus nonces, su comprobación de `manage_options` y su saneado, exactamente como antes del hub

### Requirement: Guardado por la vía propia de cada pestaña

El sistema SHALL permitir que cada pestaña guarde por su vía original, sin unificar formularios ni grupos de opciones. La pestaña `matrix` SHALL guardar por POST contra sí misma con el nonce `atareao_matrix_config` y ofrecer el botón «Enviar mensaje de prueba». La pestaña `pocketid` SHALL guardar por POST contra sí misma con el nonce `atareao_pocketid_config` y ofrecer el botón «Probar conexión». La pestaña `umami` SHALL conservar su guardado en `admin_init` con nonce y su acción de importación del plugin legado. La pestaña `mastodon` SHALL guardar por POST contra sí misma con su propio nonce y ofrecer el botón «Comprobar ahora» con su propio nonce. La pestaña `tema` SHALL conservar la Settings API (`settings_fields('atareao_options_group')` + POST a `options.php`), declare un `_wp_http_referer` explícito con la URL de su pestaña después de `settings_fields()` para que el guardado vuelva al hub, y SHALL llamar a `settings_errors()` para mostrar el aviso de guardado.

#### Scenario: Guardado de Matrix por POST y nonce

- **WHEN** un administrador guarda la pestaña «Matrix»
- **THEN** el formulario se envía por POST a la propia pestaña con el nonce `atareao_matrix_config` y los ajustes se persisten por la vía del módulo Matrix

#### Scenario: Guardado y prueba de conexión de PocketID

- **WHEN** un administrador guarda la pestaña «PocketID» o pulsa «Probar conexión»
- **THEN** el formulario se envía por POST a la propia pestaña con el nonce `atareao_pocketid_config` y la operación la procesa el módulo PocketID

#### Scenario: Guardado e importación de Umami

- **WHEN** un administrador guarda la pestaña «Umami» o pulsa la importación de la configuración legada
- **THEN** el guardado se procesa en `admin_init` con su nonce y la importación la procesa el módulo Analytics sin cambiar de formulario ni de grupo

#### Scenario: Guardado y comprobación de Mastodon

- **WHEN** un administrador guarda la pestaña «Mastodon» o pulsa «Comprobar ahora»
- **THEN** el guardado y la comprobación se envían por POST a la propia pestaña con su nonce y la operación la procesa el módulo MastodonReplies

#### Scenario: Guardado de Tema por options.php con vuelta a la pestaña

- **WHEN** un administrador guarda la pestaña «Tema»
- **THEN** el formulario se envía a `options.php` con el grupo `atareao_options_group`, el `_wp_http_referer` apunta a la URL de la pestaña «Tema» del hub, el guardado vuelve a esa pestaña y `settings_errors()` muestra el aviso correspondiente

#### Scenario: Los destinos de formulario no se unifican

- **WHEN** se comparan los formularios de las cinco pestañas
- **THEN** cada uno mantiene el destino y el grupo/nonce que usaba antes del hub (Matrix y PocketID a sí mismas, Umami en `admin_init`, Mastodon a sí misma con su propio nonce y Tema a `options.php`)
