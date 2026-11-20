# podcast-duration-backfill — Duración real del episodio en el feed

## Qué es

Corrige la duración que el feed del podcast declara en `<itunes:duration>` y rellena la que falta,
tomando el dato del RSS público de Anchor (Spotify for Podcasters).

El problema medido en producción el 2026-11-20: de **850 items**, **82 no declaraban duración**, **476**
usaban `HH:MM`, **285** minutos sueltos y **7** `HH:MM:SS`; **761** valores eran **60× cortos**. La causa
no era la fuente —Anchor declara **850/850** en `HH:MM:SS`— sino la emisión, que publicaba el metadato
**verbatim** sin validar la semántica.

El change OpenSpec vive en `openspec/changes/2026-11-20-podcast-duration-backfill/` (`proposal.md`,
`tasks.md` y el delta en `specs/podcast-feed/spec.md`).

## Ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` (`\Atareao\PodcastFeed`) —
  `normalizeDuration()` y la emisión de `itunes:duration`.
- `wp-content/plugins/atareao-functionality/includes/class-podcast-duration.php`
  (`\Atareao\PodcastDuration`) — `writeDuration()`, `backfill()` y el subcomando `cliBackfill()`.
- `wp-content/plugins/atareao-functionality/includes/class-podcast-guid.php` — `createEpisode()` delega
  la escritura en el punto único.
- `wp-content/plugins/atareao-functionality/atareao-functionality.php` — `require_once` e `init()`.
- `tests/podcast-duration/test_duration_emission.php`, `test_backfill.php` y
  `fixtures/anchor-rss-duration.xml` — arneses del contrato.
- `.justfile` — receta `test-duration`, integrada en `test`.
- `.github/workflows/ci.yml` — paso «Test PHP harnesses» del job `lint` (sin contenedor).

## Cómo funciona

### Normalización (emisión)

`PodcastFeed::normalizeDuration( string $raw ): string` devuelve el valor **literal** o la cadena vacía:

| Entrada | Salida | Motivo |
|---|---|---|
| `00:22:11` | `00:22:11` | `HH:MM:SS`, minutos y segundos en `00–59` |
| `1:02:03` | `1:02:03` | horas de uno o más dígitos, sin rellenar |
| `22:11` | `22:11` | `MM:SS` admitido por Apple |
| `1331` | `1331` | entero no negativo, **segundos** |
| `00:75:00`, `22:75`, `00:22:99` | `''` | componente fuera de rango |
| `abc`, `1:2:3`, `00:22:11x`, `--5` | `''` | malformado |
| `''`, solo espacios | `''` | sin dato |

**No se reescala ni se reinterpreta.** Está prohibido deducir que `00:22` o `20` son minutos y
multiplicar por 60: la semántica de Apple ya dice que un entero o un `MM:SS` son **segundos**. Adivinar
la unidad es justo lo que produjo los valores 60× cortos. La corrección del dato corresponde al
backfill; aquí un valor inválido simplemente omite el tag.

La emisión lee el canónico `duration` y usa `_duration` **solo** como respaldo transitorio cuando el
canónico está vacío. Si ambos existen y difieren, gana el canónico.

### Punto único de escritura

`PodcastDuration::writeDuration( int $postId, string $canonical ): bool` es el **único** camino de
escritura de la duración. Escribe `duration` (`PodcastFeed::DURATION_META`) y su espejo `_duration`
(`PodcastFeed::LEGACY_DURATION_META`) con el mismo valor, y **solo si difiere**. El importador de
episodios (`PodcastGuid::createEpisode()`) también delega aquí: un alta escribe canónico y espejo.

### Backfill

`backfill()` lee el origen (URL resuelta o fichero `--source`) y empareja cada episodio publicado:

1. **`anchor-guid`** exacto, comparado tras `trim`, contra el `<guid>` del item.
2. Respaldo por **número**: el `ATA <n>` deducido del título de Anchor
   (`PodcastAudio::episodeNumberFromTitle()`) contra el metadato `number`, y **solo si hay exactamente
   un candidato**. Sin candidato o con varios → `sin_correspondencia`.
3. El `<itunes:duration>` del homólogo se valida con `normalizeDuration()`; si no valida, o si es una
   duración **cero** (`0`, `0:00`, `00:00`, `00:00:00`, que el origen publica cuando no la conoce) →
   `ilegibles` y no se escribe. Una duración cero nunca borra un valor existente.
4. La decisión se toma sobre **ambos** metas: solo es `sin_cambios` cuando `duration` **y** `_duration`
   normalizan ya al valor del origen. Si cualquiera difiere —incluido un **espejo desincronizado** con
   el canónico correcto— se escribe a través de `writeDuration()`. No se toca ningún otro metadato.

