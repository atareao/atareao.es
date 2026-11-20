# podcast-feed Delta

> El requisito de duración no existe hoy como tal en `openspec/specs/podcast-feed/spec.md`: solo hay
> una frase («SHALL declarar `itunes:duration` cuando la duración se conozca») dentro de «Episodio con
> audio y numeración». Por eso este delta se declara como **ADDED** —con un requisito propio— en lugar
> de reescribir un MODIFIED que obligaría a reproducir todos los escenarios vigentes. El texto nuevo
> **refina** aquella frase sin contradecirla.

## ADDED Requirements

### Requirement: Duración del episodio en el feed

Cada `<item>` de un episodio publicado y público SHALL declarar **como máximo un**
`<itunes:duration>` y SHALL declararlo **solo** cuando la duración persistida sea interpretable con la
semántica de Apple: `HH:MM:SS`, `MM:SS` o un entero de segundos. El valor emitido SHALL escaparse para
XML; el sistema SHALL NOT emitir un valor vacío, malformado o fuera de rango.

**Fuente de verdad y espejo.** La duración SHALL persistirse en el metadato canónico `duration`
(`PodcastFeed::DURATION_META`). El metadato heredado `_duration`
(`PodcastFeed::LEGACY_DURATION_META`) —que el tema pinta en la ficha y en la tarjeta del episodio—
SHALL ser un **espejo exacto** del canónico. Todo proceso que escriba la duración —incluido el
importador de episodios— SHALL escribir **ambos** metas con el **mismo valor** y SHALL hacerlo a través
de un **único punto de escritura**, `PodcastDuration::writeDuration( int $postId, string $canonical ):
bool`; SHALL NOT existir ningún otro camino de escritura de la duración. La emisión SHALL leer el
canónico y SHALL usar `_duration` **solo** como respaldo transitorio cuando el canónico esté vacío:
cuando ambos existan y difieran, SHALL prevalecer el canónico y el backfill SHALL igualarlos.

**Normalización de la emisión.** `PodcastFeed::normalizeDuration( string $raw ): string` SHALL devolver
el valor a emitir o la cadena vacía, con este contrato:

- `HH:MM:SS` con horas de uno o más dígitos y minutos y segundos de **exactamente dos dígitos** en
  `00–59` → el valor **literal**, sin relleno, recorte ni reformateo.
- `MM:SS` con minutos y segundos de **exactamente dos dígitos** en `00–59` → el valor **literal**
  (formato admitido por Apple).
- **entero no negativo** (`^[0-9]+$`) → el valor **literal**, interpretado como **segundos** (formato
  admitido por Apple); SHALL NOT convertirse a `HH:MM:SS`, multiplicarse ni dividirse.
- tras `trim()` de los espacios exteriores, **cualquier otro valor** —vacío, no numérico, con signo, con
  espacios internos, con más de tres componentes, o con minutos o segundos fuera de `00–59`— →
  **cadena vacía**, y el tag SHALL omitirse.

La normalización SHALL NOT multiplicar, dividir, redondear ni reinterpretar el valor: está **prohibido**
deducir que un entero o un `MM:SS` significa minutos. La corrección del dato corresponde al backfill,
que toma el valor de Anchor.

**Backfill desde Anchor.** El sistema SHALL ofrecer el subcomando
`wp atareao podcast-duration-backfill [--dry-run] [--limit=<n>] [--source=<ruta>]`:

- **Fuente.** Sin `--source`, SHALL usar la **URL de origen resuelta** como el resto del plugin:
  `get_option('atareao_podcast_origin_rss')` (`PodcastAudio::ORIGIN_RSS_OPTION`) cuando no esté vacía
  y, si lo está, `PodcastAudio::ORIGIN_RSS_DEFAULT` (`https://anchor.fm/s/5a5b39c/podcast/rss`). Hoy
  producción tiene el ajuste **vacío** (`false`), así que el camino normal es el valor por defecto. El
  informe SHALL indicar la **URL de origen usada**. Con `--source=<ruta>` SHALL leer ese fichero local,
  SHALL NOT realizar ninguna petición de red y SHALL indicar en el informe esa ruta como origen.
