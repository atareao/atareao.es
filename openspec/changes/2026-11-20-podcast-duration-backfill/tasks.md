# Tasks: Duración real del episodio en el feed del podcast

> Estado: **PENDIENTE DE APROBACIÓN** (2026-11-20). Ninguna casilla se marca sin evidencia CLI pegada
> literalmente. Regla dura: RED → GREEN → REFACTOR. Alcance: normalizar `itunes:duration` en la emisión
> y rellenar la duración desde Anchor con un backfill idempotente. **No se toca el audio, ni el orden,
> ni los guids, ni se migra el feed.**
>
> Las casillas de **datos y verificación sobre el feed publicado** dependen de un despliegue que no
> forma parte de la redacción de este change.

## 0. Contexto y decisiones previas

- [x] 0.1 Confirmar la emisión: `durationMeta()` (`class-podcast-feed.php:476`) lee `duration` y cae a
      `_duration`, y la emisión escribe el valor **verbatim** (`:494-496`); el tema pinta `_duration`
      (`single-podcast.php:17`, `template-parts/content-podcast.php:8`)
- [x] 0.2 Medir el feed de producción (2026-11-20): **850 items**, **82 sin `<itunes:duration>`**,
      **768 con tag** —**476 `HH:MM`**, **285 minutos sueltos**, **7 `HH:MM:SS`**— y **761 con valor
      60× corto**
- [x] 0.3 Confirmar la semántica de Apple: `00:22` son **22 segundos**, no 22 minutos; casos 16628
      (`00:22` vs Anchor `00:22:11`), 17764 (`20` vs `00:20:37`) y `12:20` vs `00:20:16`
- [x] 0.4 Confirmar la fuente: el RSS de Anchor (`get_option('atareao_podcast_feed')` =
      `https://anchor.fm/s/5a5b39c/podcast/rss`) declara **850/850** con `HH:MM:SS`
- [x] 0.5 Confirmar que los 82 sin duración tienen `anchor-guid`, `mp3-url` y `number` (**82/82**), y
      que el importador escribe `duration` solo al crear (`class-podcast-guid.php:436-438`)
- [x] 0.6 Confirmar que el sondeo de audio está descartado: sin `ffprobe`/`ffmpeg` en `atareao`,
      `wpcli` ni `wordpress-nginx-1`, y 850 audios ≈ 18,5 GB
- [x] 0.7 Decisiones registradas en el `proposal.md`: meta canónico + espejo con punto único de
      escritura, normalización sin heurística, entero de segundos literal, respaldo de emparejamiento
      por número, módulo nuevo `PodcastDuration`, `--limit` acota actualizaciones
- [x] 0.8 Confirmar en producción que `atareao_podcast_origin_rss` vale **`false`** (ajuste vacío) y
      que `PodcastAudio::ORIGIN_RSS_DEFAULT` (`class-podcast-audio.php:54`) es exactamente
      `https://anchor.fm/s/5a5b39c/podcast/rss`; por tanto el backfill debe resolver la fuente con el
      patrón de `originUrl()`, no leyendo el ajuste a secas

## 1. RED — contrato de la emisión normalizada

- [ ] 1.1 Arnés `tests/podcast-duration/test_duration_emission.php` (estilo del proyecto: contador
      propio, `exit(0/1)`, `tests/wp-stubs.php`, dobles en memoria) y receta `just test-duration` en el
      `.justfile`; verificar que la receta arranca y falla antes de implementar
- [ ] 1.2 Prueba *`HH:MM:SS` se emite tal cual* (`00:22:11` → `00:22:11`)
- [ ] 1.3 Prueba *`MM:SS` válido se emite tal cual* (`22:11` → `22:11`) y *el entero de segundos se
      emite tal cual* (`1331` → `1331`, sin multiplicar ni convertir)
- [ ] 1.4 Prueba *componente fuera de rango omite el tag* (`00:75:00`, `22:75`, `00:22:99`)
- [ ] 1.5 Prueba *valor malformado omite el tag* (`abc`, `1:2:3`, `00:22:11x`, `--5`) y *episodio sin
      dato omite el tag*
- [ ] 1.6 Prueba *el feed lee el canónico* y *usa `_duration` solo como respaldo cuando el canónico
      está vacío*; si ambos difieren, gana el canónico
- [ ] 1.7 Evidencia RED pegada literalmente: `just test-duration` con **exit 1** para los escenarios
      nuevos, con el resto de arneses en verde

## 2. GREEN — normalización en la emisión

- [ ] 2.1 `class-podcast-feed.php`: `public static function normalizeDuration( string $raw ): string`
      con el contrato del delta (trim exterior, tres formas admitidas, `''` para el resto); docblock que
      explique por qué no se reescala ni se reinterpreta
- [ ] 2.2 `class-podcast-feed.php`: sustituir la emisión verbatim por el valor de `normalizeDuration()`
      y omitir el tag cuando devuelva `''`; máximo un `itunes:duration` por item
