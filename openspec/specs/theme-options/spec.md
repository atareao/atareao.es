# theme-options Specification

## Purpose
Esta capability caracteriza y documenta el comportamiento real del módulo `\Atareao\ThemeOptions` del plugin `atareao-functionality`: el registro del grupo de opciones `atareao_options_group` para los enlaces sociales, el feed de podcast y los ajustes de OpenGist, y su formulario de guardado mediante la Settings API. La UI del módulo pasa a ser la pestaña `tema` del hub de ajustes «Atareao» (deja de ser la página «Theme Options» bajo Apariencia); los nombres de opción, el saneado, `show_in_rest`, los defaults y el destino del formulario se conservan sin cambios. Esta capability se documenta como paso de caracterización (Phase 0) previo a refactorizar la ubicación de su pantalla.

## Requirements

### Requirement: Registro de las opciones del grupo atareao_options_group

El sistema SHALL registrar en `admin_init` las opciones del grupo `atareao_options_group`: los nueve enlaces sociales `atareao_social_{youtube,ivoox,spotify,apple,telegram,x,mastodon,github,linkedin}` con saneado `esc_url_raw`; el feed de podcast `atareao_podcast_feed` con saneado `esc_url_raw`; y los ajustes de OpenGist `atareao_opengist_server` y `atareao_opengist_username`, con saneado `esc_url_raw` y `sanitize_text_field` respectivamente, ambos con `show_in_rest => true` y `default => ''`. Ningún nombre de opción SHALL renombrarse ni borrarse.

#### Scenario: Redes sociales

- **WHEN** el módulo registra sus ajustes
- **THEN** los nueve enlaces sociales quedan registrados en el grupo `atareao_options_group` con `esc_url_raw` como saneado

#### Scenario: Feed de podcast

- **WHEN** el módulo registra sus ajustes
- **THEN** `atareao_podcast_feed` queda registrado en el grupo `atareao_options_group` con `esc_url_raw` como saneado

#### Scenario: Ajustes de OpenGist

- **WHEN** el módulo registra sus ajustes
- **THEN** `atareao_opengist_server` y `atareao_opengist_username` quedan registrados con `show_in_rest => true` y `default => ''`, con `esc_url_raw` y `sanitize_text_field` respectivamente

#### Scenario: Saneado por tipo

- **WHEN** se guarda una URL no válida en un enlace social o un texto con etiquetas en el usuario de OpenGist
- **THEN** el valor almacenado es el resultado del saneado correspondiente a su tipo

### Requirement: Formulario y guardado por la Settings API en la pestaña «Tema»

El sistema SHALL exponer el formulario del módulo en la pestaña `tema` del hub de ajustes «Atareao». El formulario SHALL conservar la Settings API: `<form method="post" action="options.php">`, `settings_fields('atareao_options_group')` y `submit_button()`. Los campos SHALL renderizarse con el nombre de opción correspondiente y las URLs escapadas con `esc_url`/`esc_attr`. Este change elimina el `add_theme_page` y el envoltorio del módulo, de modo que la ubicación de la UI se describe por referencia a la pestaña `tema` y no como una página independiente en Apariencia.

#### Scenario: Formulario apunta a options.php

- **WHEN** se renderiza la pestaña «Tema»
- **THEN** el formulario se envía por POST a `options.php` con el grupo `atareao_options_group` declarado con `settings_fields()`

#### Scenario: Botón de guardado

- **WHEN** se renderiza la pestaña «Tema»
- **THEN** el formulario incluye el botón de envío generado por `submit_button()`

#### Scenario: Guardado por la Settings API

- **WHEN** un administrador guarda la pestaña «Tema»
- **THEN** `options.php` procesa el grupo `atareao_options_group` y las opciones se guardan con su saneado registrado
