# Tasks: Autorrelleno del enlace de audio por episodio desde el RSS del origen

> Estado: **borrador de plan**, pendiente de aprobación explícita del titular.
> Ninguna casilla de 1 a 5 se marca sin evidencia CLI pegada literalmente.
> Alcance: **solo `mp3-url`**. La URL pública de Spotify y su enlace en la ficha quedan fuera por decisión
> del titular (ver `proposal.md`), y **nunca** se sobrescribe un `mp3-url` válido.

## 0. Contexto y decisiones previas

- [x] 0.1 Caracterizar el origen: `https://anchor.fm/s/5a5b39c/podcast/rss` → 844 items, 915 KB
      comprimidos, 0,18 s, `ETag` + **304** al `If-None-Match`, sin bloqueo por `User-Agent`
      (probados `WordPress/6.8.2`, `curl/8.5.0` y un UA de navegador: HTTP 200 los tres)
- [x] 0.2 Caracterizar la clave de emparejamiento: **844 identidades de audio únicas en 844 items**;
      833 de los 834 episodios de WordPress con audio coinciden por identidad; 1 divergencia real
      (ATA 3, audio recodificado); el `itunes:episode` del origen **no** es fiable (19 números
      repetidos, ATA 829 marcado como 825, ATA 712 como 713) → **la clave primaria es la identidad**
- [x] 0.3 Medir el hueco real: 838 episodios en el feed del podcast, **3 sin `<enclosure>`** y **1 con
      `<enclosure url="15">`** (ATA 552). Números de WordPress cubiertos por número: 832 de 835
      (faltan 4, 32 y 666 — 666 casa por identidad, en el origen va titulado 667)
- [x] 0.4 **Refutar la hipótesis barata**: el token del `<link>` del origen (`e3ra5d1`) **no** es un ID
      de Spotify. Control: el oEmbed de episodios responde 200+JSON con IDs reales de 22 caracteres
      de otros podcasts y **504** con ese token, en las tres formas de URL; el oEmbed del show sí
      funciona. Consecuencia: la URL pública exige la Web API y credenciales
- [x] 0.5 Confirmar la ubicación del código: módulo del plugin
      (`includes/class-podcast-audio.php`), pruebas offline en `tests/audio-sync/`, motor de decisión
      **puro** (sin red) para que el contrato sea verificable sin WordPress
- [x] 0.6 Confirmar que **no** hay cron: `quadlets/atareao-wordpress.container:22` fija
      `DISABLE_WP_CRON=true` → nada de eventos diferidos; el trabajo va en la petición de publicación
      o en el comando
- [x] 0.7 **Decisión del titular (2026-11-02), ya cerrada:** este change entrega **solo** el
      autorrelleno y la reparación del `mp3-url` desde el RSS del origen. La URL pública de Spotify y
      su enlace en la ficha quedan **fuera de alcance** (exigen app de desarrollador y cuenta Premium
      desde 2026-02-11). Y **no habrá opción que sobrescriba un `mp3-url` válido**: la divergencia del
      ATA 3 se reporta y no se corrige desde aquí

## 1. RED — pruebas que describen el contrato (antes de implementar)

- [x] 1.1 Fixture `tests/audio-sync/fixtures/origin-rss-sample.xml`, recorte del RSS real con los
      casos que importan: `ATA 844` (normal), `ATA839` (sin espacio), `ATA T01E50 - ...` (formato
      antiguo con doble prefijo), `705 - ...` (número fuera del patrón `ATA`), `T01E36 ...`, el par
      duplicado del 667, el ítem sin número y un `enclosure` no-URL (el `"15"`)
- [x] 1.2 `tests/audio-sync/test_podcast_audio.php` (arnés procedural, `require_once
      tests/wp-stubs.php`), una prueba por escenario del delta:
  - [x] 1.2.1 *El audio procede del origen* / *una sola descarga* / *origen inalcanzable* →
        `parseOriginFeed`, `audioIdentity`, contador de peticiones del doble de `wp_remote_get`
  - [x] 1.2.2 *Misma identidad en dos formas* (envuelta vs directa), *identidad distinta → divergente*,
        *la identidad gana al número*
  - [x] 1.2.3 *Formatos de título → mismo número* (los cinco casos del fixture),
        *número con dos candidatos → ambiguo*, *número sin ítem → sin par*
  - [x] 1.2.4 *Audio vacío → se rellena*, *valor que no es URL → se repara*, *el feed publica una URL
        válida tras reparar*
  - [x] 1.2.5 *No se cambia el audio ya correcto*, *la divergencia solo se informa*, *un emparejamiento
        fallido no borra el valor existente* (y **no** existe opción que lo sobrescriba)
  - [x] 1.2.6 *Publicar resuelve*, *el origen aún no lo tiene*, *fallo de red no rompe*, *guardar de
        nuevo no repite*, *borradores y otros tipos no se tocan* (negar la escritura de metas en
        borradores vía `transition_post_status`)
  - [x] 1.2.7 *Ensayo sin escritura*, *segunda ejecución sin escritura*, *límite*, *solo los del audio
        ausente o inválido*, *fallo por episodio sin abortar* (motor de decisión puro, sin red)
  - [x] 1.2.8 *Solo URLs absolutas http(s)*, *un valor no saneable no se escribe*
