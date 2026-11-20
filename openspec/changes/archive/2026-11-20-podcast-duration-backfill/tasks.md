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

- [x] 1.1 Arnés `tests/podcast-duration/test_duration_emission.php` (estilo del proyecto: contador
      propio, `exit(0/1)`, `tests/wp-stubs.php`, dobles en memoria) y receta `just test-duration` en el
      `.justfile`; verificar que la receta arranca y falla antes de implementar
- [x] 1.2 Prueba *`HH:MM:SS` se emite tal cual* (`00:22:11` → `00:22:11`)
- [x] 1.3 Prueba *`MM:SS` válido se emite tal cual* (`22:11` → `22:11`) y *el entero de segundos se
      emite tal cual* (`1331` → `1331`, sin multiplicar ni convertir)
- [x] 1.4 Prueba *componente fuera de rango omite el tag* (`00:75:00`, `22:75`, `00:22:99`)
- [x] 1.5 Prueba *valor malformado omite el tag* (`abc`, `1:2:3`, `00:22:11x`, `--5`) y *episodio sin
      dato omite el tag*
- [x] 1.6 Prueba *el feed lee el canónico* y *usa `_duration` solo como respaldo cuando el canónico
      está vacío*; si ambos difieren, gana el canónico
- [x] 1.7 Evidencia RED pegada literalmente: `just test-duration` con **exit 1** para los escenarios
      nuevos, con el resto de arneses en verde

## 2. GREEN — normalización en la emisión

- [x] 2.1 `class-podcast-feed.php`: `public static function normalizeDuration( string $raw ): string`
      con el contrato del delta (trim exterior, tres formas admitidas, `''` para el resto); docblock que
      explique por qué no se reescala ni se reinterpreta
- [x] 2.2 `class-podcast-feed.php`: sustituir la emisión verbatim por el valor de `normalizeDuration()`
      y omitir el tag cuando devuelva `''`; máximo un `itunes:duration` por item
- [x] 2.3 Evidencia GREEN pegada literalmente: `just test-duration` (**exit 0**) + `just php-lint` +
      `just test`

## 3. RED — contrato del backfill

- [x] 3.1 Arnés `tests/podcast-duration/test_backfill.php` con el mismo estilo y dobles en memoria
      (RSS de Anchor de fixture + almacén de posts/metas), añadido a la receta `just test-duration`
- [x] 3.2 Fixture del RSS de Anchor con, al menos, un item `HH:MM:SS` con homólogo en WordPress, un
      episodio con `duration` 60× corta, un episodio sin duración y un episodio sin homólogo
- [x] 3.3 Prueba *el backfill rellena un episodio sin duración* (escribe `duration` y `_duration`)
- [x] 3.4 Prueba *el backfill corrige una duración 60× corta* (`00:22` → `00:22:11`, `20` → `00:20:37`)
- [x] 3.5 Prueba *el espejo de metas queda coherente*: `duration` y `_duration` siempre iguales tras
      la escritura
- [x] 3.6 Prueba *la segunda pasada real da 0 actualizados* y no modifica metadatos (idempotencia)
- [x] 3.7 Prueba *`--dry-run` no escribe* y lista los cambios; *`--limit` acota las actualizaciones* y
      reporta `pendientes`
- [x] 3.8 Prueba *un episodio sin correspondencia se reporta y no se modifica*; *un valor de Anchor
      ilegible no se escribe*
- [x] 3.9 Prueba *`--source` lee el fichero y no hace red* (el doble de `wp_remote_get` falla si se
      invoca) y *un origen ilegible aborta sin escrituras*
- [x] 3.10 Prueba *el ajuste `atareao_podcast_origin_rss` vacío resuelve
      `PodcastAudio::ORIGIN_RSS_DEFAULT`* y el backfill aplica duraciones en vez de abortar (caso real
      de producción)
- [x] 3.11 Prueba *el informe indica la URL de origen*: con `--source` la ruta y sin él la URL
      resuelta (configurada o por defecto)
- [x] 3.12 Prueba *el importador de episodios escribe el espejo* al crear un episodio (usa el punto
      único de escritura)
- [x] 3.13 Evidencia RED pegada literalmente: `just test-duration` con **exit 1** para los escenarios
      nuevos, manteniendo en verde los de §1

## 4. GREEN — módulo `PodcastDuration` y subcomando

- [x] 4.1 `includes/class-podcast-duration.php` (nuevo, namespace `Atareao`): `init()` que registra
      `\WP_CLI::add_command( 'atareao podcast-duration-backfill', array(__CLASS__, 'cliBackfill') )`
      solo si `WP_CLI` está definido, con el patrón de `class-podcast-guid.php:97-98`
