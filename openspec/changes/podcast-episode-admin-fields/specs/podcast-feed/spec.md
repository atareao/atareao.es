## ADDED Requirements

### Requirement: Metabox de multimedia del episodio
El editor del post type `podcast` DEBE mostrar un metabox «Multimedia del episodio» con dos campos: imagen del episodio (metadato `episode-image`, ID de adjunto) y documento de capítulos (metadato `podcast-chapters`, ID de adjunto). Ambos campos DEBEN poder quedar vacíos y DEBEN poder limpiarse (MUST).

#### Scenario: Campos vacíos
- **GIVEN** un episodio sin `episode-image` ni `podcast-chapters`
- **WHEN** un usuario con `edit_post` abre su pantalla de edición
- **THEN** ve los dos campos vacíos, con botón de selección y sin vista previa
- **AND** no se muestra botón de quitar en ninguno de los dos

#### Scenario: Valores existentes
- **GIVEN** un episodio con `episode-image` = 1234 y `podcast-chapters` = 5678
- **WHEN** se abre su edición
- **THEN** se muestra la miniatura del adjunto 1234
- **AND** el campo de capítulos indica el adjunto 5678
- **AND** ambos muestran botón de quitar

### Requirement: Guardado seguro de los campos
El guardado DEBE verificar el nonce del metabox, la capacidad `edit_post` sobre el episodio y que el post type sea `podcast`, y NO DEBE ejecutarse durante autosave ni al guardar revisiones. Los valores DEBEN normalizarse con `absint` (MUST).

#### Scenario: Guardado válido
- **GIVEN** un usuario con `edit_post` y un nonce válido
- **WHEN** envía el formulario con `atareao_episode_image` = "1234" y `atareao_podcast_chapters` = "5678"
- **THEN** `episode-image` = 1234 y `podcast-chapters` = 5678

#### Scenario: Nonce inválido
- **GIVEN** un nonce ausente o incorrecto
- **WHEN** se envía el formulario
- **THEN** los metadatos quedan sin cambios

#### Scenario: Usuario sin permiso
- **GIVEN** un usuario sin `edit_post` sobre el episodio
- **WHEN** se envía el formulario
- **THEN** los metadatos quedan sin cambios

#### Scenario: Autosave
- **GIVEN** un autosave o una revisión del episodio
- **WHEN** se ejecuta el guardado
- **THEN** los metadatos quedan sin cambios

#### Scenario: Limpiar un campo
- **GIVEN** un episodio con `episode-image` = 1234
- **WHEN** un usuario con permiso envía el campo vacío
- **THEN** el metadato `episode-image` se elimina
- **AND** el feed vuelve a usar `atareao_podcast_cover` como imagen del episodio

### Requirement: Validación de los adjuntos
`episode-image` DEBE apuntar a un adjunto existente cuyo MIME empiece por `image/` (MUST). `podcast-chapters` DEBE apuntar a un adjunto existente con MIME `application/json` o `text/plain` y cuyo contenido sea un objeto JSON con un array `chapters` (MUST). Si un valor no cumple, NO DEBE guardarse y DEBE registrarse un aviso de administración para el usuario actual.

#### Scenario: Imagen válida
- **GIVEN** el adjunto 1234 es una imagen PNG
- **WHEN** se guarda `episode-image` = 1234
- **THEN** el metadato se guarda

#### Scenario: Adjunto inexistente o que no es imagen
- **GIVEN** el adjunto 9999 no existe, o el adjunto 1235 es un PDF
- **WHEN** se guarda `episode-image` con ese ID
- **THEN** el metadato no se modifica
- **AND** se añade un aviso de administración

#### Scenario: Capítulos no válidos
- **GIVEN** el adjunto 5678 es un MP3, o es un JSON sin array `chapters`
- **WHEN** se guarda `podcast-chapters` = 5678
- **THEN** el metadato no se modifica
- **AND** se añade un aviso de administración

#### Scenario: Capítulos válidos
- **GIVEN** el adjunto 5678 es un JSON con `{"chapters":[{"startTime":0,"title":"Intro"}]}`
- **WHEN** se guarda `podcast-chapters` = 5678
- **THEN** el metadato se guarda

### Requirement: Registro de metadatos para el editor
`episode-image` y `podcast-chapters` DEBEN registrarse con `register_post_meta` para el post type `podcast`, con tipo `integer`, `single` verdadero, sin exposición REST, y `auth_callback` que exija la capacidad `edit_posts` (MUST).

#### Scenario: Metadatos no expuestos por REST
- **GIVEN** un episodio publicado
- **WHEN** se consulta el endpoint REST del episodio
- **THEN** `episode-image` y `podcast-chapters` no aparecen en la respuesta

### Requirement: Compatibilidad con el feed
El feed DEBE seguir usando `episode-image` como única fuente de imagen del episodio, con fallback a `atareao_podcast_cover`, y DEBE emitir `podcast:chapters` solo cuando el adjunto referenciado sea accesible. Este change no modifica esa lógica (MUST).

#### Scenario: Regresión de imagen
- **GIVEN** un episodio con `episode-image` apuntando a una imagen y otro sin él
- **WHEN** se genera el feed
- **THEN** el primero emite `itunes:image` con la URL absoluta de su imagen
- **AND** el segundo emite la portada del programa

#### Scenario: Regresión de capítulos
- **GIVEN** un episodio con `podcast-chapters` y otro con un adjunto borrado
- **WHEN** se genera el feed
- **THEN** el primero emite `podcast:chapters` con URL absoluta y `type="application/json+chapters"`
- **AND** el segundo no emite la etiqueta