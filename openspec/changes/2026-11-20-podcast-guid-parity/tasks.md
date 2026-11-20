# Tasks: Paridad exacta del guid del podcast y del archivo completo

> Estado: **PENDIENTE DE APROBACIÓN** (2026-11-20). Ninguna casilla se marca sin evidencia CLI pegada
> literalmente. Regla dura: RED → GREEN → REFACTOR. Alcance: emisión literal del `<guid>` e
> importación idempotente del episodio ausente. **No se migra el feed.**
>
> **Dependencia**: `podcast-feed-soberania` (no archivado) aporta el archivo completo, el metadato
> `anchor-guid` y el mapeo determinista. Estas tareas asumen ese estado.
>
> Las casillas de **verificación sobre el feed publicado** dependen de un despliegue que no forma parte
> de este change.

## 0. Contexto y decisiones previas

- [ ] 0.1 Confirmar la causa del prefijo `http://`: `add_filter( 'the_guid', 'esc_url' )` en
      `wp-includes/default-filters.php:271` y `esc_url()` anteponiendo `http://` a cadenas sin esquema;
      verificar con evidencia que `the_guid()` corre el filtro **después** de `get_the_guid()`
- [ ] 0.2 Reproducir la comparación cruda de los dos feeds y confirmar **844/849** guids que difieren
      solo por el prefijo y **5/5** guids tipo URL que coinciden exactos
- [ ] 0.3 Confirmar el hueco del archivo: numeración WP de 1 a 850 con **único** hueco en el **386**;
      Anchor con 850 items y WP con 849
- [ ] 0.4 Verificar los datos de origen del 386 (título, `pubDate`, temporada, número, duración, guid,
      enclosure, sin transcript) contra el RSS de Anchor y pegarlos como evidencia
- [ ] 0.5 Registrar las metas de referencia del episodio hermano (ID 16216) que el importador debe
      reproducir en forma: `anchor-guid`, `duration`, `mp3-url`, `number`, `season`, `transcript-srt`
- [x] 0.6 **RESUELTA** — decisiones aprobadas por el titular el 2026-11-20 (ver «Decisiones» del
      `proposal.md` y el contrato de `specs/podcast-guid/spec.md`): (1) `duration` **verbatim** de
      Anchor (`00:22:04`); (2) estado `publish`; (3) autor heredado del episodio `podcast` más
      reciente, overridable con `--author=<id>`, sin términos de taxonomía; (4) fuente = la opción ya
      configurada (`PodcastAudio::ORIGIN_RSS_OPTION`), sin flag `--url`

## 1. RED — contrato de la emisión del guid

- [ ] 1.1 Arnés `tests/podcast-guid-parity/test_guid_emission.php` (estilo del proyecto: contador
      propio, `exit(0/1)`, `tests/wp-stubs.php`, dobles en memoria) y receta `just test-guid-parity` en
      el `.justfile`; verificar que la receta existe y arranca
- [ ] 1.2 Prueba *el guid desnudo se emite sin esquema*: con `anchor-guid` = UUID desnudo, el `<guid>`
      no contiene `http://`; verificar en rojo antes de implementar
- [ ] 1.3 Prueba *el guid ya-URL se emite verbatim*: con `anchor-guid` = `https://anchor.fm/…`, el
      `<guid>` es idéntico
- [ ] 1.4 Prueba *la corrección es posterior al escape del núcleo*: simular el `esc_url` del núcleo
      (guid → `http://<uuid>`) y comprobar que el filtro del feed restituye el valor persistido
- [ ] 1.5 Prueba *fuera del feed del podcast no hay efecto*: fuera de `isPodcastFeed()` el filtro
      devuelve `$guid` sin cambios
- [ ] 1.6 Prueba *el guid sigue siendo estable entre composiciones* y *no hay guids duplicados*
- [ ] 1.7 Evidencia RED pegada literalmente: `just test-guid-parity` con **exit 1** (y `just test` en
      verde en lo preexistente)

## 2. GREEN — emisión literal del guid

- [ ] 2.1 `class-podcast-feed.php`: `add_filter( 'the_guid', array(__CLASS__, 'filterGuidEmission'), 20, 2 )`
      (prioridad **> 10**, tras `esc_url`); verificar que la firma registrada es la correcta
- [ ] 2.2 `class-podcast-feed.php`: callback `filterGuidEmission( string $guid, int $postId ): string`
      que, solo con `isPodcastFeed()` true, devuelve `esc_html( self::itemGuid( $postId ) )`; si el guid
      resuelto está vacío o no es el feed, devuelve `$guid` intacto. **Nunca** `esc_url`
- [ ] 2.3 Documentar en el docblock del callback por qué la prioridad es > 10 y por qué se usa
      `esc_html` en lugar de `esc_url` (contrato con `the_guid`/`esc_url` del núcleo)
- [ ] 2.4 Evidencia GREEN pegada literalmente: `just test-guid-parity` (**exit 0**) + `just php-lint` +
      `just test`

## 3. RED — contrato del importador

- [ ] 3.1 Arnés `tests/podcast-guid-parity/test_import_missing.php` con el mismo estilo y dobles en
      memoria (RSS de Anchor de fixture + almacén de posts/metas); añadirlo a la receta
      `just test-guid-parity`