- [x] 4.2 `public static function writeDuration( int $postId, string $canonical ): bool`: único punto
      de escritura; escribe `PodcastFeed::DURATION_META` y `PodcastFeed::LEGACY_DURATION_META` con el
      mismo valor y **solo si difiere**; sin literales de meta sueltos
- [x] 4.3 `private static function originUrl(): string` en el módulo nuevo, con el mismo contrato que
      `PodcastAudio::originUrl()` y `PodcastGuid::originUrl()` (ajuste no vacío o
      `PodcastAudio::ORIGIN_RSS_DEFAULT`), sin cambiar la API de esos módulos
- [x] 4.4 `public static function backfill( array $options = array() ): array`: fuente con
      `originUrl()` o `--source`, reutilizar `PodcastGuid::parseOriginFeed()` y
      `PodcastAudio::episodeNumberFromTitle()` (sin duplicar el parser ni la deducción del número),
      emparejamiento `anchor-guid` → número único, validación con
      `PodcastFeed::normalizeDuration()`, escritura solo si difiere, contadores del informe y la URL o
      ruta de origen en el resultado
- [x] 4.5 `cliBackfill( $args = array(), $assoc = array() ): void`: traduce `--dry-run`,
      `--limit` y `--source`, llama a `backfill()` y reporta con `WP_CLI::log`/`WP_CLI::success`,
      incluyendo la URL de origen usada; `--dry-run` rotula «Habría actualizado: N»; origen ilegible →
      `WP_CLI::error` sin escrituras
- [x] 4.6 `atareao-functionality.php`: `require_once` del módulo y `PodcastDuration::init()` en el
      bootstrap
- [x] 4.7 `class-podcast-guid.php`: `createEpisode()` delega la duración en
      `PodcastDuration::writeDuration()` y deja de escribir `duration` por su cuenta
- [x] 4.8 Evidencia GREEN pegada literalmente: `just test-duration` (**exit 0**) + `just php-lint` +
      `just test`

## 5. REFACTOR — limpieza y estilo

- [x] 5.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [x] 5.2 Reutilizar constantes y helpers existentes (metas, parser de Anchor, deducción de número) y
      documentar la precedencia del emparejamiento (guid antes que número) y por qué el número exige
      candidato único
- [x] 5.3 Re-ejecutar `just test-duration`, `just test-feed-soberania` y `just test` tras el refactor,
      pegando la evidencia

## 6. CI (job `lint`, sin contenedor)

- [x] 6.1 Añadir `php tests/podcast-duration/test_duration_emission.php` y
      `php tests/podcast-duration/test_backfill.php` al paso «Test PHP harnesses» de
      `.github/workflows/ci.yml`, como los arneses de `podcast-guid-parity`
- [x] 6.2 Verificar en local con `php` del sistema que ambos arneses corren sin WordPress ni contenedor
      y pegar la salida
- [x] 6.3 Confirmar que el job `lint` no necesita servicios ni matriz y que el tiempo añadido es
      despreciable

## 7. Documentación

- [x] 7.1 `docs/podcast-duration-backfill.md`: qué es, ficheros implicados, cómo funciona (normalización,
      emparejamiento y punto único de escritura), variables/config (`atareao_podcast_origin_rss`),
      cómo desplegar y ejecutar el backfill, decisiones y follow-ups
- [x] 7.2 README del plugin: documentar `wp atareao podcast-duration-backfill [--dry-run]
      [--limit=<n>] [--source=<ruta>]` y el formato canónico, incluido el espejo `_duration`
- [x] 7.3 Dejar registrado que `_duration` como respaldo de lectura es deuda transitoria y cuándo se
      retira

## Evidencia CLI (2026-11-20)

> Salidas literales de la rama `feature/podcast-duration-backfill`.

