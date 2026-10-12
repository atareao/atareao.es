# image-handling Delta

## ADDED Requirements

### Requirement: Single source of truth for image filter hooks

La política de imágenes del sitio SHALL ser propiedad del plugin `atareao-functionality` y registrarse desde `\Atareao\SEO::init()`. El plugin SHALL ser el **único codebase** que registra los filtros `wp_get_attachment_image_attributes`, `wp_calculate_image_sizes`, `jpeg_quality`, `wp_editor_set_quality` y `big_image_size_threshold`; el tema `atareao-theme` NO SHALL registrar ninguno de ellos. Los filtros `wp_calculate_image_sizes`, `jpeg_quality`, `wp_editor_set_quality` y `big_image_size_threshold` SHALL tener exactamente **un** callback cada uno. El filtro `wp_get_attachment_image_attributes` SHALL tener exactamente **tres** callbacks, **todos** del plugin y ninguno de otro origen.

Las prioridades relativas dentro de `wp_get_attachment_image_attributes` SHALL ser explícitas y quedar documentadas en un único fichero: `loading="lazy"` en 10, alt de respaldo en 15 y `fetchpriority` en 20. La conducta SHALL NOT depender de qué codebase haya registrado sus callbacks primero.

#### Scenario: Cobertura de callbacks por filtro

- **WHEN** se inspecciona `$wp_filter` para los cinco filtros de imagen
- **THEN** `wp_calculate_image_sizes`, `jpeg_quality`, `wp_editor_set_quality` y `big_image_size_threshold` tienen un callback cada uno, `wp_get_attachment_image_attributes` tiene tres, y **todos** pertenecen a `\Atareao\SEO` sin excepción

#### Scenario: El tema no registra filtros de imagen

- **WHEN** se busca en `wp-content/themes/atareao-theme` cualquier `add_filter` sobre los cinco filtros de imagen
- **THEN** no hay ninguno

### Requirement: Lazy loading with a fetchpriority exception

Las imágenes SHALL emitirse con `loading="lazy"` por defecto. La **primera** imagen de una página singular o de la portada SHALL emitirse con `fetchpriority="high"` y sin `loading`, para no penalizar el LCP; el resto SHALL conservar `loading="lazy"`. El atributo `alt` SHALL rellenarse con el título del post cuando el adjunto no declare ninguno.

#### Scenario: Primera imagen de una página singular

- **WHEN** se renderiza una página singular
- **THEN** la primera imagen lleva `fetchpriority="high"` y no lleva `loading`

#### Scenario: Imágenes siguientes

- **WHEN** se renderiza una página singular con más de una imagen
- **THEN** las imágenes posteriores conservan `loading="lazy"`

#### Scenario: Listados y archivos

- **WHEN** se renderiza un archivo o un listado
- **THEN** todas las imágenes llevan `loading="lazy"` y ninguna lleva `fetchpriority`

#### Scenario: Alt de respaldo

- **WHEN** una imagen se renderiza sin alt y el post actual tiene título
- **THEN** el atributo `alt` se rellena con el título del post

### Requirement: Responsive sizes policy

El atributo `sizes` SHALL derivarse de una única política evaluada sobre el contexto de la consulta: si la petición es singular, `(max-width: 768px) 100vw, 1200px`; en cualquier otro caso, `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw`. Al ser `is_singular()` exhaustivo, la política SHALL tener exactamente esas dos ramas y SHALL NOT dejar el valor de entrada sin sustituir en una de ellas por depender de un segundo callback para completarlo.

#### Scenario: Página singular

- **WHEN** se renderiza una página singular
- **THEN** el atributo `sizes` de las imágenes es `(max-width: 768px) 100vw, 1200px`

#### Scenario: Listados y archivos

- **WHEN** se renderiza un archivo o un listado
- **THEN** el atributo `sizes` de las imágenes es `(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw`

### Requirement: Image quality and big image threshold

La calidad de compresión SHALL ser 85, tanto para JPEG (`jpeg_quality`) como para las reescrituras del editor (`wp_editor_set_quality`). El umbral de reescalado de imágenes grandes (`big_image_size_threshold`) SHALL ser 2560 px.

#### Scenario: Calidad de compresión

- **WHEN** WordPress comprime un JPEG o el editor reescribe una imagen
- **THEN** la calidad aplicada es 85

#### Scenario: Umbral de imagen grande

- **WHEN** se sube una imagen de más de 2560 px
- **THEN** el umbral aplicado es 2560 px
