# Tasks — podcast-episode-admin-fields

Issue: #248

## 1. Preparación (RED)
- [x] 1.1 Crear `tests/podcast-episode-admin-fields/test_admin_fields.php` siguiendo el estilo de `tests/podcast-srt-upload/`.
- [x] 1.2 Tests de guardado: nonce válido/inválido, sin `edit_post`, autosave, post type distinto, limpieza de campo (escenarios de *Guardado seguro*).
- [x] 1.3 Tests de validación: imagen válida, adjunto inexistente, MIME no imagen, JSON válido, JSON sin `chapters`, MIME no JSON (escenarios de *Validación de los adjuntos*).
- [x] 1.4 Test de registro de metadatos: tipo `integer`, `single`, `show_in_rest` falso y `auth_callback` con `edit_posts`.
- [x] 1.5 Test de render: el metabox aparece solo en `podcast` y escapa los valores.
- [x] 1.6 Ejecutar `just test` y confirmar que los tests nuevos FALLAN y el resto sigue verde.

## 2. Implementación (GREEN)
- [x] 2.1 En `class-metaboxes.php`: `register_post_meta('podcast', 'episode-image'|'podcast-chapters', [...])` integer/single/`show_in_rest=false`.
- [x] 2.2 Método de render del metabox (nonce, capability, `absint`, `esc_attr`, `wp_get_attachment_image`).
- [x] 2.3 Hook `save_post_podcast` con verificaciones de nonce, capability, autosave y post type.
- [x] 2.4 Validadores: `isValidEpisodeImage(int): bool` (MIME `image/*`) y `isValidChaptersDocument(int): bool` (MIME JSON/plain + `chapters` array).
- [x] 2.5 Avisos de administración vía `add_settings_error`/transient cuando un valor se rechaza.
- [x] 2.6 Asset `assets/js/podcast-media.js` con `wp.media` (image para la imagen, sin filtro de tipo para el JSON), enqueue solo en `post.php`/`post-new.php` con `get_current_screen()->post_type === 'podcast'`.
- [x] 2.7 Ejecutar `just test` → todo verde.

## 3. Refactor y calidad
- [x] 3.1 `just php-lint` limpio.
- [x] 3.2 Revisar accesibilidad: `label for`, textos alternativos, botones con texto y no solo icono.
- [x] 3.3 `just test` de nuevo tras el refactor.

## 4. Cierre
- [x] 4.1 `docs/podcast-episode-admin-fields.md` (qué es, ficheros, cómo funciona, cómo desplegar, decisiones, follow-ups).
- [x] 4.2 Comentar en el issue #248 que la mitad de ajustes de canal ya estaba implementada.
- [x] 4.3 `openspec validate podcast-episode-admin-fields --strict`.
- [ ] 4.4 Archivar el change tras aprobación y despliegue.