El informe devuelve `actualizados`, `sin_cambios`, `sin_correspondencia`, `ilegibles`, `pendientes`,
`origin`, `origin_error` y, en el ensayo, `changes` con `id`, `canonical`, `legacy` y `to`.

### Fuente de origen

`atareao_podcast_origin_rss` cuando no está vacío; si lo está (caso real de producción: `false`), se usa
`PodcastAudio::ORIGIN_RSS_DEFAULT` = `https://anchor.fm/s/5a5b39c/podcast/rss`. El informe siempre
indica la URL o la ruta usada.

## Cómo se ejecuta

```bash
# Ensayo: lista los cambios y no escribe
wp atareao podcast-duration-backfill --dry-run

# Ejecución real, por tandas si hace falta
wp atareao podcast-duration-backfill --limit=100

# Sin red: lee un RSS local
wp atareao podcast-duration-backfill --source=/tmp/anchor.xml --dry-run
```

Salida del comando:

```
Origen: https://anchor.fm/s/5a5b39c/podcast/rss
Actualizados: 761
Sin cambios: 89
Sin correspondencia: 0
Ilegibles: 0
Pendientes: 0
```

- `--limit=<n>` acota las **actualizaciones** de la pasada; lo no aplicado se cuenta como `pendientes`.
  `--limit=0` significa sin límite.
- `--dry-run` rotula «Habría actualizado: N», lista cada cambio como
  `id: duration=<actual> _duration=<actual> → <nuevo>` y no escribe ningún metadato. Con muchos
  cambios lista los 20 primeros y resume el resto («… y N más»).
- Origen inaccesible, HTTP no 2xx o XML inválido → error de WP-CLI, `origin_error`, y **cero
  escrituras**.

### Despliegue

El módulo se carga desde `atareao-functionality.php` y registra el subcomando solo si `WP_CLI` está
definido, así que no hay pasos extra: publicar la versión del plugin y ejecutar el comando en el
contendor de WP-CLI. Verificación:

```bash
wp atareao podcast-duration-backfill --help
wp atareao podcast-duration-backfill --dry-run
```

### Rollback

Los valores anteriores no se versionan en la base de datos. Para revertir una pasada concreta hay que
restaurar el backup previo de `wp_postmeta` (o volver a ejecutar el importador desde un origen con los
valores antiguos). El backfill **no toca** audio, título, fecha, guid, numeración, orden ni adjuntos,
de modo que revertir la duración no afecta al resto del episodio.

## Verificación

```bash
just test-duration   # 43 PASS (emisión) + 102 PASS (backfill) + 5 PASS (degradación del importador)
just php-lint
just test
```

## Decisiones y follow-ups

- **Sin heurística de unidades.** El entero y `MM:SS` se emiten literales; la corrección viene del
  origen, no de una multiplicación.
- **Decisión sobre ambos metas.** `sin_cambios` exige que canónico y espejo coincidan con el origen;
  un espejo desincronizado cuenta como actualización y se iguala. Es lo que evita que el tema siga
  pintando una duración vieja.
- **Cero es ilegible en el backfill.** El origen publica `0` para items sin duración conocida;
  escribirlo borraría un valor bueno. En la **emisión** un `0` persistido se sigue emitiendo tal cual:
  es el dato que hay.
- **Número solo con candidato único.** Un `ATA <n>` repetido es ambiguo: se reporta, no se adivina.
  *Riesgo conocido y aceptado:* si dos items distintos comparten número y el episodio no tiene
  `anchor-guid`, el respaldo puede aplicar el dato del homónimo equivocado. Se acepta porque el
  emparejamiento por guid es el camino normal (el 100 % del archivo real lo tiene) y el respaldo exige
  candidato único.
- **Degradación del importador.** `PodcastGuid::createEpisode()` escribe canónico y espejo a través de
  `PodcastDuration::writeDuration()`. Si el módulo no estuviera cargado (carga parcial anómala), escribe
  solo el canónico en vez de abortar con «Class not found»; el backfill rellena el espejo después.
- **`_duration` es deuda transitoria.** Se mantiene como espejo porque el tema lo pinta en la ficha y
  la tarjeta (`single-podcast.php`, `template-parts/content-podcast.php`). Cuando el tema lea el
  canónico `duration`, se podrá retirar el espejo y el respaldo de lectura de la emisión.
- **Sin caché de origen propia.** El backfill es una operación manual y poco frecuente; reutiliza el
  parser de `PodcastGuid` y no añade un transient que pueda ocultar un origen actualizado.