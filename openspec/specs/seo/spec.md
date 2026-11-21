# seo Specification

## Purpose
Esta capacidad define la resolución de la descripción SEO de un post —a partir de las claves de metadatos de los plugins SEO y del extracto como respaldo— como responsabilidad del plugin `atareao-functionality`. El tema `atareao-theme` no contiene esa lógica y, si expone el adaptador histórico, este se limita a delegar en el plugin.

## Requirements

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

### Requirement: Integración con The SEO Framework por sus filtros reales

El plugin SHALL integrarse con The SEO Framework **únicamente** por los filtros que TSF 5.2.1 dispara
de verdad. En concreto:

- Para el título, SHALL registrar `the_seo_framework_title_from_generation` con un callback de firma
  `filterGeneratedTitle( string $title, ?array $args ): string`, aceptando **2 argumentos**. El
  segundo argumento SHALL tratarse como **anulable** (`?array`), con las claves `id`, `tax`, `pta` y
  `uid` posibles y `uid` pudiendo ser `null`; el callback SHALL NOT desreferenciar claves ausentes.
- Para la descripción, SHALL registrar `the_seo_framework_generated_description` con un callback de
  firma `filterGeneratedDescription( string $desc, ?array $args, string $type ): string`, aceptando
  **3 argumentos**, donde `$type` ∈ `search|opengraph|twitter`.
- Para el sufijo de marca, SHALL registrar `the_seo_framework_use_title_branding` con un callback de
  firma `filterUseTitleBranding( bool $use, ?array $args, bool $social ): bool`, aceptando **3
  argumentos**.
- El plugin SHALL NOT registrar `the_seo_framework_title` ni `the_seo_framework_description` (no
  existen en TSF 5.2.1).
- El plugin SHALL NOT modificar los hooks `the_seo_framework_schema_graph_data` y
  `the_seo_framework_sitemap_exclude_ids`, que sí existen y ya funcionan.

El callback de título SHALL devolver el **valor recibido sin cambios** salvo cuando la **petición
actual** sea la portada o una de las plantillas de listado cubiertas, evaluadas con los condicionales
de WordPress (`is_front_page()`, `is_home()` y el archivo de tipo de contenido correspondiente); en
esas superficies SHALL devolver el título curado, de **60 caracteres o menos**. El callback SHALL
devolver el valor recibido sin cambios en cualquier otro contexto, y en particular cuando
`is_admin()`, una petición AJAX, un feed, una petición REST, la generación de un sitemap o `robots`
estén activos.

**Nota (ruta auto-determinada).** En la ruta auto-determinada —la habitual en la portada— TSF
`get_bare_generated_title( $args = null )` llama al filtro con `$args` igual a **`null`**
(`inc/classes/meta/title.class.php`). Recibir `$args = null` **no** SHALL desactivar las superficies
cubiertas: la decisión SHALL tomarse por la petición actual, no por la presencia de `$args`. La
cláusula «identidad cuando `$args` es `null`» queda **prohibida** por anular el caso normal de la
portada.

El callback de descripción SHALL devolver la descripción curada en la portada y en las plantillas de
listado cubiertas para **los tres valores de `$type`**, y el valor recibido sin cambios en cualquier
otro contexto.

#### Scenario: El título de la portada lo reescribe el filtro real

- **WHEN** TSF genera el título de la portada y se ejecuta `filterGeneratedTitle()`
- **THEN** devuelve el título curado de la portada, de 60 caracteres o menos

#### Scenario: El título de un listado lo reescribe el filtro real

- **WHEN** TSF genera el título de `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` o `/software/`
- **THEN** devuelve el título curado de esa superficie

#### Scenario: Un contenido singular no se altera

- **WHEN** TSF genera el título de un post, un tutorial o un episodio
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

#### Scenario: Un contexto ajeno al front-end no se altera

- **WHEN** TSF genera un título en el admin, en una petición AJAX, en un feed, en una petición REST, en un sitemap o para `robots`
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

#### Scenario: La ruta auto-determinada no desactiva las superficies cubiertas

- **WHEN** el callback recibe `$args` como `null` (ruta auto-determinada, como la portada) y la petición actual es una superficie cubierta
- **THEN** devuelve el título curado de esa superficie y no el valor recibido

#### Scenario: La descripción curada se aplica a los tres tipos de generación

