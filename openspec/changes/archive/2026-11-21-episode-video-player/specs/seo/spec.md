# seo Delta

## MODIFIED Requirements

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

## ADDED Requirements

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
