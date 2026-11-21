# seo Delta

## ADDED Requirements

### Requirement: URL de reproducción incrustable en el VideoObject

La entidad `VideoObject` del grafo SHALL declarar la propiedad **`embedUrl`** con la URL de un **reproductor incrustable** del **mismo vídeo** que resuelven `url` y `thumbnailUrl`: para YouTube, `https://www.youtube-nocookie.com/embed/<id>` con el **mismo identificador** que `url` y la miniatura; para Vimeo, `https://player.vimeo.com/video/<id>` con el identificador numérico del vídeo. La entidad SHALL NOT declarar `contentUrl`. La existencia o ausencia de `VideoObject` SHALL NOT cambiar, y SHALL emitirse un único `VideoObject` por página.

#### Scenario: Vídeo de YouTube con embedUrl

- **WHEN** el grafo se genera para un episodio con vídeo de YouTube (en el contenido o en el metadato `youtube_url`)
- **THEN** la entidad `VideoObject` declara `embedUrl` = `https://www.youtube-nocookie.com/embed/<id>`, con el mismo `<id>` que `url` y la miniatura

#### Scenario: Vídeo de Vimeo con embedUrl

- **WHEN** el vídeo resuelto es de Vimeo con identificador numérico
- **THEN** la entidad `VideoObject` declara `embedUrl` = `https://player.vimeo.com/video/<id>`

#### Scenario: Sin contentUrl

- **WHEN** se inspecciona la entidad `VideoObject` generada
- **THEN** NO declara la propiedad `contentUrl`

#### Scenario: Sin vídeo no hay VideoObject

- **WHEN** un episodio no declara vídeo ni en el contenido ni en el metadato
- **THEN** el grafo no contiene ninguna entidad `VideoObject`

### Requirement: Duración del vídeo en ISO 8601

La entidad `VideoObject` SHALL declarar la propiedad **`duration`** en formato **ISO 8601** (`PT#H#M#S`, omitiendo los componentes a cero) cuando el episodio tenga una **duración conocida**: la misma duración canónica que el feed emite como `<itunes:duration>`, obtenida por el accesor público de `PodcastFeed`. Cuando la duración falte, sea `00:00:00` o esté malformada, la propiedad SHALL **omitirse** sin adivinar. La duración declarada SHALL ser la del episodio.

#### Scenario: Duración en ISO 8601

- **WHEN** el episodio declara la duración `00:22:04`
- **THEN** el `VideoObject` declara `duration` = `PT22M4S`

#### Scenario: Duración con horas

- **WHEN** el episodio declara la duración `01:02:03`
- **THEN** el `VideoObject` declara `duration` = `PT1H2M3S`

#### Scenario: Sin duración no se declara la propiedad

- **WHEN** el episodio no declara duración, la declara como `00:00:00` o con un valor malformado
- **THEN** el `VideoObject` NO declara la propiedad `duration`

#### Scenario: La duración del grafo coincide con la del feed

- **WHEN** un episodio declara `duration` en `HH:MM:SS`
- **THEN** el `itunes:duration` del feed y el `duration` del `VideoObject` representan la misma duración

### Requirement: Miniatura de máxima resolución con reserva garantizada

La propiedad `thumbnailUrl` de la entidad `VideoObject` SHALL declarar la **mejor miniatura 16:9 disponible** del vídeo, prefiriendo `maxresdefault.jpg`, después `hq720.jpg` y con **reserva garantizada** `hqdefault.jpg`. La disponibilidad SHALL resolverse con una comprobación **cacheada** y **con tiempo límite**, y el proceso SHALL NOT fallar ni bloquear la composición de la página: ante cualquier error, tiempo de espera agotado o respuesta anómala SHALL emitirse `hqdefault.jpg`. La miniatura SHALL pertenecer a un host de imágenes permitido y SHALL corresponder al **mismo identificador** de vídeo que `url` y `embedUrl`.

#### Scenario: Existe la miniatura máxima

- **WHEN** la miniatura `maxresdefault.jpg` está disponible
- **THEN** `thumbnailUrl` = `https://i.ytimg.com/vi/<id>/maxresdefault.jpg`

#### Scenario: Sin maxres pero con hq720

- **WHEN** `maxresdefault.jpg` no está disponible y `hq720.jpg` sí
- **THEN** `thumbnailUrl` = `https://i.ytimg.com/vi/<id>/hq720.jpg`

#### Scenario: Reserva garantizada ante fallo, error o caché vacía

- **WHEN** ninguna comprobación concluye que las variantes de alta resolución existen, o la comprobación falla o excede el tiempo límite
- **THEN** `thumbnailUrl` = `https://i.ytimg.com/vi/<id>/hqdefault.jpg` y la página se compone con normalidad

#### Scenario: La comprobación no se repite dentro de la caché

- **WHEN** se genera el grafo dos veces para el mismo vídeo dentro del periodo de caché
- **THEN** no se repite la petición de comprobación

#### Scenario: La miniatura de la fachada visible no cambia

- **WHEN** se representa un episodio con vídeo
- **THEN** la fachada visible sigue usando la miniatura `hqdefault.jpg` (sin cambios en el reproductor)