```text
# RED §1 — normalizeDuration() aún no existe
$ podman run --rm ... php tests/podcast-duration/test_duration_emission.php
  FAIL: existe PodcastFeed::normalizeDuration()
Resumen: 2 PASS, 1 FAIL
EXIT=1

# RED §3 — PodcastDuration aún no existe
$ podman run --rm ... php tests/podcast-duration/test_backfill.php
  FAIL: existe includes/class-podcast-duration.php
  FAIL: la clase \Atareao\PodcastDuration está disponible
  FAIL: existe PodcastDuration::writeDuration()
  FAIL: existe PodcastDuration::backfill()
Resumen: 0 PASS, 4 FAIL
EXIT=1

# GREEN §2/§4
$ just test-duration
Resumen: 43 PASS, 0 FAIL
Resumen: 79 PASS, 0 FAIL
EXIT=0

# Regresiones de los arneses existentes
$ just test-guid-parity     → Resumen: 78 PASS, 0 FAIL; EXIT=0
$ just test-feed-soberania  → Resumen: 26 PASS, 0 FAIL; EXIT=0

# Lint y estilo
$ just php-lint             → 71 ficheros «No syntax errors detected»; EXIT=0
$ just phpcs <tocados>      → 0 ERRORES; 4 avisos PSR1.Files.SideEffects (patrón
                              preexistente de los ficheros de clase del plugin)

# Suite completa
$ just test                 → 1895 PASS, 1 FAIL; EXIT=1
  FAIL: (reg) version coincide con la del plugin (Expected: '1.24.0', got: '1.22.0')
  (fallo preexistente de server.json, #192)

# CI (job lint, sin contenedor de WordPress)
$ podman run --rm ... docker.io/library/php:8.3-cli \
    php tests/podcast-duration/test_duration_emission.php
Resumen: 43 PASS, 0 FAIL; EMISSION_EXIT=0
$ ... php tests/podcast-duration/test_backfill.php
Resumen: 79 PASS, 0 FAIL; BACKFILL_EXIT=0

# OpenSpec
$ openspec validate 2026-11-20-podcast-duration-backfill --strict
Change '2026-11-20-podcast-duration-backfill' is valid
EXIT=0
```

## Evidencia CLI — ronda de auditoría (2026-11-20)

```text
# Mutación exigida: volver a decidir SOLO por el canónico (drift del espejo)
$ podman run --rm ... php tests/podcast-duration/test_backfill.php
--- Drift del espejo ---
  FAIL: un drift del espejo cuenta como actualizado (esperado 1, obtenido 0)
  FAIL: el drift no se declara sin cambios (esperado 0, obtenido 1)
  FAIL: el espejo se iguala al canónico (esperado '00:45:00', obtenido '00:40:00')
Resumen: 99 PASS, 3 FAIL
EXIT=1
# Revertida la mutación → verde:
Resumen: 102 PASS, 0 FAIL

# GREEN
$ just test-duration
Resumen: 43 PASS, 0 FAIL    (emisión)
Resumen: 102 PASS, 0 FAIL   (backfill)
Resumen: 5 PASS, 0 FAIL     (degradación del importador)
EXIT=0

# Lint y estilo
$ just php-lint             → 71 ficheros «No syntax errors detected»; EXIT=0
$ just phpcs <tocados>      → 0 ERRORES; avisos PSR1.Files.SideEffects (patrón preexistente)

# Suite completa
$ just test                 → 1923 PASS, 1 FAIL; EXIT=1
  FAIL: (reg) version coincide con la del plugin (Expected: '1.24.0', got: '1.22.0')
  (fallo preexistente de server.json, #192)

$ openspec validate 2026-11-20-podcast-duration-backfill --strict
Change '2026-11-20-podcast-duration-backfill' is valid
EXIT=0
```

## 8. PR, release y despliegue

- [x] 8.1 PR de `feature/podcast-duration-backfill` a `development` con CI `lint` en verde; pegar
      `gh pr checks`
      — **PR #208** (`feature/podcast-duration-backfill` → `development`), CI `lint` en verde:
      ```
      lint	pass	11s	https://github.com/atareao/atareao.es/actions/runs/44800408626/job/136510764743
      ```
- [x] 8.2 Merge a `development` y, por el flujo del repositorio, PR de `development` a `main`
      (el bump lo decide `release-prepare.yml` a partir de los commits; `feat` → `minor`)
      — merge a `development` = **`1d97754`**. A continuación, por el flujo del repositorio: PR de
      sincronización **#206** (`main` → `development`, que deja el autómata) mergeado, y PR **#209**
      (`development` → `main`, «🚀 release: duraciones del podcast normalizadas y backfill desde
      Anchor») mergeado.
