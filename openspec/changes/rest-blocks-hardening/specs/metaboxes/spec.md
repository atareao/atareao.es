# Metaboxes Delta

## Purpose

Esta capability define el contrato de seguridad del módulo `\Atareao\Metaboxes` en su doble superficie de salida: los campos que expone por la REST API y el endpoint AJAX de cálculo del número de capítulo. Fija que la exposición REST de los metadatos del podcast se limite a un conjunto curado de claves públicas —sin claves protegidas ni internas— y declare `auth_callback` para restringir la lectura, y que el handler AJAX valide un nonce además de la capacidad, sin alterar los nombres de campo, el hook, la acción ni el contrato de respuesta existentes.

## ADDED Requirements

### Requirement: Exposición REST acotada de metadatos de podcast

Los campos REST `all_metadata` y `metadata` del tipo `podcast` SHALL devolver únicamente un conjunto curado de metadatos públicos del podcast y SHALL NOT devolver claves protegidas (prefijo `_`) ni claves internas o de infraestructura (por ejemplo `_edit_lock`, `_genesis_description`, `_thumbnail_id`, `_download_url`, `_repository_url`, `_version`). Ambos campos SHALL declarar un `auth_callback` que restrinja la lectura, de modo que la exposición de metadatos sea una decisión explícita y no el volcado indiscriminado de todas las claves. Los nombres de los campos REST (`all_metadata`, `metadata`) y los del resto de campos (`seo_description`, `mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`) SHALL conservarse; las claves protegidas SHALL NOT exponerse aunque la petición esté autenticada.

#### Scenario: Claves protegidas excluidas en la lectura anónima

- **WHEN** se solicita `GET /wp-json/wp/v2/podcast/<id>` sin autenticar y el podcast tiene metadatos protegidos (`_genesis_description`, `_edit_lock`, `_thumbnail_id`)
- **THEN** la respuesta de `all_metadata`/`metadata` no contiene ninguna clave que empiece por `_`

#### Scenario: Solo se exponen metadatos públicos curados

- **WHEN** se inspeccionan los metadatos devueltos por `all_metadata`/`metadata` de un podcast
- **THEN** solo aparecen las claves del conjunto curado de metadatos públicos y ninguna clave interna

#### Scenario: La lectura se restringe con auth_callback

- **WHEN** una petición intenta leer los metadatos de podcast sin cumplir la condición del `auth_callback`
- **THEN** el sistema no devuelve los metadatos restringidos y responde conforme a la autorización REST de WordPress

#### Scenario: Los nombres de campo no cambian

- **WHEN** se comparan los campos REST y de post meta antes y después del cambio
- **THEN** `all_metadata`, `metadata`, `seo_description`, `mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id` y `post_views_count` conservan su nombre

### Requirement: Verificación de nonce en el endpoint AJAX de número de capítulo

El handler AJAX `ajaxGetNextNumeroCapitulo` (hook `wp_ajax_atareao_get_next_numero_capitulo`, acción `atareao_get_next_numero_capitulo`) SHALL verificar un nonce ligado a la acción antes de realizar cualquier trabajo, además de exigir la capacidad `edit_posts`. Una petición sin nonce o con un nonce inválido SHALL rechazarse con error (HTTP 403) y SHALL NOT calcular ni devolver el siguiente número de capítulo. El script del editor que invoca la acción SHALL enviar el nonce. El nombre del hook, el nombre de la acción y el contrato de respuesta (`{ next }` en caso de éxito) SHALL conservarse.

#### Scenario: Petición sin nonce

- **WHEN** un usuario con `edit_posts` invoca la acción sin enviar nonce
- **THEN** el sistema rechaza la petición con error y no devuelve ningún número de capítulo

#### Scenario: Nonce inválido

- **WHEN** la petición envía un nonce que no corresponde a la acción
- **THEN** el sistema rechaza la petición con error y no ejecuta el cálculo

#### Scenario: Petición válida con nonce y capacidad

- **WHEN** un usuario con `edit_posts` invoca la acción con el nonce válido y un `tutorial_id` correcto
- **THEN** el sistema devuelve el siguiente número de capítulo en el contrato de éxito existente

#### Scenario: Sin capacidad suficiente

- **WHEN** la petición llega con nonce válido pero sin la capacidad `edit_posts`
- **THEN** el sistema rechaza la petición con error y no devuelve ningún dato

#### Scenario: El contrato del hook no cambia

- **WHEN** se revisan el hook y la acción registrados para el cálculo del número de capítulo
- **THEN** `wp_ajax_atareao_get_next_numero_capitulo` y `atareao_get_next_numero_capitulo` se conservan