- **WHEN** TSF genera la descripción de la portada o de un listado con `$type` igual a `search`, `opengraph` o `twitter`
- **THEN** devuelve la descripción curada de esa superficie

#### Scenario: La descripción fuera de alcance no se altera

- **WHEN** TSF genera la descripción de un contenido singular, de un feed o de un sitemap
- **THEN** el callback devuelve el mismo valor que recibe, sin cambios

### Requirement: Sufijo de marca del título en las superficies cubiertas

El sistema SHALL NOT emitir el sufijo de marca (las adiciones que TSF decide en
`inc/classes/meta/title/conditions.class.php::use_branding()`, p. ej. « - atareao con Linux» en los
listados y el *tagline* de la portada) en la portada ni en las plantillas de listado cubiertas. El
mecanismo SHALL ser el filtro real `the_seo_framework_use_title_branding`, cuyo callback SHALL
devolver **`false`** únicamente cuando la petición actual sea una superficie cubierta, y SHALL
devolver **el valor recibido sin cambios** (identidad) en el resto de superficies, de modo que un
post, un tutorial o un episodio **conservan** su sufijo de marca.

El plugin SHALL NOT modificar el ajuste global `title_rem_additions` de TSF (hoy `0`) ni ningún otro
ajuste de TSF. El resultado SHALL ser reproducible desde el repositorio y SHALL NOT depender de un
paso de datos.

#### Scenario: Las superficies cubiertas no llevan sufijo de marca

- **WHEN** se inspecciona el `<title>` de la portada, `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` o `/software/`
- **THEN** ninguno termina en el sufijo de marca del sitio

#### Scenario: Un post conserva su sufijo de marca

- **WHEN** el callback de sufijo recibe la generación del título de un post
- **THEN** devuelve el valor recibido sin cambios y el título conserva el sufijo de marca de TSF

#### Scenario: Un episodio conserva su sufijo de marca

- **WHEN** el callback de sufijo recibe la generación del título de un episodio
- **THEN** devuelve el valor recibido sin cambios y el título conserva el sufijo de marca de TSF

#### Scenario: El ajuste global de TSF no se modifica

- **WHEN** se comparan los ajustes de TSF antes y después de aplicar el change
- **THEN** `title_rem_additions` y el resto de ajustes de TSF permanecen idénticos

### Requirement: Respeto a los ajustes de TSF de la portada

Cuando TSF tenga definido un título de portada propio en sus ajustes (p. ej. `homepage_title`), ese
valor SHALL prevalecer y el plugin SHALL NOT pisarlo: la ruta *custom* de TSF gana sobre la generada
y el plugin no interviene en ese caso. El change SHALL NOT escribir ningún ajuste de TSF y SHALL
resolver el resultado por **código** (los filtros reales), no por datos.

#### Scenario: Un título de portada definido en TSF gana

- **WHEN** el ajuste `homepage_title` de TSF está definido y se solicita la portada
- **THEN** se sirve el valor del ajuste de TSF y el plugin no lo pisa

#### Scenario: El change no escribe ajustes de TSF

- **WHEN** se inspeccionan los ajustes de TSF tras aplicar el change
- **THEN** ninguno ha sido escrito ni modificado por el plugin

### Requirement: Guardia contra hooks de SEO inexistentes

El proyecto SHALL mantener una **lista verificada** de hooks `the_seo_framework_*` que el plugin puede
registrar, versionada en el repositorio junto al arnés de tests. La lista SHALL ser exactamente la
verificada contra TSF 5.2.1:

- `the_seo_framework_title_from_generation` (`inc/classes/meta/title.class.php:237-241`)
- `the_seo_framework_generated_description` (`inc/classes/meta/description.class.php:176-181`)
- `the_seo_framework_use_title_branding` (`inc/classes/meta/title/conditions.class.php:172-176`)
- `the_seo_framework_schema_graph_data` (`inc/classes/meta/schema.class.php:143`)
- `the_seo_framework_sitemap_exclude_ids` (`inc/classes/sitemap/utils.class.php:97`)

Los hooks `the_seo_framework_title` y `the_seo_framework_description` SHALL constar como
**prohibidos** (no existen en TSF 5.2.1). Un test SHALL fallar si el plugin registra un hook
`the_seo_framework_*` que no figure en la lista, de modo que un hook inexistente no pueda volver a
introducirse sin ser detectado.

