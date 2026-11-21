# seo Delta

## ADDED Requirements

### Requirement: URL de reproducción incrustable en el VideoObject

La entidad `VideoObject` del grafo SHALL declarar la propiedad **`embedUrl`** con la URL de un **reproductor incrustable** del **mismo vídeo** que resuelven `url` y `thumbnailUrl`: para YouTube, `https://www.youtube-nocookie.com/embed/<id>` con el **mismo identificador** que `url` y la miniatura; para Vimeo, `https://player.vimeo.com/video/<id>` con el identificador numérico del vídeo. La entidad SHALL NOT declarar `contentUrl` (no existe fichero de medios directo para vídeo alojado). La existencia o ausencia de `VideoObject` SHALL NOT cambiar respecto al comportamiento actual, y SHALL emitirse un único `VideoObject` por página.

#### Scenario: Vídeo de YouTube con embedUrl

- **WHEN** el grafo se genera para un episodio con vídeo de YouTube (en el contenido o en el metadato `youtube_url`)
- **THEN** la entidad `VideoObject` declara `embedUrl` = `https://www.youtube-nocookie.com/embed/<id>`, con el mismo `<id>` que `url` y que la miniatura

#### Scenario: Vídeo de Vimeo con embedUrl

- **WHEN** el vídeo resuelto es de Vimeo con identificador numérico
- **THEN** la entidad `VideoObject` declara `embedUrl` = `https://player.vimeo.com/video/<id>`

#### Scenario: Sin contentUrl

- **WHEN** se inspecciona la entidad `VideoObject` generada
- **THEN** NO declara la propiedad `contentUrl`

#### Scenario: Sin vídeo no hay VideoObject

- **WHEN** un episodio no declara vídeo ni en el contenido ni en el metadato
- **THEN** el grafo no contiene ninguna entidad `VideoObject`

#### Scenario: Un solo VideoObject y resto del grafo intacto

- **WHEN** se genera el grafo de una página con vídeo
- **THEN** hay exactamente una entidad `VideoObject` y las demás entidades (`WebSite`, `Person`, `PodcastEpisode`, …) no cambian
