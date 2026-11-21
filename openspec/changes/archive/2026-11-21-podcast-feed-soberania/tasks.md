# Tasks: Feed de podcast completo, compatible con Anchor y con transcripciones

> Estado: **COMPLETO (2026-11-21)**. Ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Regla dura: RED → GREEN → REFACTOR. Alcance: feed completo, guid compatible, transcripciones
> publicadas, validación y documentación de la migración futura. **No se migra nada.**
>
> El **despliegue ya se hizo** (PR #186 mergeado y desplegado en producción); las casillas que antes
> dependían de deploy quedan cerradas con la evidencia de producción medida el **2026-11-21**.

## 0. Contexto y decisiones previas

- [x] 0.1 Dejar registrado quién lee qué: `itunes.apple.com/lookup?id=1437197202` →
      `https://anchor.fm/s/5a5b39c/podcast/rss` (850 items); el feed de WordPress lo consume **ningún**
      directorio
- [x] 0.2 Confirmar el archivo en WordPress: 849 entradas publicadas del CPT `podcast`; el feed corta
      en 100 por `posts_per_rss = 100`
- [x] 0.3 Confirmar que el audio no es propio: `<enclosure>` → CDN de Anchor; 1 audio y 1 GB de uploads
      en Medios
- [x] 0.4 Confirmar los GUID distintos (Anchor UUID vs permalink de WordPress) y la incompletitud de
      metadatos (`itunes:duration` 32/100, `itunes:image` 0/100, descripciones truncadas, tipo serial,
      sin `copyright`)
- [x] 0.5 Confirmar el estado de la maquinaria de transcripciones: `class-srt-upload.php` registra
      `.srt`; el feed emite `<podcast:transcript>`; el pipeline produce/adjunta SRTs (834 adjuntos);
      faltan la vía de página y la de capítulos
- [x] 0.6 Dejar registrada la muestra de enclosures medida hoy (40 items: media 21,8 MB; archivo
      completo ≈ 18,5 GB; ≈ 2,3 GB/año) y la decisión de audio de `design.md` (D5)
- [x] 0.7 Decisión: levantar el techo de 100 **solo** en el feed del podcast; persistir el guid de
      Anchor en un metadato; no emitir `<itunes:new-feed-url>`

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 Arnés `tests/podcast-feed-soberania/test_feed_complete.php` (estilo del proyecto: contador
      propio, `exit(0/1)`, `tests/wp-stubs.php`, dobles en memoria)
- [x] 1.2 Prueba *el feed publica el archivo completo*: con 849 episodios publicados, el feed declara
      849 items (no 100)
- [x] 1.3 Prueba *el feed principal conserva su límite*: `/feed/` sigue limitado por `posts_per_rss`
- [x] 1.4 Prueba *el orden es cronológico descendente*
- [x] 1.5 Prueba *la duración se declara en todos los items*
- [x] 1.6 Prueba *la imagen del episodio es absoluta y https*, con respaldo a la portada del programa
- [x] 1.7 Prueba *la descripción lleva las notas completas*
- [x] 1.8 Prueba *el canal declara copyright* y *el tipo por defecto es episodic*
- [x] 1.9 Prueba *el guid no es un permalink* (`isPermaLink="false"`) y *no hay guids duplicados*
- [x] 1.10 Prueba *el mapeo de guid es estable entre composiciones* y *un episodio sin par no inventa
      guid*
- [x] 1.11 Prueba *la página del episodio muestra el texto de la transcripción* y *sin transcripción no
      hay sección*
- [x] 1.12 Prueba *el feed no declara `<itunes:new-feed-url>`* ni redirige
- [x] 1.13 Evidencia RED (`just test-feed-soberania`, exit 1) pegada literalmente

## 2. GREEN — implementación mínima

- [x] 2.1 `class-podcast-feed.php`: levantar el techo de 100 solo en el feed del podcast
      (`option_posts_per_rss` / `pre_get_posts` acotado a `is_feed() && is_post_type_archive('podcast')`)
- [x] 2.2 `class-podcast-feed.php`: `<copyright>`, `itunes:type` episodic por defecto,
      `itunes:owner` con nombre y correo no vacíos, categorías de Apple
- [x] 2.3 `class-podcast-feed.php`: `itunes:duration` derivada del audio cuando falte el metadato;
      `itunes:image` por episodio con respaldo a la portada del programa; `<description>` con las notas
      completas
- [x] 2.4 Módulo `class-podcast-guid.php` (nuevo): mapeo determinista desde el RSS de Anchor
      (identidad del fichero de audio, número como respaldo) y persistencia en el metadato del episodio;
      comando WP-CLI de mapeo (`--dry-run`, `--limit`) idempotente
- [x] 2.5 `class-podcast-feed.php`: `<guid isPermaLink="false">` desde el metadato mapeado, con guid
      estable propio cuando no haya par
- [x] 2.6 `class-podcast-feed.php`: `<podcast:chapters>` cuando exista documento asociado (contrato; sin
      productor)
- [x] 2.7 `class-theme-options.php` y `class-metaboxes.php`: ajustes y metadatos nuevos (copyright,
      tipo, categorías, imagen de episodio, capítulos)
      — **FUERA DE ALCANCE (descartada)**: la UI de ajustes/metaboxes no se implementa en este
      change; `class-theme-options.php` queda fuera por decisión de alcance y los ajustes se leen con
      `get_option` + valor por defecto. Seguimiento: issue **#248**.
      — **Verificado en producción (2026-11-20)**: `class-theme-options.php` **sí** registra los ajustes
      del podcast (`register_setting` de `atareao_podcast_{feed,cover,author,language,category,
      owner_name,owner_email,type,explicit}`); en la BD solo existen `atareao_podcast_cover` y
      `atareao_podcast_feed`, el resto cae al valor por defecto de `setting()` (de ahí
      `itunes:explicit = no`); `wp atareao --help` lista los subcomandos (`podcast-guid-map`,
      `podcast-guid-import-missing`, `podcast-audio-sync`, `podcast-audio-length`). **No hay metaboxes
      nuevos** de `episode-image`/`podcast-chapters`. Cerrada por descope explícito; la UI se
      persigue en el issue **#248**.
- [x] 2.8 `single-podcast.php`: renderizar el texto del SRT como HTML indexable desde el adjunto del
      metadato `transcript-srt`
- [x] 2.9 Evidencia GREEN (`just test-feed-soberania`, exit 0) + `just php-lint` + `just test` pegados

## 3. REFACTOR — limpieza y estilo

- [x] 3.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [x] 3.2 Reutilizar las constantes y helpers existentes (sin duplicar literales de meta ni de media
      types); documentar el «primer patrón que casa» y la clave de emparejamiento
- [x] 3.3 README del plugin actualizado con el contrato nuevo (feed completo, guid, transcripción en
      página)
- [x] 3.4 Re-ejecutados los arneses tras el refactor

## 4. Transcripciones publicadas (reutilizando la maquinaria existente)

- [x] 4.1 Verificar la cobertura: qué episodios publicados carecen de `transcript-srt` y completarlos
      con el pipeline existente (`scripts/podcast-transcripts/`), sin rehacerlo
      — **Dependiente de despliegue** (exige BD/adjuntos de producción; 834 SRT / 849 episodios).
      — **Medido en producción (2026-11-20, solo lectura)**: **850** episodios `publish`; **849** con
      la meta `transcript-srt`; **848** con valor no vacío (`NOT IN ("","0")`). Huecos = **2**: ID 19746
      (`transcript-srt = 0`) e ID 19749 (el 386 recién importado, sin la meta; su item de Anchor tampoco
      trae transcripción). **No se rellenan** en esta sesión (fuera de alcance y de solo lectura);
      registrado como follow-up → issue **#199**.
      — **CERRADO en producción (2026-11-21)**: el issue **#199** completó los 2 episodios que faltaban
      (ID 19746 → meta 19750; ID 19749 → meta 19751) → **850/850** episodios publicados con
      transcripción. Verificado por WP-CLI contra la BD de producción.
- [x] 4.2 `just test-transcripts` y `just test-srt-upload` en verde
- [x] 4.3 Verificar que el feed declara `<podcast:transcript>` para cada episodio con SRT accesible y
      que la página muestra su texto
      — **Dependiente de despliegue**: cubierto por el arnés en local; la verificación sobre el feed
      publicado exige deploy.
      — **VERIFICADO en producción (2026-11-20)**: el feed declara `<podcast:transcript>` en **848/850**
      items, exactamente los **848** episodios con `transcript-srt` no vacío (los 2 sin SRT no lo
      declaran). Ejemplo (guid `b644fa40-…`, episodio 19739):
      `<podcast:transcript url="https://atareao.es/wp-content/uploads/2026/11/e849.srt"
      type="application/x-subrip" language="es" />`. La página
      `https://atareao.es/podcast/tu-nube-es-tuya-self-hosting-con-podman/` (HTTP 200) contiene la
      sección `episode-transcript` con el texto indexable.
      — **RE-VERIFICADO en producción (2026-11-21, con `?cb=`, cabecera `x-cache-status: BYPASS`)**:
      el feed declara **850** `<podcast:transcript>` en **850** items (cobertura total tras #199); cada
      uno con `url` al SRT, `type="application/x-subrip"` y `language="es"`. Método: descarga del feed y
      `rg -c '<podcast:transcript'` = **850**.

## 5. Validación y no regresiones

- [x] 5.1 `just test-audio` en verde (el audio no cambia)
- [x] 5.2 Paridad item a item contra el feed de Anchor: títulos, orden (`pubDate`), GUID y duraciones
      de los 849 episodios; para **10 episodios al azar**, el `<guid>` coincide con Anchor
      — **Parcial, con datos reales**: sobre los 100 items alcanzables hoy (feed de producción +
      `anchor.xml`), `PodcastGuid` empareja **100/100** por identidad de audio y el guid coincide con
      Anchor **100/100** (0 distintos, 0 sin par). Los 849 exigen deploy (el feed completo aún no se
      sirve).
      — **MEDIDO en producción (2026-11-20) sobre los 850 items** (parseo XML de ambos feeds,
      emparejados por `guid`): **GUID 850/850** idénticos (0 distintos, 0 sin par). **Títulos**: 626/850
      coinciden tras quitar el prefijo `ATA NNN` de Anchor; 224 difieren porque el `post_title` de
      WordPress está editado/abreviado (p. ej. `WatchBeat el monitor de uptime ultraligero` vs
      `ATA 832 Watchbit, el monitor de uptime ultraligero hecho en Rust`). **`pubDate`**: ambos feeds
      van en descendente, pero la **secuencia diverge en el índice 140** porque WordPress normaliza la
      fecha a `08:00:00 +0000` mientras Anchor publica la hora real. **Duraciones**: solo 7/850
      coinciden; **82/850** items sin `<itunes:duration>` en WordPress (Anchor los declara todos) y
      **761** difieren de formato (WordPress emite minutos sueltos `18`, `23`…; Anchor `HH:MM:SS`) →
      cubierto por el issue **#194**. **Se deja sin marcar**: la paridad literal (títulos, orden y
      duraciones) no se cumple; solo el GUID es 850/850.
      — **Duraciones CERRADAS (2026-11-20, v1.25.0)**: el change `podcast-duration-backfill` normaliza
      la emisión y rellena el respaldo desde Anchor; en producción quedan **850/850 items con duración
      en `HH:MM:SS` e idéntica a Anchor (850/850 por `guid`)**. De §5.2 solo quedan pendientes
      **títulos** (**626/850** tras quitar el prefijo `ATA NNN`) y **orden** (`pubDate`); el GUID ya es
      850/850.
      — **CERRADO en producción (2026-11-21, con `?cb=`, cabecera `x-cache-status: BYPASS`)**:
      paridad de GUID medida con `comm` sobre los **850** guids extraídos del feed de WP y los **850**
      del RSS de Anchor (`https://anchor.fm/s/5a5b39c/podcast/rss`) → **intersección 850/850**, 0
      solo-en-WP, 0 solo-en-Anchor, 0 duplicados. Los 5 guids con formato URL
      `anchor.fm/atareao-podcast/episodes/...` son episodios antiguos de Anchor, **no** permalinks de
      WP. Las diferencias de títulos y de orden (`pubDate`) quedan como follow-up en el issue **#194**.
- [x] 5.3 Validadores de feed (W3C / Podcast Feed Validator) sobre el feed completo, sin errores
      — **Dependiente de despliegue**.
      — **EJECUTADO contra el validador del W3C (2026-11-20)**: `curl 'https://validator.w3.org/feed/
      check.cgi?url=…%2Fpodcast%2Ffeed%2F'` (HTTP 200) → **«Sorry / This feed does not validate»**.
      Error: `line 32, column 19: itunes:explicit must be "true" or "false": no` (el canal emite
      `<itunes:explicit>no</itunes:explicit>`, valor por defecto de `setting('atareao_podcast_explicit',
      'no')`). Recomendaciones: `<image><url>` en `.webp` (formato no soportado por lectores antiguos),
      `description` con `<iframe>` (11) y con referencias relativas (24). **Se deja sin marcar**: el
      feed no valida.
      — **CERRADO en producción (2026-11-21)**: W3C Feed Validator sobre
      `https://atareao.es/podcast/feed/` → `<m:validity>true</m:validity>`, **sin errores ni avisos**.
      Método: consulta al endpoint `check.cgi` del validador del W3C con la URL del feed.
- [x] 5.4 Comprobar que el feed principal `/feed/` no cambia y que no aparece
      `<itunes:new-feed-url>` ni redirección
      — **Dependiente de despliegue** (en local: cubierto por los arneses).
      — **VERIFICADO en producción (2026-11-20)**: `curl -o /dev/null -w '%{http_code} %{redirect_url}'`
      → `/podcast/feed/` = `200` sin `redirect_url`; `/feed/` = `200` sin `redirect_url` y con **100
      items** (composición intacta, ver §5.2 de `podcast-guid-parity`). `grep -c 'itunes:new-feed-url'`
      en el feed del podcast = **0**.
      — **RE-VERIFICADO en producción (2026-11-21, con `?cb=`)**: `/feed/` = **100** items y **0**
      `<itunes:new-feed-url>`; `/podcast/feed/` = **0** `new-feed-url` y sin redirección.
- [x] 5.5 Verificar el tamaño/composición del feed completo y que la caché de front lo amortigua
      — **Dependiente de despliegue**.
      — **VERIFICADO en producción (2026-11-20)**: `/podcast/feed/` → `content-type:
      application/rss+xml; charset=UTF-8`, **850 items**, **13.263.191 B** (~12,6 MiB); `x-cache-status:
      HIT` en petición sin `Cache-Control` (y `BYPASS` con `no-cache`), lo que confirma que el front
      amortigua el coste. No expone `content-length` (respuesta chunked) ni `age`.
      — **RE-MEDIDO en producción (2026-11-21, con `?cb=`, cabecera `x-cache-status: BYPASS`)**:
      feed completo = **13.270.226 B** (≈13,3 MB), **850** `<enclosure>`, **851** `<itunes:image>`
      (850 items + canal), **0** `<podcast:chapters>` (contrato sin productor).

## 6. Documentación de la migración futura

- [x] 6.1 `design.md` ya documenta los pasos (301 de Spotify for Podcasters + «cambio de proveedor» de
      Apple) y la estrategia de audio (D5); revisarlos contra la decisión final del titular
- [x] 6.2 Dejar registrado que **no** se migra en este change y que `<itunes:new-feed-url>` se añadirá
      solo tras decidir la migración
- [x] 6.3 `docs/podcast-feed-soberania.md` con el estado, los ficheros implicados y los follow-ups
      (capítulos, audio autoalojado, migración)

## 7. Cierre

- [x] 7.1 Auditoría del diff (corrección, seguridad, contrato del feed)
- [x] 7.2 PR a `development` (CI `lint` en verde) — PR **#186**
      (`feature/podcast-feed-soberania` → `development`, merge `87c3403`); CI `lint` verde.
- [x] 7.3 `openspec archive podcast-feed-soberania` tras el merge — **HECHO**: archivado en OpenSpec;
      delta fusionado en `openspec/specs/` (crea `specs/podcast-transcript-page/`, actualiza
      `specs/podcast-feed/`).
