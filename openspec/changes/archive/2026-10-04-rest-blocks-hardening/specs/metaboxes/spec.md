# Metaboxes Delta

## Purpose

Esta capability define el contrato de seguridad del módulo `\Atareao\Metaboxes` en su doble superficie de salida: el campo REST `all_metadata` que expone por la API REST y el endpoint AJAX de cálculo del número de capítulo. Fija que la exposición REST de los metadatos del podcast en `all_metadata` se limite a un conjunto curado de claves públicas —sin claves protegidas ni internas—, siendo la curación el control efectivo, y que el handler AJAX valide un nonce además de la capacidad, sin alterar los nombres de campo, el hook, la acción ni el contrato de respuesta existentes. El campo `metadata` y `Metaboxes::registerMetaFields()` quedan explícitamente fuera de alcance y no se activan.

## ADDED Requirements

### Requirement: Exposición REST acotada de metadatos de podcast

El campo REST `all_metadata` del tipo `podcast` SHALL devolver únicamente un conjunto curado de metadatos públicos del podcast y SHALL NOT devolver claves protegidas (prefijo `_`) ni claves internas o de infraestructura (por ejemplo `_edit_lock`, `_genesis_description`, `_thumbnail_id`, `_download_url`, `_repository_url`, `_version`). La defensa SHALL ser la curación de claves: el campo SHALL NOT declarar `auth_callback`, porque `register_rest_field()` de core no lo declara ni lo consume y constituiría un control aparente sin efecto; la exposición de metadatos es una decisión explícita materializada en la lista curada, no el volcado indiscriminado de todas las claves. El nombre del campo REST `all_metadata` y el del resto de campos activos (`seo_description`) SHALL conservarse; las claves protegidas SHALL NOT exponerse aunque la petición esté autenticada.

#### Scenario: Claves protegidas excluidas en la lectura anónima

- **WHEN** se solicita `GET /wp-json/wp/v2/podcast/<id>` sin autenticar y el podcast tiene metadatos protegidos (`_genesis_description`, `_edit_lock`, `_thumbnail_id`)
- **THEN** la respuesta de `all_metadata` no contiene ninguna clave que empiece por `_`

#### Scenario: Solo se exponen metadatos públicos curados

- **WHEN** se inspeccionan los metadatos devueltos por `all_metadata` de un podcast
- **THEN** solo aparecen las claves del conjunto curado de metadatos públicos y ninguna clave interna

#### Scenario: Los nombres de campo no cambian

- **WHEN** se comparan los campos REST activos antes y después del cambio
- **THEN** `all_metadata` y `seo_description` conservan su nombre

### Requirement: `metadata` y `registerMetaFields()` fuera de alcance

El campo REST `metadata` y el registro de post meta del método `Metaboxes::registerMetaFields()` SHALL NOT activarse en este change: el método SHALL permanecer definido pero sin engancharse y sin ejecutarse, porque pasa un array como `$post_type` a `register_post_meta()` —lo que en core provoca un `TypeError` fatal— y porque expondría metas protegidas (`_download_url`, `_repository_url`, `_version`) vía REST. Su corrección se abordará en un change aparte, considerando `show_in_rest => false` para las metas con prefijo `_` y `auth_callback` para `post_views_count`.

#### Scenario: `registerMetaFields` no se ejecuta en el ciclo real

- **WHEN** se dispara el ciclo real de `init`
- **THEN** `registerMetaFields()` no se ejecuta, no se registra el campo `metadata` ni ningún `register_post_meta`, y no se produce ningún error fatal

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
