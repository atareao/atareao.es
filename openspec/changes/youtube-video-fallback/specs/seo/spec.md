# seo Delta

## ADDED Requirements

### Requirement: Resolución del vídeo del episodio con respaldo en metadato

El sistema SHALL resolver el vídeo propio de un episodio mediante una única función pública y
estática `\Atareao\SEO::embeddedVideo( $post )`, que devuelve `array{url:string,thumbnail:string}`
o `null`. La resolución SHALL probar **en este orden**:

1. El **contenido** del post (`post_content`), extrayendo el identificador de un embed de YouTube
   (`youtube.com/embed/`, `youtube.com/watch?v=`, `youtu.be/`) o una URL de Vimeo — el
   comportamiento actual, que NO SHALL alterarse.
2. Cuando el contenido no declare vídeo, el **metadato `youtube_url`** del post, siempre que su
   valor sea una URL de YouTube válida de la que se pueda extraer un identificador.

La **miniatura** SHALL derivarse del identificador de YouTube
(`https://i.ytimg.com/vi/<id>/hqdefault.jpg`). Para un vídeo de Vimeo sin miniatura conocida, la
miniatura SHALL ser cadena vacía.

El metadato `youtube_url` SHALL registrarse sobre el CPT `podcast` (y los tipos de episodio
declarados en `\Atareao\SEO::PODCAST_EPISODE_TYPES`), como valor **único** de tipo cadena,
**protegido** (no público), con `auth_callback` restrictivo y `sanitize_callback` que valide el
esquema `http(s)`.

El **enlace de vídeo al final del contenido** (`appendEpisodeVideoLink`) y la entidad
**`VideoObject`** del grafo (`appendVideoObject`) SHALL consumir la **misma** resolución que
`embeddedVideo()`, de forma que un episodio con respaldo en metadato declare ambos. Un episodio sin
vídeo en el contenido ni en el metadato SHALL NOT declarar `VideoObject` ni añadir enlace.

#### Scenario: Episodio con el vídeo embebido en el contenido (sin regresión)

- **WHEN** el `post_content` de un episodio contiene un `<iframe>` de `youtube.com/embed/<id>`
- **THEN** `embeddedVideo()` devuelve la URL `https://www.youtube.com/watch?v=<id>` y la miniatura de `<id>`

#### Scenario: Episodio sin embed en el contenido pero con metadato `youtube_url`

- **WHEN** el `post_content` no contiene ninguna URL de vídeo y el post declara `youtube_url` con una URL válida de YouTube
- **THEN** `embeddedVideo()` devuelve la URL canónica y la miniatura del identificador del metadato

#### Scenario: El respaldo en metadato alimenta el enlace de final de episodio

- **WHEN** se renderiza la página de un episodio sin embed en el contenido y con `youtube_url`
- **THEN** el contenido incluye el enlace `.episode-video-link` apuntando al vídeo del metadato

#### Scenario: El respaldo en metadato alimenta el VideoObject

- **WHEN** el grafo de datos estructurados se genera para un episodio sin embed en el contenido y con `youtube_url`
- **THEN** el grafo contiene una entidad `VideoObject` con el nombre, la descripción, la miniatura, la fecha de subida y la URL del vídeo del metadato

#### Scenario: Episodio sin embed ni metadato

- **WHEN** el `post_content` no contiene vídeo y el post no declara `youtube_url`
- **THEN** `embeddedVideo()` devuelve `null` y no se añade enlace ni `VideoObject`

#### Scenario: Metadato con URL que no es de YouTube

- **WHEN** el post declara `youtube_url` con un valor que no es una URL de YouTube válida
- **THEN** el fallback NO se toma y, si el contenido tampoco declara vídeo, `embeddedVideo()` devuelve `null`

#### Scenario: Post inexistente o de tipo no episodio

- **WHEN** se llama a `embeddedVideo()` con un identificador que no corresponde a un post
- **THEN** devuelve `null` sin provocar un error fatal