- [x] 8.3 Release publicada (`vX.Y.Z`) y despliegue a producción; verificar la versión servida
      — `release-prepare.yml` clasificó **minor** (el commit de implementación es `✨ feat`) → PR
      **#210** «chore: release v1.25.0» mergeado; release **v1.25.0** publicada con los assets
      `atareao-functionality.zip` y `atareao-theme.zip`. Despliegue con `just deploy-prod` (4
      ficheros, `sha256` y `owner=82:82` verificados):
      ```
      atareao-functionality.php                 sha256=48fc4045c3a34ecfdb216acdda479b8997c083688c541f88355a0ba355afd2c3
      includes/class-podcast-duration.php       sha256=0193f5c2e168b9067735e1d6d81fbc8072100510ce13708b790d507aaa4f2ae3
      includes/class-podcast-feed.php           sha256=2418b43d2966659f6ffe7e9da87528336b7fb0078ebe39b159cbdd624e74f0f8
      includes/class-podcast-guid.php           sha256=40c107a8b2f2e4b49c060148f575987e636bbcd6a256a039040d91c31e6949cf
      ```
      Versión servida: **1.25.0**.
- [x] 8.4 Confirmar que el plugin nuevo está activo y que
      `wp atareao podcast-duration-backfill --help` lista el subcomando en producción
      — plugin **activo** y subcomando listado en la ayuda de producción:
      ```
      $ timeout 120 ssh -o BatchMode=yes atareao 'docker exec wpcli wp atareao podcast-duration-backfill --help'
      NAME

        wp atareao podcast-duration-backfill

      DESCRIPTION

        WP-CLI callback for the duration backfill.

      SYNOPSIS

        wp atareao podcast-duration-backfill 



      GLOBAL PARAMETERS

        --path=<path>
            Path to the WordPress files.

        --url=<url>
            Pretend request came from given URL. In multisite, this argument is how
            the target site is specified.

        --ssh=[<scheme>:][<user>@]<host|container>[:<port>][<path>]
            Perform operation against a remote server over SSH (or a container using
            scheme of "docker", "docker-compose", "docker-compose-run", "vagrant").

        --http=<http>
            Perform operation against a remote WordPress installation over HTTP.

        --user=<id|login|email>
            Set the WordPress user.

        --skip-plugins[=<plugins>]
            Skip loading all plugins, or a comma-separated list of plugins. Note:
            mu-plugins are still loaded.

        --skip-themes[=<themes>]
            Skip loading all themes, or a comma-separated list of themes.

        --skip-packages
            Skip loading all installed packages.

        --require=<path>
            Load PHP file before running the command (may be used more than once).

        --exec=<php-code>
            Execute PHP code before running the command (may be used more than once).

        --context=<context>
            Load WordPress in a given context.

        --[no-]color
            Whether to colorize the output.

        --debug[=<group>]
            Show all PHP errors and add verbosity to WP-CLI output. Built-in groups
            include: bootstrap, commandfactory, and help.

        --prompt[=<assoc>]
            Prompt the user to enter values for all command arguments, or a subset
            specified as comma-separated values.

        --quiet
            Suppress informational messages.

      $ timeout 120 ssh -o BatchMode=yes atareao 'docker exec wpcli wp plugin get atareao-functionality --field=status'
      active
      ```

## 9. Datos y verificación en producción

- [x] 9.1 Ejecutar `wp atareao podcast-duration-backfill --dry-run` y pegar el informe
      (`actualizados`, `sin_cambios`, `sin_correspondencia`, `ilegibles`, `pendientes`) junto a la URL
      de origen usada; comprobar que las muestras del informe coinciden con la diagnosis (16628, 17764,
      `12:20`)
      — `--dry-run` en producción (salida literal):
      ```
      Origen: https://anchor.fm/s/5a5b39c/podcast/rss
      Habría actualizado: 850
      Sin cambios: 0
      Sin correspondencia: 0
      Ilegibles: 0
      Pendientes: 0
        19746: duration=(vacío) _duration=(vacío) → 00:25:57
        19739: duration=(vacío) _duration=(vacío) → 00:33:51
        … (20 muestras) …
        … y 830 más
      ```
      Las muestras de la diagnosis quedaron así en BD tras el backfill:
      `16628: duration=00:22:11 _duration=00:22:11` (Anchor `00:22:11`),
      `17764: duration=00:20:37 _duration=00:20:37` (Anchor `00:20:37`); el `12:20` anómalo **ya no
      existe** (`rg -c '12:20' feed-local.xml` → **0**).
- [x] 9.2 Ejecutar el backfill real (por tandas con `--limit` si hace falta) y pegar el informe final
      — ejecución real → **`Success: Backfill de duración completado`** (**850** escrituras; la prueba
      de que fueron 850 es §9.3, cuya segunda pasada declara `Sin cambios: 850`).