- [ ] 2.3 Evidencia GREEN pegada literalmente: `just test-duration` (**exit 0**) + `just php-lint` +
      `just test`

## 3. RED — contrato del backfill

- [ ] 3.1 Arnés `tests/podcast-duration/test_backfill.php` con el mismo estilo y dobles en memoria
      (RSS de Anchor de fixture + almacén de posts/metas), añadido a la receta `just test-duration`
- [ ] 3.2 Fixture del RSS de Anchor con, al menos, un item `HH:MM:SS` con homólogo en WordPress, un
      episodio con `duration` 60× corta, un episodio sin duración y un episodio sin homólogo
- [ ] 3.3 Prueba *el backfill rellena un episodio sin duración* (escribe `duration` y `_duration`)
- [ ] 3.4 Prueba *el backfill corrige una duración 60× corta* (`00:22` → `00:22:11`, `20` → `00:20:37`)
- [ ] 3.5 Prueba *el espejo de metas queda coherente*: `duration` y `_duration` siempre iguales tras
      la escritura
- [ ] 3.6 Prueba *la segunda pasada real da 0 actualizados* y no modifica metadatos (idempotencia)
- [ ] 3.7 Prueba *`--dry-run` no escribe* y lista los cambios; *`--limit` acota las actualizaciones* y
      reporta `pendientes`
- [ ] 3.8 Prueba *un episodio sin correspondencia se reporta y no se modifica*; *un valor de Anchor
      ilegible no se escribe*
- [ ] 3.9 Prueba *`--source` lee el fichero y no hace red* (el doble de `wp_remote_get` falla si se
      invoca) y *un origen ilegible aborta sin escrituras*
- [ ] 3.10 Prueba *el ajuste `atareao_podcast_origin_rss` vacío resuelve
      `PodcastAudio::ORIGIN_RSS_DEFAULT`* y el backfill aplica duraciones en vez de abortar (caso real
      de producción)
- [ ] 3.11 Prueba *el informe indica la URL de origen*: con `--source` la ruta y sin él la URL
      resuelta (configurada o por defecto)
- [ ] 3.12 Prueba *el importador de episodios escribe el espejo* al crear un episodio (usa el punto
      único de escritura)
- [ ] 3.13 Evidencia RED pegada literalmente: `just test-duration` con **exit 1** para los escenarios
      nuevos, manteniendo en verde los de §1

## 4. GREEN — módulo `PodcastDuration` y subcomando

- [ ] 4.1 `includes/class-podcast-duration.php` (nuevo, namespace `Atareao`): `init()` que registra
      `\WP_CLI::add_command( 'atareao podcast-duration-backfill', array(__CLASS__, 'cliBackfill') )`
      solo si `WP_CLI` está definido, con el patrón de `class-podcast-guid.php:97-98`
- [ ] 4.2 `public static function writeDuration( int $postId, string $canonical ): bool`: único punto
      de escritura; escribe `PodcastFeed::DURATION_META` y `PodcastFeed::LEGACY_DURATION_META` con el
      mismo valor y **solo si difiere**; sin literales de meta sueltos
- [ ] 4.3 `private static function originUrl(): string` en el módulo nuevo, con el mismo contrato que
      `PodcastAudio::originUrl()` y `PodcastGuid::originUrl()` (ajuste no vacío o
      `PodcastAudio::ORIGIN_RSS_DEFAULT`), sin cambiar la API de esos módulos
- [ ] 4.4 `public static function backfill( array $options = array() ): array`: fuente con
      `originUrl()` o `--source`, reutilizar `PodcastGuid::parseOriginFeed()` y
      `PodcastAudio::episodeNumberFromTitle()` (sin duplicar el parser ni la deducción del número),
      emparejamiento `anchor-guid` → número único, validación con
      `PodcastFeed::normalizeDuration()`, escritura solo si difiere, contadores del informe y la URL o
      ruta de origen en el resultado
- [ ] 4.5 `cliBackfill( $args = array(), $assoc = array() ): void`: traduce `--dry-run`,
      `--limit` y `--source`, llama a `backfill()` y reporta con `WP_CLI::log`/`WP_CLI::success`,
      incluyendo la URL de origen usada; `--dry-run` rotula «Habría actualizado: N»; origen ilegible →
      `WP_CLI::error` sin escrituras
- [ ] 4.6 `atareao-functionality.php`: `require_once` del módulo y `PodcastDuration::init()` en el
      bootstrap
- [ ] 4.7 `class-podcast-guid.php`: `createEpisode()` delega la duración en
      `PodcastDuration::writeDuration()` y deja de escribir `duration` por su cuenta
- [ ] 4.8 Evidencia GREEN pegada literalmente: `just test-duration` (**exit 0**) + `just php-lint` +
      `just test`

## 5. REFACTOR — limpieza y estilo