- [x] 1.3 Receta `just test-audio` en `.justfile` (patrón de `just test-transcripts`)
- [x] 1.4 Ejecutar por CLI y **confirmar que falla** (`just test-audio`, esperado: exit code 1)
- [x] 1.5 Confirmar que **no hay regresión** en las suites heredadas (`just test` 1068/0 y
      `just test-transcripts` verde) — pegar la salida literal
- [x] 1.6 Registrar la evidencia del RED en este fichero

#### Evidencia — RED

**`just test-audio` → EXIT=1** (el módulo de producción aún no existe, así que los 27 escenarios del
delta fallan; es el rojo esperado):

```
=== Running podcast-audio-sync (RED) Test Suite ===
  FAIL: El audio procede del origen — módulo no implementado
  FAIL: Una ejecución descarga el origen una sola vez — módulo no implementado
  ... (27 líneas FAIL, una por escenario del delta) ...
  FAIL: Un valor no saneable no se escribe — módulo no implementado

============================================
Total passed: 0, Total failed: 27
error: recipe `test-audio` failed with exit code 1
```

**Sin regresión en las suites heredadas:**

```
$ just test              → EXIT=0   Total passed: 1068, Total failed: 0
$ just test-transcripts  → EXIT=0   Total passed: 32,   Total failed: 0
```

**`php -l` (contenedor `wordpress:cli-php8.3`):**

```
No syntax errors detected in tests/audio-sync/test_podcast_audio.php
No syntax errors detected in tests/wp-stubs.php
```

**Artefactos RED entregados:**

- `tests/audio-sync/fixtures/origin-rss-sample.xml` — recorte del RSS del origen con 11 items: los cinco
  formatos de título (`ATA 844`, `ATA839`, `ATA T01E50 -`, `705 -`, `T01E36`), el par duplicado del
  **667**, un item sin número, el `enclosure url="15"` del ATA 552, y el **mismo fichero `ata700.mp3`**
  en dos formas (envuelto por la redirección de reproducción del origen y enlace directo).
- `tests/audio-sync/test_podcast_audio.php` — arnés procedural (`require_once tests/wp-stubs.php`,
  `harness_reset_state()` por caso) con **27 escenarios**, uno por cada escenario del delta. Con el
  módulo ausente no muere por *fatal*: imprime cada escenario como FALLO y `exit(1)`.
- `.justfile` — receta `just test-audio` (misma mecánica que `test`/`test-transcripts`).
- `tests/wp-stubs.php` — cambio **aditivo**: se añade el doble `wp_remote_get()` (que faltaba) delegando
  en `atareao_mock_remote_request()`, junto a `wp_safe_remote_get()`.

**Decisiones de interfaz fijadas por el RED** (el GREEN debe cumplirlas): un origen inalcanzable cuenta
los episodios como `failed` y no escribe nada; un `mp3-url` válido sin identidad `.mp3` cae a la clave de
número (puede ser `ambiguous`/`unmatched`), mientras que un `.mp3` válido con identidad desconocida es
`divergent`; `--dry-run` reporta `repaired` (lo que se habría escrito) y `remaining`.

## 2. GREEN — implementación mínima

- [x] 2.1 `includes/class-podcast-audio.php`: constantes (meta `mp3-url`, URL del origen, TTL del
      índice, tamaño máximo, tiempo de espera) y **funciones puras**: `audioIdentity`,
      `episodeNumberFromTitle`, `isUsableAudioUrl`, `parseOriginFeed`, `buildIndex`, `planSync`
- [x] 2.2 El módulo: descarga con `wp_remote_get` (tiempo de espera, límite de bytes, `ETag` +
      `If-None-Match`), índice en *transient* con TTL corto y TTL de fallo más corto todavía
- [x] 2.3 Gancho de publicación idempotente + meta marca de intento (prefijo interno) para no repetir
      descargas ni reescribir metas ya resueltas
- [x] 2.4 Comando `wp atareao podcast-audio-sync` con `--dry-run`, `--limit`, `--missing`,
      `--format=table|json` y resumen por clase (patrón de `class-pocketid-login.php`); **sin** ninguna
      opción que sobrescriba un `mp3-url` válido