- **Emparejamiento.** Primero por `anchor-guid` exacto —comparado tras `trim`— contra el `<guid>` del
  item de Anchor. Respaldo cuando el episodio no tenga `anchor-guid` o no haya coincidencia: por número
  de episodio, deducido del título de Anchor con el prefijo `ATA <n>` y comparado con el metadato
  `number`, y **solo si hay exactamente un candidato**. Sin candidato o con varios → **sin
  correspondencia**.
- **Valor.** El `<itunes:duration>` del item homólogo, validado con `normalizeDuration()`; si no valida
  → **ilegible**, y SHALL NOT escribirse. Una duración **cero** —`0`, `0:00`, `00:00` o `00:00:00`, que
  el origen publica cuando no conoce la duración— SHALL considerarse **ilegible** y SHALL NOT
  sobrescribir un valor existente: escribirla borraría un dato bueno.
- **Escritura.** La decisión SHALL tomarse sobre **ambos** metas: el episodio SHALL considerarse al día
  (`sin_cambios`) solo cuando `duration` **y** `_duration` normalizan ya al valor del origen. Si
  cualquiera de los dos difiere —incluido un espejo desincronizado con el canónico correcto— SHALL
  escribirse a través del único punto de escritura (canónico y espejo). SHALL NOT tocar `mp3-url`,
  título, fecha, `anchor-guid`, numeración, orden, adjuntos ni ningún otro metadato.
- **Informe.** SHALL reportar `actualizados`, `sin_cambios`, `sin_correspondencia`, `ilegibles` y
  `pendientes` (dejados fuera por `--limit`), y con `--dry-run` SHALL listar los cambios **sin
  escribir**, con `post_id`, valor canónico y espejo actuales y valor nuevo; si son muchos SHALL
  limitar el listado (primeras 20 entradas) y resumir el resto.
- **Límite.** `--limit=<n>` SHALL acotar el número de **actualizaciones** de la pasada; lo no aplicado
  por el tope SHALL contarse como `pendientes`.
- **Idempotencia.** Una segunda ejecución real sobre la misma fuente SHALL reportar **0 actualizados** y
  SHALL NOT modificar ningún metadato.
- **Origen ilegible.** Un origen inaccesible o con XML inválido SHALL abortar con error y SHALL NOT
  escribir nada.

#### Scenario: Una duración `HH:MM:SS` se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `00:22:11`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>00:22:11</itunes:duration>` sin reformatear

#### Scenario: Una duración `MM:SS` válida se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `22:11`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>22:11</itunes:duration>`, formato que Apple admite

#### Scenario: Un entero de segundos se emite tal cual

- **GIVEN** un episodio con el metadato canónico `duration` igual a `1331`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` declara `<itunes:duration>1331</itunes:duration>`, sin convertirlo a `00:22:11` ni multiplicarlo por 60

#### Scenario: Un componente fuera de rango omite el tag

- **GIVEN** un episodio cuyo metadato canónico `duration` vale `00:75:00`, `22:75` o `00:22:99`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y el feed sigue siendo XML bien formado

#### Scenario: Un valor malformado omite el tag

- **GIVEN** un episodio cuyo metadato canónico `duration` vale `abc`, `1:2:3`, `00:22:11x` o `--5`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y nunca se emite el valor inválido

#### Scenario: Un episodio sin dato omite el tag

- **GIVEN** un episodio sin `duration` y sin `_duration`
- **WHEN** se compone el feed del podcast
- **THEN** su `<item>` no declara `<itunes:duration>` y el resto de sus elementos se emite con normalidad

#### Scenario: El backfill rellena un episodio sin duración

- **GIVEN** un episodio publicado con `anchor-guid` cuyo homólogo de Anchor declara `00:22:04` y que no tiene ningún metadato de duración
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el episodio queda con `duration` y `_duration` iguales a `00:22:04` y el informe lo cuenta en `actualizados`

#### Scenario: El backfill corrige una duración 60 veces corta

- **GIVEN** un episodio con `duration` igual a `00:22` y otro con `duration` igual a `20`, cuyos homólogos de Anchor declaran `00:22:11` y `00:20:37`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** sus canónicos pasan a `00:22:11` y `00:20:37`, sus espejos coinciden y el informe los cuenta en `actualizados`

#### Scenario: El backfill es idempotente

- **GIVEN** una ejecución real del backfill ya aplicada sobre la misma fuente
- **WHEN** se ejecuta de nuevo `wp atareao podcast-duration-backfill`
- **THEN** el informe da `actualizados = 0`, todos los episodios emparejados quedan en `sin_cambios` y ningún metadato cambia

#### Scenario: `--dry-run` no escribe

- **GIVEN** episodios con duraciones distintas de las de Anchor
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --dry-run`
- **THEN** lista los cambios que haría y no escribe ningún metadato: los valores de `duration` y `_duration` quedan como estaban

