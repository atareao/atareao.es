# podcast-guid Delta

## Purpose

Reconciliar el archivo de WordPress con el RSS de Anchor importando los episodios que existan en el
origen y falten en WordPress, con sus metadatos, de forma idempotente y auditable.

## ADDED Requirements

### Requirement: Detección del episodio ausente del origen

El sistema SHALL ofrecer un subcomando WP-CLI que compare los items del RSS de Anchor con las entradas
del CPT `podcast` de WordPress y determine qué episodios del origen **no tienen homólogo** en
WordPress. La homología SHALL resolverse por el **guid de Anchor** (metadato `anchor-guid`) y, como
respaldo, por el **número de episodio** (metadato `number`). Un item de Anchor SHALL considerarse
ausente solo cuando **ni** su guid **ni** su número casen con ninguna entrada de WordPress.

El contrato de integración SHALL ser:

- Comando: `wp atareao podcast-guid-import-missing [--dry-run] [--author=<id>]`, registrado con
  `\WP_CLI::add_command( 'atareao podcast-guid-import-missing', <callback> )` cuando `WP_CLI` esté
  definido, junto al ya existente `wp atareao podcast-guid-map`.
- Callback: `callback( array $args = array(), array $assoc = array() ): void` — `$args` son los
  argumentos posicionales y `$assoc` los asociativos; el flag `--dry-run` SHALL leerse de
  `$assoc['dry-run']` como booleano y el autor forzado de `$assoc['author']` como entero positivo.
- Fuente del origen: el mismo RSS de Anchor que ya consume el mapeo de guid (`PodcastGuid`), sin
  introducir una segunda URL de origen y **sin** flag `--url`: para los tests se inyecta un fixture
  con un filtro/stub.

#### Scenario: Se detecta el episodio que falta

- **WHEN** Anchor declara 850 items y WordPress tiene 849 entradas del CPT `podcast`, faltando el episodio de número 386
- **THEN** el comando reporta **1** episodio ausente: el 386, con su guid de Anchor `3974e7ad-1194-4fac-a0f9-b5995e1be1bd`

#### Scenario: Un episodio ya presente no se considera ausente

- **WHEN** un item de Anchor casa por guid o por número con una entrada de WordPress
- **THEN** ese item no se reporta como ausente y no se propone para importar

#### Scenario: El origen inaccesible no provoca escrituras

- **WHEN** el RSS de Anchor no se puede obtener o no es XML válido
- **THEN** el comando no escribe ninguna entrada ni metadato y termina reportando el error

#### Scenario: Un item del origen sin guid ni número se reporta, no se adivina

- **WHEN** un item de Anchor no declara guid y no permite deducir un número de episodio
- **THEN** el comando lo reporta como no importable y no crea ninguna entrada para él

### Requirement: Creación del episodio ausente con sus metadatos

Para cada episodio ausente, el sistema SHALL crear una entrada del CPT `podcast` con estado `publish`,
cuyo título SHALL ser el título del item de Anchor y cuya fecha de publicación SHALL corresponder al
`pubDate` del origen, interpretado en la zona horaria del sitio (`Europe/Madrid`). El autor SHALL ser el
del episodio `podcast` más reciente, salvo que se fuerce con `--author=<id>`. La entrada SHALL NOT
recibir términos de taxonomía. La entrada SHALL recibir los metadatos que el feed consume, con estas
claves y tipos:

- `anchor-guid` (string): el guid del item de Anchor, **verbatim** (UUID desnudo, p. ej.
  `3974e7ad-1194-4fac-a0f9-b5995e1be1bd`).
- `number` (int): el número de episodio del origen (p. ej. `386`).
- `season` (int): la temporada del origen (p. ej. `4`).
- `duration` (string): la duración del item de Anchor, **verbatim** (el valor literal de
  `<itunes:duration>`, en su formato `hh:mm:ss`, p. ej. `00:22:04`), sin normalizar ni reformatear.
- `mp3-url` (string): la URL del `<enclosure>` del item de Anchor, **verbatim**.

El comando SHALL NOT inventar valores ausentes en el origen: cuando un metadato no exista en el item de
Anchor, SHALL omitirse en lugar de rellenarse con un valor arbitrario.

#### Scenario: El episodio 386 se crea con sus metadatos

- **WHEN** se ejecuta el importador y el episodio 386 está ausente
- **THEN** se crea una entrada `podcast` titulada `ATA386 Aventuras y desventuras con Docker y las actualizaciones automáticas`, con `anchor-guid=3974e7ad-1194-4fac-a0f9-b5995e1be1bd`, `number=386`, `season=4`, `duration=00:22:04` (verbatim de Anchor) y `mp3-url` igual al enclosure de Anchor

#### Scenario: El episodio importado se publica

- **WHEN** el importador crea un episodio ausente
- **THEN** la entrada se crea con estado `publish`

#### Scenario: El autor se hereda del episodio más reciente

- **WHEN** el importador crea un episodio ausente y no se ha forzado un autor
- **THEN** la entrada hereda el autor del episodio `podcast` más reciente

#### Scenario: El autor se puede forzar con --author

- **WHEN** se ejecuta el importador con `--author=<id>` y hay un episodio ausente
- **THEN** la entrada creada declara ese autor y no el heredado

#### Scenario: El episodio importado no recibe términos de taxonomía

- **WHEN** el importador crea un episodio ausente
- **THEN** la entrada no recibe ningún término de taxonomía

#### Scenario: La fecha se interpreta en la zona del sitio

- **WHEN** el item de Anchor declara `pubDate` `Thu, 19 May 2022 03:27:16 GMT`
- **THEN** la entrada se publica con fecha `2022-05-19 05:27:16` en `Europe/Madrid`

#### Scenario: Un metadato ausente en el origen se omite

- **WHEN** el item de Anchor no declara `itunes:season`
- **THEN** la entrada creada no escribe el metadato `season` en lugar de rellenarlo con un valor inventado

#### Scenario: El episodio creado no declara transcripción

- **WHEN** el item de Anchor no publica transcript
- **THEN** la entrada creada no escribe el metadato `transcript-srt` y el feed no declara `<podcast:transcript>` para ella

### Requirement: Idempotencia y simulación del importador

El subcomando SHALL ser **idempotente**: ejecutado dos veces sobre el mismo origen y el mismo archivo de
WordPress, la segunda ejecución SHALL crear **0** entradas. Con el flag `--dry-run`, el comando SHALL
reportar lo que haría **sin escribir** ninguna entrada ni metadato. El comando SHALL reportar, en ambos
modos, cuántos episodios ha detectado y cuántos ha creado (o habría creado).

#### Scenario: La segunda pasada no inserta nada

- **WHEN** se ejecuta el importador dos veces seguidas sin cambios en el origen ni en WordPress
- **THEN** la primera pasada crea el episodio ausente y la segunda crea **0** entradas

#### Scenario: Dry-run no escribe

- **WHEN** se ejecuta el importador con `--dry-run` y hay un episodio ausente
- **THEN** se reporta el episodio que se crearía y el número de entradas del CPT `podcast` no cambia

#### Scenario: Dry-run seguido de ejecución real

- **WHEN** se ejecuta con `--dry-run` y después sin el flag
- **THEN** solo la segunda ejecución escribe, y escribe exactamente el episodio que el `--dry-run` había reportado

#### Scenario: El comando reporta el recuento

- **WHEN** el importador termina
- **THEN** informa del número de episodios ausentes detectados y del número de entradas creadas (o que habría creado en `--dry-run`)