- [x] 2.5 Registro: `require_once` + `PodcastAudio::init()` en `atareao-functionality.php`
- [x] 2.6 Pasar las pruebas (`just test-audio` → exit code 0) y pegar la salida literal

#### Evidencia — GREEN

**`just test-audio` → EXIT=0**: los 27 escenarios del delta, **96 aserciones, 0 fallos**:

```
=== Running podcast-audio-sync (RED) Test Suite ===
... (27 escenarios, 96 PASS) ...
============================================
Total passed: 96, Total failed: 0
All tests passed successfully!
```

**Sin regresión:**

```
$ just test              → EXIT=0   Total passed: 1068, Total failed: 0
$ just test-transcripts  → EXIT=0   Total passed: 32,   Total failed: 0
$ just php-lint          → EXIT=0   66 ficheros sin errores de sintaxis
$ openspec validate podcast-audio-sync --strict → valid
```

**Implementación:** `includes/class-podcast-audio.php` (clase `Atareao\PodcastAudio`): funciones puras
(`audioIdentity`, `episodeNumberFromTitle`, `isUsableAudioUrl`, `parseOriginFeed` —SimpleXML—,
`buildIndex`, `planSync`) sin red ni WP; orquestación (`syncEpisodes`, `onTransition`, `init`, `cliSync`)
con `wp_remote_get` (tiempo de espera, corte por tamaño, `ETag`/`If-None-Match`), índice en *transient*
con TTL de éxito y TTL de fallo más corto, y marca de intento `_atareao_podcast_audio_attempt`.
Registrado en `atareao-functionality.php` (`require_once` + `\Atareao\PodcastAudio::init()`).

**Comportamiento fijado por el RED** (no por criterio del implementador): un origen inalcanzable cuenta
los episodios como `failed` y no escribe nada; un `mp3-url` válido **sin identidad `.mp3`** cae a la clave
de número (puede ser `ambiguous`/`unmatched`, y si el número casa de forma única sería `ok` sin
escritura), mientras que un `.mp3` válido con identidad desconocida es `divergent`.

## 3. REFACTOR

- [x] 3.1 `just php-lint` sin errores
- [x] 3.2 `just phpcs` limpio en el plugin (una ruta por invocación)
- [x] 3.3 Re-ejecutar `just test-audio`, `just test` y `just test-transcripts` (triple verde)
- [x] 3.4 Revisión de estilo del módulo: constantes documentadas, sin números mágicos, sin estado
      global compartido entre peticiones

#### Evidencia — REFACTOR

```
$ just php-lint          → EXIT=0   66 ficheros sin errores de sintaxis
$ just test-audio        → EXIT=0   Total passed: 140, Total failed: 0
$ just test              → EXIT=0   Total passed: 1068, Total failed: 0
$ just test-transcripts  → EXIT=0   Total passed: 32,   Total failed: 0
```

**`just phpcs` (defaults)**: la receta por omisión arrastra hallazgos **preexistentes** (JS de bloques,
theme y `class-cache-purge.php`), ajenos a este change. Acotado al módulo nuevo
(`phpcs --standard=PSR12 -n includes/class-podcast-audio.php`): **0 errores**. Con warnings, el único del
módulo es el aviso `PSR1.Files.SideEffects` del guard `if (!defined('ABSPATH')) { exit; }`, **idéntico**
al que arrastran todos los módulos del plugin (`class-podcast-feed.php`, `class-seo.php`…). **Cero
errores nuevos.**

**Estilo**: constantes documentadas, sin números mágicos, motor de decisión puro sin estado global; el
único estado por petición vive en el *transient* y en las metas.

#### Ronda de auditoría (2026-11-02) — defectos corregidos

Una revisión independiente encontró dos defectos **bloqueantes** que se corrigieron con RED→GREEN
(la spec no se tocó: ambos incumplían requisitos ya escritos). El RED pasó de 27 escenarios / 96
aserciones a **28 escenarios / 140 aserciones**.

- **B1 — el gancho de publicación no funcionaba en el flujo real.** Se usaba `transition_post_status`,
  que el core dispara **antes** de `save_post` (donde `Metaboxes::saveMetaboxes` persiste `number` y
  `mp3-url`) y en cada `wp_insert_post`. Al publicar, el gancho leía un `number` aún no guardado,
  marcaba el intento y no reparaba; además el metabox pisaba después el `mp3-url`. **Corregido**: el
  gancho es `wp_after_insert_post` (prioridad 20, 4 args), que corre **después** de guardada la meta, y
  `ATTEMPT_META` **solo** se marca cuando el episodio queda resuelto (`repair`/`ok`), nunca en
  `unmatched`/`ambiguous`/`divergent`/fallo. El arnés ahora ejerce el hook vía `do_action()`.