El test SHALL ejecutarse sin TSF instalado (como en CI) comprobando que **cada hook que registra el
plugin pertenece a la lista**. Un **paso de verificación en producción**, con TSF instalado, SHALL
comprobar que **cada hook de la lista aparece en el código de TSF** (ocurrencia de `apply_filters`), y
SHALL reportar como fallo cualquier hook de la lista que TSF no dispare.

El plugin SHALL cargar y funcionar **sin error fatal** cuando TSF no está instalado: los filtros
quedan inertes y el resto del plugin opera con normalidad.

#### Scenario: Un hook registrado fuera de la lista rompe el test

- **WHEN** el plugin registra un hook `the_seo_framework_*` que no está en la lista verificada
- **THEN** el test falla e identifica el hook

#### Scenario: La guardia pasa sin TSF instalado

- **WHEN** se ejecuta el test en CI, sin TSF instalado, y todos los hooks registrados pertenecen a la lista
- **THEN** el test pasa

#### Scenario: La lista coincide con el TSF instalado

- **WHEN** se ejecuta el paso de verificación en producción con TSF 5.2.1
- **THEN** cada hook de la lista aparece en el código de TSF y la verificación pasa

#### Scenario: El plugin sin TSF no provoca error fatal

- **WHEN** el plugin se carga sin TSF instalado
- **THEN** carga sin error fatal y los filtros de TSF quedan inertes

### Requirement: Títulos y descripciones curados de la portada y los listados

El sistema SHALL emitir el `<title>` de la portada con una longitud de **60 caracteres o menos**,
contando lo que sirva TSF **con el sufijo de marca ya eliminado en esa superficie**. El sistema SHALL
emitir, en `/blog/` y en cada una de las cuatro plantillas de listado (`/tutoriales/`, `/podcast/`,
`/aplicaciones/` y `/software/`), un `<title>` curado (sin sufijo de marca) y una `<meta
name="description">` curada y no vacía, procedentes de las tablas de títulos y descripciones del
plugin, con una longitud de entre **120 y 160 caracteres** para la descripción. Ningún `<title>` de
esas superficies SHALL terminar en el sufijo de marca del sitio.

#### Scenario: El título de la portada cabe en la SERP

- **WHEN** se cuenta la longitud del `<title>` servido en la portada
- **THEN** es de 60 caracteres o menos

#### Scenario: El blog tiene título y descripción curados

- **WHEN** se inspecciona el `<title>` y la `<meta name="description">` servidos en `/blog/`
- **THEN** ambos son los curados de la superficie y la descripción mide entre 120 y 160 caracteres

#### Scenario: Las cuatro plantillas tienen título y descripción curados

- **WHEN** se inspecciona el `<title>` y la `<meta name="description">` de `/tutoriales/`, `/podcast/`, `/aplicaciones/` y `/software/`
- **THEN** cada superficie sirve su título y su descripción curados, y ninguno termina en el sufijo de marca

### Requirement: La descripción curada se comparte con las tarjetas sociales

En la portada y en las plantillas de listado cubiertas, la descripción servida en `<meta
name="description">`, `og:description` y `twitter:description` SHALL ser **la misma cadena curada**.

#### Scenario: Las tres descripciones coinciden

- **WHEN** se comparan `<meta name="description">`, `og:description` y `twitter:description` de `/blog/`
- **THEN** las tres son idénticas a la descripción curada de la superficie

### Requirement: Archivo de autor canónico y redirección del nicename heredado

El autor cuyo `display_name` es `atareao` SHALL tener `user_nicename` igual a `atareao`, de forma que
`GET /author/atareao/` responda **200** con el archivo de ese autor. El cambio de `user_nicename`
SHALL realizarse con un **paso de datos idempotente**: ejecutarlo una segunda vez no SHALL modificar
ningún dato.

`GET /author/yyyxfsgj/` (nicename heredado) SHALL responder un **301** hacia la URL canónica del
archivo, en **una sola redirección**, sin bucle: la URL canónica no SHALL redirigirse a sí misma. La
redirección SHALL resolverse en `template_redirect`, detectando el **path** de la petición heredada
bajo el prefijo `^/author/yyyxfsgj(/|$)`, **preservando el sufijo de subruta** (`/page/2/`, `/feed/`,
etc.) **y la query string**, y SHALL NOT aplicarse a ningún otro nicename. Una subruta ya canónica
(`/author/atareao/page/2/`) SHALL NOT redirigirse.

