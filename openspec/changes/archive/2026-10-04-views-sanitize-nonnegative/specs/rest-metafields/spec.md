# rest-metafields Delta

## Purpose

Este delta endurece el saneado de `post_views_count` en la capability `rest-metafields`: además de tolerar la firma de cuatro argumentos de `sanitize_meta()` y de no usar funciones internas de PHP, el valor persistido SHALL ser un entero no negativo. No cambia ningún otro contrato de la capability (registro sin fatal, metas internas fuera de REST, capacidad de escritura, `metadata` acotado, `seo_description` de solo lectura).

## MODIFIED Requirements

### Requirement: Saneado compatible con la firma de `sanitize_meta()`

Los `sanitize_callback` declarados por el plugin SHALL tolerar los cuatro argumentos con que `sanitize_meta()` invoca al callback (`$value`, `$meta_key`, `$meta_type`, `$object_subtype`). SHALL NOT usar funciones internas de PHP que rechacen argumentos adicionales (por ejemplo, `intval`), porque en PHP 8 lanzan `ArgumentCountError` y provocan un error fatal. En particular, `update_post_meta('post_views_count', …)` SHALL completarse sin error fatal y SHALL persistir el valor saneado. El valor saneado de `post_views_count` SHALL ser un entero **no negativo**: un valor negativo SHALL persistirse como `0` y un valor no numérico SHALL persistirse como `0`.

#### Scenario: `update_post_meta` no provoca fatal

- **WHEN** se ejecuta `update_post_meta('post_views_count', 5)` sobre un post con la meta registrada
- **THEN** la escritura se completa sin `ArgumentCountError` y persiste el valor saneado

#### Scenario: La guardia reproduce el `ArgumentCountError` si el callback es `intval`

- **WHEN** el `sanitize_callback` de `post_views_count` es la función interna `intval` y `sanitize_meta()` la invoca con sus cuatro argumentos
- **THEN** se produce un `ArgumentCountError` («expects at most 2 arguments») y la guardia lo detecta

#### Scenario: Valor negativo se persiste como cero

- **WHEN** se escribe un valor negativo (por ejemplo `-7`) en `post_views_count`
- **THEN** el valor persistido es `0`

#### Scenario: Valor no numérico se persiste como cero

- **WHEN** se escribe un valor no numérico en `post_views_count`
- **THEN** el valor persistido es `0`
