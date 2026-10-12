# seo Delta

## ADDED Requirements

### Requirement: SEO description resolution

El sistema SHALL resolver la descripción SEO de un post mediante `\Atareao\SEO::getDescription()`, público y estático, propiedad del plugin `atareao-functionality`. La resolución SHALL probar en orden las claves de metadatos `_genesis_description` (la que usa The SEO Framework), `_yoast_wpseo_metadesc`, `rank_math_description` y `_aioseo_description`, y SHALL devolver la primera no vacía. Si ninguna lo está, SHALL devolver el extracto (`post_excerpt`) del post. Si el post no existe, SHALL devolver cadena vacía. El resultado SHALL cachearse estáticamente por ID de post dentro de la misma petición.

El tema `atareao-theme` NO SHALL contener la lógica de resolución. Podrá exponer un adaptador con el nombre histórico `atareao_get_seo_description()` que delegue en el plugin y que, si el plugin no está presente, devuelva el extracto sin provocar un error fatal.

#### Scenario: Descripción declarada en el plugin SEO

- **WHEN** el post declara una descripción en `_genesis_description` y hay otras claves presentes
- **THEN** se devuelve el valor de `_genesis_description`

#### Scenario: Varias claves presentes

- **WHEN** el post declara descripción en más de una de las cuatro claves
- **THEN** se devuelve la de la primera clave del orden declarado

#### Scenario: Sin descripción declarada

- **WHEN** el post no declara ninguna de las cuatro claves
- **THEN** se devuelve su `post_excerpt`

#### Scenario: Post inexistente

- **WHEN** se pide la descripción de un ID que no corresponde a ningún post
- **THEN** se devuelve cadena vacía

#### Scenario: Adaptador del tema sin el plugin

- **WHEN** el tema llama a `atareao_get_seo_description()` y la clase `\Atareao\SEO` no existe
- **THEN** se devuelve el extracto del post sin provocar un error fatal