#### Scenario: La URL canónica responde

- **WHEN** se solicita `GET /author/atareao/`
- **THEN** la respuesta es 200 y es el archivo del autor 3

#### Scenario: El nicename heredado redirige a la canónica

- **WHEN** se solicita `GET /author/yyyxfsgj/`
- **THEN** la respuesta es 301 con `Location` apuntando a la URL canónica del archivo

#### Scenario: Las subrutas del nicename heredado se preservan

- **WHEN** se solicita `GET /author/yyyxfsgj/page/2/` o `GET /author/yyyxfsgj/feed/`
- **THEN** la respuesta es un único 301 con `Location` en `/author/atareao/page/2/` o
  `/author/atareao/feed/` respectivamente

#### Scenario: La query string se preserva

- **WHEN** se solicita `GET /author/yyyxfsgj/?feed=rss2`
- **THEN** la respuesta es un único 301 con `Location` en `/author/atareao/?feed=rss2`

#### Scenario: La subruta canónica no se redirige

- **WHEN** se solicita `GET /author/atareao/page/2/`
- **THEN** no se emite redirección y la petición sigue su curso

#### Scenario: La redirección no entra en bucle

- **WHEN** se sigue el 301 de `/author/yyyxfsgj/`
- **THEN** la URL canónica responde 200 sin volver a redirigir

#### Scenario: El paso de datos es idempotente

- **WHEN** se ejecuta el paso de datos una segunda vez
- **THEN** no modifica el `user_nicename` y no produce efecto alguno

### Requirement: No regresión fuera de las superficies cubiertas

En las superficies no cubiertas —un post, un tutorial, un episodio, el feed principal `/feed/`, un
sitemap y el panel de administración— el título y la descripción generados por TSF SHALL permanecer
**idénticos** a los que TSF produce sin el plugin actuando: la integración SHALL NOT alterarlos.

#### Scenario: Un post conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un post con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: Un tutorial conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un tutorial con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: Un episodio conserva su título y descripción

- **WHEN** se compara el `<title>` y la `<meta name="description">` de un episodio con los que TSF genera sin la intervención del plugin
- **THEN** son idénticos

#### Scenario: El feed principal no se altera

- **WHEN** se inspecciona el feed principal `/feed/`
- **THEN** sus títulos y descripciones no cambian respecto a la generación de TSF

#### Scenario: Un sitemap no se altera

- **WHEN** se inspecciona un sitemap
- **THEN** su contenido no cambia respecto a la generación de TSF

#### Scenario: El admin no se altera

- **WHEN** se inspecciona el título y la descripción en el panel de administración
- **THEN** no cambian respecto a la generación de TSF

### Requirement: Redirección alcanzable de `/sitemap_index.xml`

El plugin SHALL resolver la petición a `/sitemap_index.xml` con un **301** hacia `/sitemap.xml`
(el inventario real), y SHALL hacerlo desde un hook que se ejecute **antes** del despacho del endpoint
de sitemap de The SEO Framework —`parse_request` prioridad **15**, `bootstrap/init-front.php:44` en TSF
5.2.1—, de modo que la redirección no quede inalcanzable. La decisión de destino SHALL residir en una
única función (`sitemapRedirectTarget`).

El plugin SHALL NOT redirigir `/sitemap.xml` ni las rutas normales, SHALL NOT alterar el contenido de
`/sitemap.xml` y SHALL NOT depender de desactivar funciones de TSF.

El proyecto SHALL mantener una **guardia de orden** que falle si el callback de la redirección se
registra en un hook o prioridad que no preceda al despacho de TSF, para que un falso verde como el que
ocultó este fallo no pueda reintroducirse.

#### Scenario: `/sitemap_index.xml` redirige al inventario real

- **WHEN** se sigue una petición a `/sitemap_index.xml`
- **THEN** la respuesta es un **301** con `Location` en `/sitemap.xml`

#### Scenario: El inventario real y las rutas normales no se redirigen

- **WHEN** se inspecciona la decisión para `/sitemap.xml` o para una ruta normal
- **THEN** no hay redirección

#### Scenario: La redirección precede al despacho de TSF