- [ ] 5.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [ ] 5.2 Reutilizar constantes y helpers existentes (metas, parser de Anchor, deducción de número) y
      documentar la precedencia del emparejamiento (guid antes que número) y por qué el número exige
      candidato único
- [ ] 5.3 Re-ejecutar `just test-duration`, `just test-feed-soberania` y `just test` tras el refactor,
      pegando la evidencia

## 6. CI (job `lint`, sin contenedor)

- [ ] 6.1 Añadir `php tests/podcast-duration/test_duration_emission.php` y
      `php tests/podcast-duration/test_backfill.php` al paso «Test PHP harnesses» de
      `.github/workflows/ci.yml`, como los arneses de `podcast-guid-parity`
- [ ] 6.2 Verificar en local con `php` del sistema que ambos arneses corren sin WordPress ni contenedor
      y pegar la salida
- [ ] 6.3 Confirmar que el job `lint` no necesita servicios ni matriz y que el tiempo añadido es
      despreciable

## 7. Documentación

- [ ] 7.1 `docs/podcast-duration-backfill.md`: qué es, ficheros implicados, cómo funciona (normalización,
      emparejamiento y punto único de escritura), variables/config (`atareao_podcast_origin_rss`),
      cómo desplegar y ejecutar el backfill, decisiones y follow-ups
- [ ] 7.2 README del plugin: documentar `wp atareao podcast-duration-backfill [--dry-run]
      [--limit=<n>] [--source=<ruta>]` y el formato canónico, incluido el espejo `_duration`
- [ ] 7.3 Dejar registrado que `_duration` como respaldo de lectura es deuda transitoria y cuándo se
      retira

## 8. PR, release y despliegue

- [ ] 8.1 PR de `feature/podcast-duration-backfill` a `development` con CI `lint` en verde; pegar
      `gh pr checks`
- [ ] 8.2 Merge a `development` y, por el flujo del repositorio, PR de `development` a `main`
      (el bump lo decide `release-prepare.yml` a partir de los commits; `feat` → `minor`)
- [ ] 8.3 Release publicada (`vX.Y.Z`) y despliegue a producción; verificar la versión servida
- [ ] 8.4 Confirmar que el plugin nuevo está activo y que
      `wp atareao podcast-duration-backfill --help` lista el subcomando en producción

## 9. Datos y verificación en producción

- [ ] 9.1 Ejecutar `wp atareao podcast-duration-backfill --dry-run` y pegar el informe
      (`actualizados`, `sin_cambios`, `sin_correspondencia`, `ilegibles`, `pendientes`) junto a la URL
      de origen usada; comprobar que las muestras del informe coinciden con la diagnosis (16628, 17764,
      `12:20`)
- [ ] 9.2 Ejecutar el backfill real (por tandas con `--limit` si hace falta) y pegar el informe final
- [ ] 9.3 Ejecutar una segunda pasada real y comprobar **0 actualizados**
- [ ] 9.4 Descargar `/podcast/feed/` y contar: **850/850 items con `<itunes:duration>` en `HH:MM:SS`**,
      **0** con `HH:MM`, **0** con minutos sueltos y **0** valores malformados
- [ ] 9.5 Comparar **10 episodios al azar** con Anchor: duración idéntica carácter a carácter; pegar
      los 10 pares
- [ ] 9.6 Comprobar que la ficha y la tarjeta del episodio muestran la duración nueva (espejo
      `_duration`) en al menos una de las 10 muestras
- [ ] 9.7 Comprobar que no han cambiado el número de items, el orden, los guids, los enclosures ni el
      feed principal `/feed/`
- [ ] 9.8 Revalidar el feed con el W3C: sin error nuevo atribuible a `itunes:duration`
- [ ] 9.9 `openspec archive 2026-11-20-podcast-duration-backfill` tras el merge, con
      `openspec validate --strict` en verde

## Estado de verificación

> Rellenar solo con evidencia CLI pegada literalmente. Nada marcado todavía.

- [ ] `just test-duration` — RED (§1, §3) y GREEN (§2, §4) documentados
- [ ] `just php-lint` y `just phpcs` — sin errores nuevos
- [ ] `just test` — sin regresiones (salvo los fallos preexistentes documentados)
- [ ] CI `lint` del PR — en verde, con los dos arneses nuevos ejecutándose
- [ ] Despliegue — versión servida y subcomando disponible
- [ ] Backfill — `--dry-run` revisado, ejecución real y segunda pasada con 0 actualizados
- [ ] Feed de producción — 850/850 `HH:MM:SS`, 0 formatos heredados
- [ ] Muestra de 10 episodios — duraciones idénticas a Anchor
- [ ] Espejo — `duration` = `_duration` en la muestra y en la ficha del episodio
- [ ] Sin cambios en items, orden, guids, enclosures ni `/feed/`
- [ ] W3C — sin error nuevo
- [ ] Change archivado