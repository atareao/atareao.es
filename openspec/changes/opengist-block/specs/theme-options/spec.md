# theme-options Delta (opengist-block)

## MODIFIED Requirements

### Requirement: Registro de las opciones del grupo atareao_options_group

El sistema SHALL registrar en `admin_init` las opciones del grupo `atareao_options_group`: los nueve enlaces sociales `atareao_social_{youtube,ivoox,spotify,apple,telegram,x,mastodon,github,linkedin}` con saneado `esc_url_raw`; el feed de podcast `atareao_podcast_feed` con saneado `esc_url_raw`; y los ajustes de OpenGist `atareao_opengist_server`, `atareao_opengist_username` y `atareao_opengist_allowed_hosts`, con saneado `esc_url_raw`, `sanitize_text_field` y saneado por entrada con `sanitize_text_field` respectivamente, todos con `show_in_rest => true` y `default => ''`. Ningún nombre de opción SHALL renombrarse ni borrarse.

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