- **WHEN** se inspeccionan los hooks registrados por el plugin
- **THEN** el callback de la redirección está en `parse_request` con prioridad anterior a 15

#### Scenario: El sitemap real no se altera

- **WHEN** se solicita `/sitemap.xml`
- **THEN** se sirve con **200** y su contenido no cambia por este comportamiento

### Requirement: Resolución del vídeo del episodio con respaldo en metadato

El sistema SHALL resolver el vídeo propio de un episodio mediante una única función pública y
estática `\Atareao\SEO::embeddedVideo( $post )`, que devuelve
`array{url:string,id:string,thumbnail:string,source:string}` o `null`. La resolución SHALL probar
**en este orden**:

1. El **contenido** del post (`post_content`), extrayendo el identificador de un embed de YouTube
   (`youtube.com/embed/`, `youtube.com/watch?v=`, `youtu.be/`) o una URL de Vimeo — el
   comportamiento actual, que NO SHALL alterarse.
2. Cuando el contenido no declare vídeo, el **metadato `youtube_url`** del post, siempre que su
   valor sea una URL de YouTube válida de la que se pueda extraer un identificador.

El retorno SHALL declarar además **el identificador de YouTube** (`id`; cadena vacía para un vídeo de
Vimeo, sin id de YouTube) y **el origen** (`source`: `content` si el vídeo proviene del contenido,
`meta` si proviene del metadato). Ambos campos son aditivos y NO SHALL alterar a los consumidores
existentes.

La **miniatura** SHALL derivarse del identificador de YouTube
(`https://i.ytimg.com/vi/<id>/hqdefault.jpg`). Para un vídeo de Vimeo sin miniatura conocida, la
miniatura SHALL ser cadena vacía.

El metadato `youtube_url` SHALL registrarse sobre el CPT `podcast` (y los tipos de episodio
declarados en `\Atareao\SEO::PODCAST_EPISODE_TYPES`), como valor **único** de tipo cadena y
**no expuesto en la API REST** (`show_in_rest` desactivado), con `sanitize_callback` que valide el
esquema `http(s)`. WordPress no aplica `auth_callback` en las lecturas REST, por lo que la única
garantía real de no-exposición es desactivar `show_in_rest`; el metadato solo se rellena por WP-CLI.

El **reproductor al final del contenido** (`appendEpisodeVideoPlayer`) y la entidad **`VideoObject`**
del grafo (`appendVideoObject`) SHALL consumir la **misma** resolución que `embeddedVideo()`, de
forma que un episodio con respaldo en metadato declare ambos. Un episodio sin vídeo en el contenido
ni en el metadato SHALL NOT declarar `VideoObject` ni añadir reproductor.

#### Scenario: Episodio con el vídeo embebido en el contenido (sin regresión)

- **WHEN** el `post_content` de un episodio contiene un `<iframe>` de `youtube.com/embed/<id>`
- **THEN** `embeddedVideo()` devuelve la URL `https://www.youtube.com/watch?v=<id>`, la miniatura de `<id>`, ese `<id>` como `id` y `content` como `source`

#### Scenario: Episodio sin embed en el contenido pero con metadato `youtube_url`

- **WHEN** el `post_content` no contiene ninguna URL de vídeo y el post declara `youtube_url` con una URL válida de YouTube
- **THEN** `embeddedVideo()` devuelve la URL canónica, la miniatura del identificador del metadato, ese identificador como `id` y `meta` como `source`

#### Scenario: El respaldo en metadato alimenta el enlace de final de episodio

- **WHEN** se renderiza la página de un episodio sin embed en el contenido y con `youtube_url`
- **THEN** el contenido incluye la fachada `.episode-video` (el enlace de vídeo al final del episodio) con el identificador del metadato

#### Scenario: El respaldo en metadato alimenta el VideoObject

- **WHEN** el grafo de datos estructurados se genera para un episodio sin embed en el contenido y con `youtube_url`
- **THEN** el grafo contiene una entidad `VideoObject` con el nombre, la descripción, la miniatura, la fecha de subida y la URL del vídeo del metadato

#### Scenario: Episodio sin embed ni metadato

- **WHEN** el `post_content` no contiene vídeo y el post no declara `youtube_url`
- **THEN** `embeddedVideo()` devuelve `null` y no se añade reproductor ni `VideoObject`

