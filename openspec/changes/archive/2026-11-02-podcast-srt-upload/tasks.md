# Tasks: Subida manual de transcripciones SRT

> Estado: **CERRADO** (2026-11-02). GREEN + REFACTOR + E2E verificados; PR #154 fusionado; delta archivado.
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Alcance: registro del tipo `srt`, filtro del selector y su arnés. Nada del feed ni del pipeline.

## 0. Contexto y decisiones previas

- [x] 0.1 Reproducir el fallo en producción con un SRT mínimo (`/tmp/prueba.srt`, 44 B):
      `wp_check_filetype_and_ext()` → `array('ext' => false, 'type' => false, 'proper_filename' => false)`
- [x] 0.2 Identificar por qué: `finfo` detecta `application/x-subrip` y core registra `.srt` como
      `text/plain`; la discrepancia hace que la validación de subida aborte
- [x] 0.3 Medir que añadir `srt => application/x-subrip` **al final** del mapa no arregla nada:
      `wp_check_filetype()` sigue devolviendo `text/plain` y la validación sigue en `false`
- [x] 0.4 Medir que **anteponerlo** sí arregla: `wp_check_filetype()` → `application/x-subrip` y
      `wp_check_filetype_and_ext()` → `ext = srt`, `type = application/x-subrip`
- [x] 0.5 Confirmar el estado de producción: 834 adjuntos `application/x-subrip`, 0 `text/plain`
- [x] 0.6 Confirmar que nginx no filtra la extensión y que el tamaño no aplica (SRT de 44 B)
- [x] 0.7 Decisión: anteponer en `upload_mimes` (no filtrar la validación), reutilizar
      `PodcastFeed::TRANSCRIPT_MEDIA_TYPE` y ampliar el selector a `text/plain` como defensa

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 Arnés `tests/podcast-srt-upload/test_srt_upload.php` (estilo `tests/audio-sync`: contador
      propio, `exit(0/1)`, carga condicional de los módulos para no morir con un fatal)
- [x] 1.2 Prueba *el tipo registrado gana al de core*: el mapa filtrado resuelve `srt` a
      `application/x-subrip` y la clave `srt` es la primera
- [x] 1.3 Prueba *los tipos de core no cambian*: `txt` sigue resolviendo `text/plain` y `vtt` su tipo
- [x] 1.4 Prueba *el tipo sale de la constante*: `PodcastFeed::TRANSCRIPT_MEDIA_TYPE` sigue siendo
      `application/x-subrip` y es el valor que usa el filtro
- [x] 1.5 Prueba *el selector pide los dos tipos*: `Metaboxes::transcriptLibraryTypes()` devuelve
      `['application/x-subrip', 'text/plain']`
- [x] 1.6 Evidencia RED (`just test-srt-upload`, exit 1):

```
  FAIL: existe includes/class-srt-upload.php
  FAIL: la clase \Atareao\SrtUpload está disponible
  FAIL: el módulo expone allowSrtMime()
  PASS: PodcastFeed::TRANSCRIPT_MEDIA_TYPE sigue siendo application/x-subrip

Resumen: 1 PASS, 3 FAIL
```

## 2. GREEN — implementación mínima

- [x] 2.1 `includes/class-srt-upload.php`: `\Atareao\SrtUpload::init()` y
      `allowSrtMime(array $mimes): array` que antepone `srt => PodcastFeed::TRANSCRIPT_MEDIA_TYPE`
- [x] 2.2 Registrado en `atareao-functionality.php` (`require_once` + `init()`)
- [x] 2.3 Versión del plugin: **no se toca a mano**; la bumpea el pipeline de release
      (`release-prepare.yml` + `.vampus.yml`). Evita inflar el versionado sin release
- [x] 2.4 `library.type` del selector → `['application/x-subrip', 'text/plain']`, inyectado en el JS
      con el marcador `__ATAREAO_TRANSCRIPT_TYPES__` y `wp_json_encode()`
- [x] 2.5 Evidencia GREEN (`just test-srt-upload`, exit 0):

```
Resumen: 15 PASS, 0 FAIL
```

`just php-lint` → `EXIT=0` (todos los ficheros, incluido `class-srt-upload.php`).
`just test` → `Total passed: 1090, Total failed: 0`, `EXIT=0`.

## 3. REFACTOR — limpieza y estilo

- [x] 3.1 `just phpcs` sobre los ficheros tocados: 0 errores. El único aviso de
      `class-srt-upload.php` es el mismo `PSR1.Files.SideEffects` de línea 1 que ya tienen todos los
      módulos del plugin (`class-podcast-audio.php` produce exactamente el mismo aviso). El `exit 2`
      global de `just phpcs` es de avisos preexistentes del theme y de los JS de bloques, no del cambio
- [x] 3.2 Comentario en el filtro explicando el «primer patrón que casa» y por qué no puede añadirse
      al final del mapa
- [x] 3.3 README del plugin: la vía manual documenta el tipo registrado y por qué el orden del mapa
      es la diferencia entre aceptar y rechazar la subida
- [x] 3.4 Re-ejecutados `just test-srt-upload` (15 PASS) y `just test` (1090 PASS) tras el refactor

## 4. E2E en producción

Desplegados los tres ficheros en el volumen de producción con backup previo en
`/tmp/e2e-srt-backup/` del contenedor `wpcli`. Los ficheros base coincidían con `HEAD~2`
(md5 idéntico), así que no se pisó ningún hotfix.

- [x] 4.1 Subida real por la ruta de WordPress (`wp media import` →
      `media_handle_sideload` → `wp_check_filetype_and_ext`): adjunto **19692** creado
- [x] 4.2 `post_mime_type` del adjunto: **`application/x-subrip`**; fichero en
      `/wp-content/uploads/2026/11/prueba.srt`
- [x] 4.3 `apply_filters('upload_mimes', …)` en producción devuelve `srt` como
      **primera** clave con `application/x-subrip`, y `wp_check_filetype_and_ext()` →
      `ext = srt`, `type = application/x-subrip`
- [x] 4.4 El selector resuelve `['application/x-subrip', 'text/plain']`
      (`Metaboxes::transcriptLibraryTypes()`); el feed sigue declarando
      `<podcast:transcript … type="application/x-subrip" …>` (98 en la primera página)
- [x] 4.5 Adjunto de prueba borrado con `wp_delete_attachment($id, true)`;
      `get_post(19692) === null`, sin ficheros residuales en `uploads/2026/11/`.
      Contadores intactos: **834** `application/x-subrip`, **0** `text/plain`

## 5. Cierre

- [x] 5.1 Auditoría del diff (corrección, seguridad, capacidades y contrato del feed)
      Revisado: el filtro no introduce entrada de usuario ni cambia capacidades; el marcador del JS se
      sustituye por `wp_json_encode()` de dos constantes; `.txt`/`.vtt` conservan su tipo (probado).
- [x] 5.2 PR #154 a `development` en borrador (CI `lint` en verde): https://github.com/atareao/atareao.es/pull/154
- [x] 5.3 `openspec archive podcast-srt-upload`: autorizado por el titular tras el merge del PR #154
      (todo el E2E quedó verificado en producción). El archivo se ejecuta en la rama
      `chore/archive-podcast-srt-upload` para volcar el delta en `openspec/specs/podcast-srt-upload/`