- **B2 — un valor no-URL terminado en `.mp3` no se reparaba.** `planSync` miraba la identidad antes que
  la usabilidad, así que `ata844.mp3`, `/staging/…/ata844.mp3` o `//host/…/ata844.mp3` daban `ok`.
  **Corregido**: la usabilidad se evalúa **antes**; un valor que no es URL absoluta `http(s)` se trata
  como ausente (cae a la clave de número → `repair`). Reproducido por CLI antes y después.
- **Mayores**: la descarga del origen pasa a `wp_safe_remote_get` con `reject_unsafe_urls` y
  `redirection = 0` (anti-SSRF, como el módulo hermano); un XML inválido o sin `<channel>` se trata como
  **fallo** (no se cachea como índice vacío «de éxito» y ya no emite `PHP Warning`); se **elimina** el
  código de `ETag`/`If-None-Match`, que era muerto y cuyo docblock prometía lo que no hacía.
- **Menores**: la caché se consulta antes que el marcador de fallo y el éxito borra el marcador; se
  validan `--limit` (no negativo) y `--format`; `audioIdentity` es insensible a mayúsculas en la
  extensión; un `ATA <n>` explícito gana a un `TxxExx`; la consulta de episodios usa `fields=ids` y
  `no_found_rows`.

## 4. Verificación E2E

- [ ] 4.1 **Ensayo contra producción, sin escribir**: `wp atareao podcast-audio-sync --dry-run` sobre
      los 838 episodios → pegar el recuento por clase y contrastarlo con lo medido en 0.2 y 0.3
      (*833 ya correctos por identidad, 1 divergente (ATA 3), 4 reparables (3 sin url + ATA 552 con
      `"15"`), los sin par (4, 32 y los 3 sin `number`)*)
- [ ] 4.2 **Ejecución real acotada**: `--limit` pequeño → comprobar en producción que el `mp3-url`
      reparado se re-lee y que el `<enclosure>` del feed cambia **solo** en el episodio reparado y
      **ninguno** de los ya correctos (comparar el recuento de `enclosure` del feed antes y después)
- [ ] 4.3 Reparación real del ATA 552 (el caso que hoy publica `url="15"`): dejarla hecha y comprobar
      que el feed **deja de publicar** `url="15"` y publica la URL del origen
- [ ] 4.4 Idempotencia real: segunda ejecución completa → «ya correctos», cero escrituras
- [ ] 4.5 Gancho de publicación: publicar un episodio de prueba (o simular la transición) y comprobar
      que el `mp3-url` se rellena solo y que el guardado **no** se rompe si el origen no responde
- [ ] 4.6 Efecto colateral deseado, comprobarlo: los episodios a los que se repare el audio **vuelven
      a ser transcribibles** por el pipeline de `podcast-transcripts` (el ATA 552 estaba clasificado
      como «sin audio utilizable»); verificar con `--dry-run` que pasa de «sin audio» a pendiente
- [ ] 4.7 Registrar las evidencias en este fichero

#### Evidencia — E2E

*(pendiente)*

## 5. Entrega

- [ ] 5.1 Rama `feature/podcast-audio-sync` desde `development`; commits con gitmoji
      (`✅ test`, `✨ feat`, `💄 style`, `📝 docs`)
- [ ] 5.2 Push y PR a `development` (sin fusionar sin autorización explícita)
- [ ] 5.3 `openspec archive podcast-audio-sync` **solo tras autorización**
- [ ] 5.4 Nota en `proposal.md` y `tasks.md` con lo aprendido: por qué la URL pública no se deriva del
      RSS, qué haría falta para automatizarla de verdad y por qué este comando no sobrescribe nada

## 6. Deuda aceptada (no bloqueante, solo documentada)

- [ ] 6.1 Números ambiguos del origen (667 y 3) y episodios 4 y 32 inexistentes en el origen: se
      reportan, no se resuelven
- [ ] 6.2 Los 3 episodios sin `number` no son emparejables por número y no tienen audio: quedan fuera
      del alcance de la reparación de audio
- [ ] 6.3 La divergencia del ATA 3 (audio recodificado) no se corrige desde el comando: se reporta
      para siempre. Si se quisiera corregir, haría falta un change aparte con orden explícita
- [ ] 6.4 nginx sirve los `.srt` como `application/octet-stream` (heredado del change anterior)
- [ ] 6.5 CI no ejecuta ninguna suite de pruebas (solo `php -l`): añadirlas es un change aparte
- [ ] 6.6 La URL pública de Spotify y su enlace visible quedan fuera de este change: exigen app de
      desarrollador con cuenta Premium, y no hay API para subir audio ni transcripciones. El
      conocimiento medido se conserva en `proposal.md`
