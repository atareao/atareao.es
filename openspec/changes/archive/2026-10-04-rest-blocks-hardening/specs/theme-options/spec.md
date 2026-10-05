# theme-options Delta

## MODIFIED Requirements

### Requirement: Registro de las opciones del grupo atareao_options_group

El sistema SHALL registrar las opciones del grupo `atareao_options_group` en un hook que se ejecute tanto durante las peticiones de administración como durante las peticiones REST (por ejemplo `init`, o `rest_api_init` además de la vía de administración): los nueve enlaces sociales `atareao_social_{youtube,ivoox,spotify,apple,telegram,x,mastodon,github,linkedin}` con saneado `esc_url_raw`; el feed de podcast `atareao_podcast_feed` con saneado `esc_url_raw`; y los ajustes de OpenGist `atareao_opengist_server`, `atareao_opengist_username` y `atareao_opengist_allowed_hosts`, con saneado `esc_url_raw`, `sanitize_text_field` y saneado por entrada con `sanitize_text_field` respectivamente, todos con `show_in_rest => true` y `default => ''`. El registro SHALL ejecutarse realmente: dado que `ThemeOptions::init()` se invoca desde el callback de `init` (prioridad 10) del bootstrap del plugin, el registro SHALL engancharse a `init` con una prioridad **posterior** a la del callback que lo invoca (p. ej. prioridad 20), porque WP_Hook no ejecuta callbacks añadidos a la prioridad que está procesando. Cuando una opción declare `show_in_rest => true`, SHALL quedar efectivamente registrada en las peticiones REST (de modo que sus defaults sean visibles para el editor y para `/wp/v2/settings` conforme a la autorización de WordPress); si una opción no debiera exponerse por REST, SHALL declarar `show_in_rest => false` en lugar de declararlo y no cumplirlo. Ningún nombre de opción SHALL renombrarse ni borrarse.

#### Scenario: Redes sociales

- **WHEN** el módulo registra sus ajustes
- **THEN** los nueve enlaces sociales quedan registrados en el grupo `atareao_options_group` con `esc_url_raw` como saneado

#### Scenario: Feed de podcast

- **WHEN** el módulo registra sus ajustes
- **THEN** `atareao_podcast_feed` queda registrado en el grupo `atareao_options_group` con `esc_url_raw` como saneado

#### Scenario: Ajustes de OpenGist

- **WHEN** el módulo registra sus ajustes
- **THEN** `atareao_opengist_server`, `atareao_opengist_username` y `atareao_opengist_allowed_hosts` quedan registrados con `show_in_rest => true` y `default => ''`, con `esc_url_raw`, `sanitize_text_field` y saneado por entrada con `sanitize_text_field` respectivamente

#### Scenario: Saneado por tipo

- **WHEN** se guarda una URL no válida en un enlace social, un texto con etiquetas en el usuario de OpenGist o una lista de hosts con entradas vacías
- **THEN** el valor almacenado es el resultado del saneado correspondiente a su tipo y la lista de hosts conserva solo las entradas no vacías saneadas

#### Scenario: Registro ejecutado desde el bootstrap en `init`

- **WHEN** el bootstrap del plugin, enganchado a `init` con prioridad 10, invoca `ThemeOptions::init()`
- **THEN** las opciones quedan efectivamente registradas en esa misma ejecución de `init`, sin perderse por engancharse a la prioridad que se está procesando

#### Scenario: Opciones declaradas REST disponibles en las peticiones REST

- **WHEN** un administrador realiza una petición REST al endpoint de ajustes y una opción del grupo declara `show_in_rest => true`
- **THEN** la opción aparece registrada en el contexto REST con su default, sin que el registro dependa de un hook que no se ejecuta en peticiones REST ni de una prioridad que WP_Hook no procesa