- [ ] 3.2 Fixture del RSS de Anchor con, al menos, el item 386 (guid `3974e7ad-…`, `pubDate`
      `Thu, 19 May 2022 03:27:16 GMT`, temporada 4, duración `00:22:04`, enclosure de Anchor) y los
      items homólogos ya presentes en WordPress
- [ ] 3.3 Prueba *se detecta el episodio que falta*: 850 items en el origen, 849 en WP → **1** ausente,
      el 386
- [ ] 3.4 Prueba *un episodio ya presente no se considera ausente* (por guid y por número)
- [ ] 3.5 Prueba *el episodio 386 se crea con sus metadatos*: post `podcast` con título, fecha
      `2022-05-19 05:27:16` en `Europe/Madrid`, `anchor-guid`, `number=386`, `season=4`, `mp3-url` y
      `duration=00:22:04` **verbatim de Anchor**; estado `publish`
- [ ] 3.6 Prueba *el autor se hereda del episodio más reciente* y *se puede forzar con `--author`*;
      *el episodio importado no recibe términos de taxonomía*
- [ ] 3.7 Prueba *un metadato ausente en el origen se omite* (sin `itunes:season` no se escribe
      `season`) y *el episodio sin transcript no escribe `transcript-srt`*
- [ ] 3.8 Prueba *la segunda pasada no inserta nada* (idempotencia) y *`--dry-run` no escribe*
      (el número de entradas del CPT no cambia)
- [ ] 3.9 Prueba *el origen inaccesible o XML inválido no provoca escrituras* y *un item sin guid ni
      número se reporta, no se adivina*
- [ ] 3.10 Evidencia RED pegada literalmente: `just test-guid-parity` con **exit 1** para los escenarios
      nuevos, manteniendo en verde los de §1

## 4. GREEN — subcomando `podcast-guid-import-missing`

- [ ] 4.1 `class-podcast-guid.php`: registrar
      `\WP_CLI::add_command( 'atareao podcast-guid-import-missing', array(__CLASS__, 'cliImportMissing') )`
      en `init()`, junto a `podcast-guid-map`, solo si `WP_CLI` está definido
- [ ] 4.2 Implementar `cliImportMissing( array $args = array(), array $assoc = array() ): void` con
      `--dry-run` leído de `$assoc['dry-run']` y `--author` de `$assoc['author']`; delegar la lógica en
      un método público testeable `importMissingEpisodes(array $options): array` (mismo patrón que
      `mapEpisodes`/`cliMap`); reutilizar `parseOriginFeed()` y el índice de homología existentes (sin
      duplicar la lógica de emparejamiento)
- [ ] 4.3 Crear el post `podcast` (`wp_insert_post`, estado `publish`, fecha del `pubDate` en la zona
      del sitio) y escribir los metas `anchor-guid`, `number`, `season`, `duration`, `mp3-url` con las
      constantes de meta ya definidas (`PodcastFeed::GUID_META`, `NUMBER_META`, `SEASON_META`,
      `DURATION_META`, `AUDIO_META`), sin literales sueltos; autor heredado del episodio más reciente o
      `--author`
- [ ] 4.4 Respetar `--dry-run` (cero escrituras) y reportar con `WP_CLI::log`/`WP_CLI::success` el
      recuento de detectados y creados
- [ ] 4.5 Evidencia GREEN pegada literalmente: `just test-guid-parity` (**exit 0**) + `just php-lint` +
      `just test`

## 5. REFACTOR — limpieza y estilo

- [ ] 5.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [ ] 5.2 Reutilizar constantes y helpers existentes; documentar el «primer patrón que casa» de la
      homología (guid antes que número) y por qué
- [ ] 5.3 Re-ejecutar `just test-guid-parity`, `just test-feed-soberania` y `just test` tras el
      refactor, pegando la evidencia

## 6. Documentación

- [ ] 6.1 `docs/podcast-guid-parity.md`: qué es, ficheros implicados, cómo funciona (filtro y
      subcomando), variables/config, cómo habilitar/desplegar, decisiones y follow-ups; verificar que
      los comandos documentados se ejecutan tal cual
- [ ] 6.2 Actualizar el README del plugin con el contrato nuevo (emisión literal del guid y subcomando
      de importación); verificar que el comando `wp atareao podcast-guid-import-missing --dry-run`
      aparece documentado tal cual
- [ ] 6.3 Dejar registrada la respuesta a las preguntas abiertas 1–4 del `proposal.md` en
      `docs/podcast-guid-parity.md`, marcándolas como decididas

## 7. Integración y cierre

- [ ] 7.1 Paridad item a item del guid contra el feed real de Anchor sobre los items alcanzables hoy
      (0 distintos por prefijo); los 849 completos quedan como verificación dependiente de despliegue
- [ ] 7.2 Verificar que el feed principal `/feed/` no cambia y que ningún guid ajeno al podcast se
      altera (cubierto por el arnés §1.5)
- [ ] 7.3 Auditoría del diff (corrección, seguridad, contrato del feed y del subcomando)
- [ ] 7.4 PR a `development` con CI `lint` en verde
- [ ] 7.5 `openspec archive 2026-11-20-podcast-guid-parity` tras el merge