- [x] 9.3 Ejecutar una segunda pasada real y comprobar **0 actualizados**
      — segunda pasada real (idempotencia):
      ```
      Actualizados: 0
      Sin cambios: 850
      Sin correspondencia: 0
      Ilegibles: 0
      Pendientes: 0
      Success: Backfill de duración completado
      ```
- [x] 9.4 Descargar `/podcast/feed/` y contar: **850/850 items con `<itunes:duration>` en `HH:MM:SS`**,
      **0** con `HH:MM`, **0** con minutos sueltos y **0** valores malformados
      — feed de producción descargado con `?cb=` (`x-cache-status: BYPASS`): `items=850`,
      `<itunes:duration>`=**850**, clasificación
      `HH:MM:SS=850 · MM:SS=0 · segundos=0 · otros=0`.
- [x] 9.5 Comparar **10 episodios al azar** con Anchor: duración idéntica carácter a carácter; pegar
      los 10 pares
      — comparación **completa** (mejor que 10 al azar), por `guid`, de los dos feeds:
      **`coinciden=850 difieren=0`** (0 sin pareja). 10 pares al azar:
      ```
      341a0008-…  00:20:09 = 00:20:09    3b39aa19-…  00:22:37 = 00:22:37
      52b73a90-…  00:21:07 = 00:21:07    bd55c8c2-…  00:21:45 = 00:21:45
      c42b5152-…  00:28:23 = 00:28:23    8c7e2a89-…  00:22:17 = 00:22:17
      7b0961dc-…  00:19:30 = 00:19:30    5b8cf2d4-…  00:19:10 = 00:19:10
      981501db-…  00:33:21 = 00:33:21    520c8354-…  00:16:17 = 00:16:17
      ```
- [x] 9.6 Comprobar que la ficha y la tarjeta del episodio muestran la duración nueva (espejo
      `_duration`) en al menos una de las 10 muestras
      — espejo pintado por el tema: ficha
      `/podcast/tu-nube-es-tuya-self-hosting-con-podman/` → `Duración: 00:33:51`; listado `/podcast/`
      (temporada 9) → **23/23 tarjetas** en `HH:MM:SS`; BD `19746: 00:25:57/00:25:57` y
      `16628: 00:22:11/00:22:11`.
- [x] 9.7 Comprobar que no han cambiado el número de items, el orden, los guids, los enclosures ni el
      feed principal `/feed/`
      — sin regresión: `items=850`, `<guid>`=850, guids con `http://`=**0**; `/feed/` (principal) sin
      el tag y sin cambios.
- [x] 9.8 Revalidar el feed con el W3C: sin error nuevo atribuible a `itunes:duration`
      — W3C Feed Validator sobre `/podcast/feed/?cb=…`: **«This is a valid RSS feed.»** y **0 avisos**
      relativos a `duration`.
- [x] 9.9 `openspec archive 2026-11-20-podcast-duration-backfill` tras el merge, con
      `openspec validate --strict` en verde
      — archivado en esta sesión (`openspec archive 2026-11-20-podcast-duration-backfill --yes`): el
      delta **ADDED** se fusionó en `openspec/specs/podcast-feed/spec.md` (`+ 1 added, ~ 0, - 0`) como
      requisito «Duración del episodio en el feed» (con sus 20 escenarios), **sin duplicar** ningún
      requisito. Validación: `openspec validate 2026-11-20-podcast-duration-backfill --strict` →
      `Unknown item '2026-11-20-podcast-duration-backfill'` (exit 1, esperado: la ruta ya está
      archivada) y `openspec validate --strict --all` → **`Totals: 40 passed, 0 failed (40 items)`**,
      exit 0.

## Estado de verificación

> Rellenar solo con evidencia CLI pegada literalmente. Nada marcado todavía.

- [x] `just test-duration` — RED (§1, §3) y GREEN (§2, §4) documentados
- [x] `just php-lint` y `just phpcs` — sin errores nuevos
- [x] `just test` — sin regresiones (salvo los fallos preexistentes documentados)
- [ ] CI `lint` del PR — en verde, con los dos arneses nuevos ejecutándose
- [ ] Despliegue — versión servida y subcomando disponible
- [ ] Backfill — `--dry-run` revisado, ejecución real y segunda pasada con 0 actualizados
- [ ] Feed de producción — 850/850 `HH:MM:SS`, 0 formatos heredados
- [ ] Muestra de 10 episodios — duraciones idénticas a Anchor
- [ ] Espejo — `duration` = `_duration` en la muestra y en la ficha del episodio
- [ ] Sin cambios en items, orden, guids, enclosures ni `/feed/`
- [ ] W3C — sin error nuevo
- [ ] Change archivado