#### Scenario: Un episodio sin correspondencia se reporta y no se modifica

- **GIVEN** un episodio cuyo `anchor-guid` no existe en Anchor y cuyo `number` tampoco permite un único candidato
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe lo cuenta en `sin_correspondencia`, conserva su `duration` y su `_duration` intactos y no inventa una duración

#### Scenario: El espejo de metas queda coherente

- **GIVEN** cualquier episodio actualizado por el backfill
- **WHEN** se comparan sus metadatos `duration` y `_duration`
- **THEN** ambos tienen el mismo valor, y el `<itunes:duration>` del feed es el del canónico

#### Scenario: El backfill corrige un espejo desincronizado

- **GIVEN** un episodio con `duration` ya correcto (`00:45:00`) y `_duration` distinto (`00:40:00`), cuyo homólogo de Anchor declara `00:45:00`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe lo cuenta en `actualizados`, `_duration` pasa a `00:45:00`, el canónico se conserva y una segunda pasada lo deja en `sin_cambios`

#### Scenario: Una duración cero no sobrescribe

- **GIVEN** un episodio con `duration` y `_duration` iguales a `00:44:44` cuyo homólogo de Anchor declara `<itunes:duration>0</itunes:duration>`
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el informe lo cuenta en `ilegibles` y conserva intactos ambos metas

#### Scenario: `--source` evita la red

- **GIVEN** un fichero local con el RSS de Anchor y un episodio emparejable
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --source=<ruta>`
- **THEN** el comando usa ese fichero, no realiza peticiones de red y aplica la duración que contiene

#### Scenario: El ajuste de origen está vacío

- **GIVEN** el ajuste `atareao_podcast_origin_rss` vacío o no definido, como está hoy en producción
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill` sin `--source`
- **THEN** el comando resuelve `PodcastAudio::ORIGIN_RSS_DEFAULT`, descarga ese RSS y aplica las duraciones en vez de abortar por falta de fuente

#### Scenario: El informe indica la URL de origen

- **GIVEN** una ejecución del backfill con `--source=<ruta>` y otra sin él
- **WHEN** se inspecciona el informe de cada una
- **THEN** la primera indica la ruta del fichero y la segunda la URL resuelta —configurada o por defecto—, de modo que siempre se sepa de dónde salieron las duraciones

#### Scenario: `--limit` acota las actualizaciones

- **GIVEN** cinco episodios con duración distinta de la de Anchor
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill --limit=2`
- **THEN** actualiza como máximo dos, informa los tres restantes como `pendientes` y una pasada posterior los completa

#### Scenario: Un origen ilegible no escribe nada

- **GIVEN** un origen inaccesible o con XML inválido
- **WHEN** se ejecuta `wp atareao podcast-duration-backfill`
- **THEN** el comando termina con error, informa la causa y no escribe ningún metadato