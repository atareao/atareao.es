# podcast-transcripts Delta

## ADDED Requirements

### Requirement: Post-proceso opt-in del texto transcrito

El proceso SHALL ofrecer un paso de **post-proceso** que restaure **puntuación y mayúsculas** del texto del SRT producido por el reconocedor, **sin volver a transcribir** y **sin alterar el audio**. El paso SHALL activarse explícitamente (opción `--postprocess`); si no se solicita, el proceso SHALL comportarse **como hoy**, sin reescribir puntuación ni mayúsculas.

#### Scenario: Sin post-proceso, el SRT es el de hoy

- **WHEN** se transcribe un episodio sin `--postprocess`
- **THEN** el SRT adjuntado es el saneado actual, sin restauración de puntuación ni mayúsculas

#### Scenario: Con post-proceso, la puntuación y las mayúsculas se restauran

- **WHEN** se transcribe un episodio con `--postprocess`
- **THEN** el SRT adjuntado presenta puntuación y mayúsculas restauradas

### Requirement: Invariancia de la rejilla temporal y de las palabras

El SRT post-procesado SHALL conservar **exactamente los mismos bloques y timecodes** que el SRT original: mismo número de bloques, mismo orden y mismos `inicio --> fin` en cada uno. El proceso SHALL NOT alterar `inicio` ni `fin`. La **secuencia de palabras** SHALL ser idéntica a la del SRT original, comparando con la puntuación y las mayúsculas normalizadas, **salvo las sustituciones declaradas explícitamente en el glosario**. Si en un bloque el resultado de un motor viola la invariancia, ese bloque SHALL conservar su texto original.

#### Scenario: Mismos bloques y timecodes

- **WHEN** se compara el SRT original y el post-procesado
- **THEN** el número de bloques y los timecodes de cada bloque coinciden en orden

#### Scenario: Misma secuencia de palabras

- **WHEN** se normaliza (minúsculas, sin signos) original y post-procesado y se compara la secuencia de palabras
- **THEN** es idéntica, salvo los alias declarados en el glosario

#### Scenario: Degradación segura por bloque

- **WHEN** un motor devuelve para un bloque un texto que rompe la invariancia
- **THEN** ese bloque conserva el texto original y el resto puede post-procesarse

### Requirement: Glosario de términos del programa

El proceso SHALL aceptar un **glosario** de términos con su **grafía canónica** y SHALL aplicarla a las apariciones del término sin distinguir mayúsculas/minúsculas. Cuando el glosario declare un **alias** (p. ej. `slim book` → `Slimbook`), el proceso SHALL aplicar la sustitución. El proceso SHALL NOT introducir en el texto palabras o alias **no declarados** en el glosario.

#### Scenario: Grafía canónica

- **WHEN** el texto contiene `gitea` y el glosario declara `Gitea`
- **THEN** el resultado escribe `Gitea`

#### Scenario: Alias declarado

- **WHEN** el glosario declara el alias `slim book` → `Slimbook` y el texto lo contiene
- **THEN** se aplica la sustitución; si el alias no está declarado, el texto NO se inventa

### Requirement: Idempotencia y reanudación del post-proceso

Aplicar el post-proceso dos veces al mismo SRT SHALL producir el mismo resultado: la segunda pasada SHALL NOT modificar el fichero. Una ejecución interrumpida SHALL poder reanudarse sin rehacer el trabajo ya completado.

#### Scenario: Segunda pasada sin cambios

- **WHEN** se ejecuta el post-proceso sobre un SRT ya post-procesado
- **THEN** el fichero no se modifica

#### Scenario: Reanudación

- **WHEN** una ejecución se interrumpe a media lista y se relanza
- **THEN** solo se procesan los episodios pendientes

### Requirement: Simulación del post-proceso

El proceso SHALL aceptar `--dry-run`: SHALL informar de los episodios y bloques que cambiaría **sin escribir** ningún fichero, **sin subir** nada y **sin modificar** la meta `transcript-srt`.

#### Scenario: Dry-run no escribe

- **WHEN** se ejecuta con `--dry-run`
- **THEN** no se escribe ningún fichero, no se sube nada y la meta de los episodios queda intacta

### Requirement: Backfill reversible de las transcripciones existentes

El proceso SHALL poder aplicar el post-proceso a las transcripciones **ya adjuntas** (backfill de los episodios existentes). Al reemplazar el contenido del adjunto SHALL conservar el SRT **original** de forma **recuperable**, de modo que la operación sea **reversible**. El **ID del adjunto** y el valor de la meta `transcript-srt` SHALL permanecer sin cambios, y la emisión del feed SHALL ser la misma antes y después.

#### Scenario: El adjunto y la meta no cambian

- **WHEN** se hace el backfill de un episodio
- **THEN** el ID de adjunto y el valor de `transcript-srt` son los mismos, con el texto mejorado

#### Scenario: Reversión

- **WHEN** se solicita revertir el post-proceso de un episodio
- **THEN** se restaura el SRT original conservado

#### Scenario: Feed intacto

- **WHEN** el feed se sirve tras el backfill
- **THEN** sigue emitiendo `<podcast:transcript>` igual que antes del backfill

### Requirement: Validez SubRip del resultado y ausencia de dependencia en el runtime

El SRT post-procesado SHALL seguir siendo **SubRip válido** según el validador existente `validate_srt` (bloques numerados, timecodes `HH:MM:SS,mmm --> HH:MM:SS,mmm`, texto no vacío). El post-proceso SHALL ejecutarse en el **proceso batch externo**; el sitio SHALL NOT ejecutar el post-proceso en ninguna petición, composición del feed o tarea programada.

#### Scenario: El SRT post-procesado valida

- **WHEN** se pasa el SRT post-procesado por `validate_srt`
- **THEN** no se reporta ningún error

#### Scenario: El runtime del sitio no post-procesa

- **WHEN** se sirve el feed o una página del sitio
- **THEN** no se ejecuta el post-proceso en el proceso de la petición