#### Scenario: Metadato con URL que no es de YouTube

- **WHEN** el post declara `youtube_url` con un valor que no es una URL de YouTube válida
- **THEN** el fallback NO se toma y, si el contenido tampoco declara vídeo, `embeddedVideo()` devuelve `null`

#### Scenario: Post inexistente o de tipo no episodio

- **WHEN** se llama a `embeddedVideo()` con un identificador que no corresponde a un post
- **THEN** devuelve `null` sin provocar un error fatal

### Requirement: Reproductor del vídeo del episodio incrustado (fachada)

El sistema SHALL mostrar el vídeo propio del episodio **incrustado en la propia página del episodio**,
al final del contenido, en lugar de solo enlazarlo. El reproductor SHALL ser una **fachada**
—miniatura y botón de reproducción— que SHALL cargar el `<iframe>` del vídeo **solo al pulsar**
(nunca al abrir la página), y SHALL cargarlo desde el dominio de privacidad mejorada
`www.youtube-nocookie.com`.

La fachada SHALL emitirse mediante la función pública `\Atareao\SEO::appendEpisodeVideoPlayer()`
enganchada a `the_content`, **únicamente** cuando la página es el singular de un episodio
(`PODCAST_EPISODE_TYPES`) y la resolución del vídeo proviene del **metadato** (`source === 'meta'`).
Cuando el vídeo proviene del **contenido** (`source === 'content'`) NO SHALL emitirse la fachada, para
no duplicar el reproductor que el cuerpo ya incrusta.

La fachada SHALL ser un enlace `<a>` a `https://www.youtube.com/watch?v=<id>` con `rel="noopener"` que
contenga la miniatura (`<img>`, `loading="lazy"`, `decoding="async"`, dimensiones explícitas) y el
botón de reproducción. **Sin JavaScript** el enlace SHALL seguir navegando a YouTube (respaldo
funcional y accesible). Con JavaScript, al pulsar SHALL evitarse la navegación y el enlace SHALL
sustituirse por el `<iframe>` de `youtube-nocookie.com` con `allowfullscreen`.

Los assets (`assets/css/episode-video.css` y `assets/js/episode-video.js`) SHALL registrarse
versionados con `ATAREAO_PLUGIN_VERSION` y SHALL encolarse **solo** en el singular de un episodio con
vídeo de metadato, nunca en listados, portada ni páginas sin vídeo.

La Cabecera de Seguridad de Contenido de producción, definida y versionada en `nginx/pro/wp.conf`,
SHALL incluir `https://i.ytimg.com` en `img-src` para permitir la miniatura, y SHALL permitir el
origen del `<iframe>` en `frame-src`.

#### Scenario: La página del episodio muestra la fachada del vídeo

- **GIVEN** un episodio sin embed en el contenido y con `youtube_url`
- **WHEN** se renderiza su página singular
- **THEN** el contenido incluye `.episode-video` con `data-video-id` igual al identificador, la miniatura de YouTube y un enlace de respaldo a la URL canónica

#### Scenario: Sin JavaScript el vídeo sigue siendo accesible

- **GIVEN** la fachada emitida para un episodio con vídeo de metadato
- **WHEN** se inspecciona el marcado sin ejecutar JavaScript
- **THEN** la fachada es un `<a>` a `https://www.youtube.com/watch?v=<id>` con `rel="noopener"` que contiene la miniatura

#### Scenario: El vídeo incrustado en el cuerpo no se duplica

- **GIVEN** un episodio cuyo `post_content` ya incrusta el vídeo (una URL de YouTube)
- **WHEN** se renderiza su página singular
- **THEN** NO se añade la fachada `.episode-video` al final del contenido

#### Scenario: Los assets solo se encolan en el singular con vídeo de metadato

- **GIVEN** la resolución del vídeo de la petición en curso
- **WHEN** la petición es el singular de un episodio con vídeo de metadato
- **THEN** el CSS y el JS de la fachada se encolan
- **AND** en un listado, la portada, o un episodio sin vídeo, NO se encolan

#### Scenario: La CSP permite la miniatura y el reproductor incrustado

- **WHEN** se inspecciona la directiva `Content-Security-Policy` de `nginx/pro/wp.conf`
- **THEN** `img-src` incluye `https://i.ytimg.com` y `frame-src` admite el origen del `<iframe>`
