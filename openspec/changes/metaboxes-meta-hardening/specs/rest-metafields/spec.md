# REST Metafields Delta

## Purpose

Esta capability define el contrato de exposición REST de los metadatos de post que gestiona el plugin `atareao-functionality`. Fija que el registro de metadatos (`\Atareao\Metaboxes::registerMetaFields()`) se ejecute sin el error fatal que hoy lo deshabilita, que las metas internas con prefijo `_` no se divulguen por REST, que la escritura de las metas públicas exija la capacidad `edit_posts`, y que el campo `seo_description` sea de solo lectura y saneado. Conserva nombres de campos, tipos de post y claves de meta.

## ADDED Requirements

### Requirement: Registro de metadatos REST sin error fatal

`Metaboxes::registerMetaFields()` SHALL ejecutarse en el hook `init` y SHALL registrar las metas de post pasando siempre un `$post_type` de tipo string; SHALL NOT pasar un array como `$post_type` a `register_post_meta()`. El registro SHALL completarse sin lanzar `TypeError` ni error fatal en ninguna petición.

#### Scenario: El registro se ejecuta sin fatal

- **WHEN** se dispara el ciclo real de `init`
- **THEN** `registerMetaFields()` se ejecuta y no se produce ningún `TypeError` ni error fatal

#### Scenario: Los tipos múltiples se iteran, no se agrupan en un array

- **WHEN** se registran las metas de los tipos `application` y `software` (`_download_url`, `_repository_url`, `_version`)
- **THEN** cada llamada a `register_post_meta()` recibe un tipo de post string, nunca un array

#### Scenario: Las metas quedan registradas

- **WHEN** se consulta la lista de metas registradas tras `init`
- **THEN** están registradas `mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id` y `post_views_count` para sus tipos correspondientes

### Requirement: Metas internas no expuestas por REST

Las metas con prefijo `_` gestionadas por el plugin (`_download_url`, `_repository_url`, `_version`) SHALL NOT exponerse por la API REST: SHALL registrarse con `show_in_rest => false` o SHALL NOT registrarse para REST. Ninguna respuesta REST de los tipos correspondientes SHALL incluir estas claves, ni siquiera para una petición autenticada.

#### Scenario: Lectura anónima sin metas internas

- **WHEN** se solicita por REST un `application` o `software` sin autenticar
- **THEN** la respuesta no contiene `_download_url`, `_repository_url` ni `_version`

#### Scenario: Lectura autenticada sin metas internas

- **WHEN** un usuario autenticado solicita por REST un `application` o `software`
- **THEN** la respuesta sigue sin contener `_download_url`, `_repository_url` ni `_version`

#### Scenario: Argumentos de registro endurecidos

- **WHEN** se inspeccionan los argumentos con que se registran `_download_url`, `_repository_url` y `_version`
- **THEN** `show_in_rest` es `false`

### Requirement: Control de capacidad en la escritura REST de metadatos públicos

Las metas públicas registradas (`mp3-url`, `number`, `season`, `numero-capitulo`, `tutorial-id`, `post_views_count`) SHALL declarar un `auth_callback` que exija la capacidad `edit_posts` (o superior) para su escritura vía REST. Una petición de escritura sin capacidad suficiente SHALL rechazarse y SHALL NOT modificar la meta. La lectura SHALL conservarse conforme al contrato REST existente.

#### Scenario: Escritura sin capacidad suficiente

- **WHEN** un usuario sin `edit_posts` intenta escribir una meta pública (por ejemplo `post_views_count`) vía REST
- **THEN** el sistema rechaza la operación y no modifica la meta

#### Scenario: Escritura con capacidad suficiente

- **WHEN** un usuario con `edit_posts` escribe un valor válido en una meta pública vía REST
- **THEN** el valor se persiste saneado

#### Scenario: Lectura conservada

- **WHEN** se lee una meta pública por REST
- **THEN** el valor sigue disponible conforme al contrato de lectura vigente

### Requirement: Campo `metadata` acotado a claves públicas

El campo REST `metadata` del tipo `podcast` SHALL devolver únicamente el conjunto curado de metas públicas (`mp3-url`, `number`, `season`, `post_views_count`) y SHALL NOT devolver claves protegidas (prefijo `_`) ni claves internas.

#### Scenario: El campo no filtra claves internas

- **WHEN** se lee `metadata` de un podcast que tiene metas protegidas (`_genesis_description`, `_edit_lock`, `_thumbnail_id`)
- **THEN** la respuesta no contiene ninguna clave que empiece por `_`

#### Scenario: Solo claves curadas

- **WHEN** se inspeccionan las claves devueltas por `metadata`
- **THEN** solo aparecen claves del conjunto curado de metas públicas

### Requirement: `seo_description` de solo lectura y saneado

El campo REST `seo_description` SHALL ser de solo lectura: SHALL NOT declarar `update_callback`, de modo que la escritura vía REST quede deshabilitada. El valor devuelto SHALL proceder de `_genesis_description` saneado en la salida y SHALL NOT exponer la clave cruda ni otras metas protegidas. La escritura de `_genesis_description` SHALL realizarse únicamente desde el editor o el plugin de SEO. El nombre del campo `seo_description` y los tipos de post en los que se ofrece SHALL conservarse.

#### Scenario: Lectura saneada

- **WHEN** se lee `seo_description` de un post con `_genesis_description` establecido
- **THEN** devuelve la descripción saneada y no expone la clave `_genesis_description`

#### Scenario: Escritura deshabilitada

- **WHEN** una petición REST intenta escribir `seo_description`
- **THEN** la operación no modifica `_genesis_description`

#### Scenario: Post sin descripción

- **WHEN** se lee `seo_description` de un post sin `_genesis_description`
- **THEN** devuelve cadena vacía

#### Scenario: El nombre del campo no cambia

- **WHEN** se comparan los campos REST antes y después del cambio
- **THEN** `seo_description` conserva su nombre y los tipos de post en los que se ofrece

### Requirement: Conservación de contratos REST y de metadatos

Los nombres de los campos REST (`all_metadata`, `metadata`, `seo_description`), los tipos de post y los nombres de las metas registradas SHALL conservarse. No se renombra ni se elimina ningún campo ni clave de meta.

#### Scenario: Campos conservados

- **WHEN** se comparan los campos REST antes y después del cambio
- **THEN** `all_metadata`, `metadata` y `seo_description` conservan su nombre

#### Scenario: Claves conservadas

- **WHEN** se comparan las claves de meta registradas antes y después del cambio
- **THEN** conservan exactamente sus nombres
