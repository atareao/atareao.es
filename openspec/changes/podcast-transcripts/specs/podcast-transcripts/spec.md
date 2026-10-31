# Podcast Transcripts Delta

## Purpose

El proceso batch que **produce** las transcripciones del podcast y las enlaza con el contrato que el feed ya consume: descarga el audio remoto de cada episodio pendiente, lo transcribe a SubRip, lo adjunta a la biblioteca de medios y escribe el ID del adjunto en la meta `transcript-srt`. Fija la idempotencia y la reanudación, el aislamiento de fallos con contabilidad, la validez del SRT producido y los límites de ejecución (solo episodios publicados, `--limit`, `--dry-run`), dejando fuera del runtime del sitio cualquier descarga o reconocimiento de voz.

## ADDED Requirements

### Requirement: Producción fuera del runtime del sitio

Las transcripciones SHALL generarse por un **proceso batch externo**, nunca en tiempo de ejecución del sitio. El sitio SHALL NOT descargar el audio de un episodio ni ejecutar reconocimiento de voz en ninguna petición, composición del feed o tarea programada.

#### Scenario: El runtime del sitio no transcribe

- **WHEN** se sirve el feed o cualquier página del sitio
- **THEN** no se descarga audio ni se ejecuta reconocimiento de voz en el proceso de la petición

#### Scenario: La transcripción procede de un proceso batch

- **WHEN** un episodio pasa a tener transcripción
- **THEN** el adjunto y la meta los escribió un proceso batch externo al runtime del sitio

### Requirement: Contrato de adjunto de la transcripción

Cada transcripción SHALL entregarse como un **adjunto de la biblioteca de medios** cuyo fichero sea un SubRip (`.srt`), y SHALL enlazarse al episodio escribiendo el **ID de ese adjunto** —un entero positivo— en la meta `transcript-srt`. El fichero SHALL quedar almacenado en el sistema de ficheros del sitio de modo que sea **legible** desde el contenedor de WordPress, para satisfacer el contrato que ya consume el feed.

#### Scenario: Tras adjuntar, el feed declara la transcripción

- **WHEN** un episodio publicado y público tiene `transcript-srt` apuntando a un adjunto `.srt` legible
- **THEN** el feed emite `<podcast:transcript>` con la URL del adjunto, `type="application/x-subrip"` y el idioma del programa

#### Scenario: El adjunto es legible en el contenedor

- **WHEN** se escribe la meta de un episodio
- **THEN** el adjunto referenciado existe y su fichero es legible desde el contenedor de WordPress

#### Scenario: Nunca se enlaza un ID inexistente

- **WHEN** la subida de un adjunto no llega a completarse
- **THEN** la meta `transcript-srt` del episodio no se modifica y sigue como estaba


#### Scenario: Solo se enlazan podcasts publicados

- **WHEN** el manifiesto de subida referencia un post que no es de tipo `podcast` o que no está publicado
- **THEN** la meta `transcript-srt` de ese post no se modifica y el proceso reporta el error de esa entrada

#### Scenario: Un episodio resuelto tiene su meta escrita y verificada

- **WHEN** el proceso da por subido un episodio
- **THEN** la meta `transcript-srt` de ese episodio fue escrita y se vuelve a leer con el ID del adjunto creado

### Requirement: Idempotencia y reanudación

El proceso SHALL ser **idempotente y reanudable**. Un episodio cuyo `transcript-srt` ya apunta a un adjunto accesible SHALL **saltarse** sin crear un adjunto nuevo ni descargar su audio. Una ejecución interrumpida SHALL poder reanudarse procesando únicamente los episodios pendientes, y repetir el proceso completo SHALL NOT duplicar adjuntos.

#### Scenario: Un episodio ya transcrito se salta

- **WHEN** el proceso encuentra un episodio con `transcript-srt` apuntando a un adjunto accesible
- **THEN** lo omite sin crear adjunto nuevo y sin descargar su audio

#### Scenario: Una ejecución interrumpida se reanuda

- **WHEN** el proceso se detiene y se vuelve a lanzar
- **THEN** procesa solo los episodios que siguen pendientes

#### Scenario: Repetir el proceso no duplica adjuntos

- **WHEN** el proceso se ejecuta dos veces sobre el mismo conjunto de episodios
- **THEN** el segundo intento no crea adjuntos adicionales

### Requirement: Omisión de episodios sin audio utilizable

Un episodio **sin `mp3-url`** o cuya `mp3-url` no sea una URL `http`/`https` utilizable SHALL **omitirse** y contabilizarse como omitido, sin descargar nada y sin abortar el lote.

#### Scenario: Episodio sin mp3-url se omite

- **WHEN** un episodio publicado tiene `mp3-url` vacía
- **THEN** se omite, se contabiliza como omitido y no se descarga nada por él

#### Scenario: Episodio con URL no utilizable se omite

- **WHEN** la `mp3-url` de un episodio no es una URL `http`/`https`
- **THEN** se omite y se contabiliza como omitido

### Requirement: Aislamiento de fallos y contabilidad del lote

Un fallo al descargar o al transcribir un episodio SHALL afectar **solo a ese episodio**: SHALL NOT escribir adjunto ni meta para él, SHALL dejar el lote continuar con los demás y SHALL NOT abortar el proceso. El episodio SHALL quedar reintentable en una ejecución posterior. Al terminar, el proceso SHALL reportar un **resumen** con el número de resueltos, omitidos y fallidos.

#### Scenario: Un fallo de descarga no contamina el episodio

- **WHEN** la descarga del audio de un episodio falla
- **THEN** ese episodio queda sin adjunto y sin meta, se contabiliza como fallido y el lote continúa

#### Scenario: Un fallo de transcripción no contamina el episodio

- **WHEN** la transcripción de un episodio falla
- **THEN** ese episodio queda sin adjunto y sin meta, se contabiliza como fallido y el lote continúa

#### Scenario: El lote termina con un resumen

- **WHEN** el proceso termina
- **THEN** informa del número de episodios resueltos, omitidos y fallidos

#### Scenario: Un SRT ilegible o no codificado en UTF-8 no aborta el lote

- **WHEN** el fichero SRT de un episodio no puede leerse o no está codificado en UTF-8
- **THEN** ese episodio se contabiliza como fallido, no se enlaza y el lote continúa con los demás

#### Scenario: Una URL rechazada por el cliente HTTP no aborta el lote

- **WHEN** la `mp3-url` de un episodio contiene caracteres que el cliente HTTP rechaza antes de conectar
- **THEN** ese episodio se contabiliza como fallido, no se enlaza y el lote continúa con los demás

#### Scenario: Un registro sin identificador utilizable no aborta el lote

- **WHEN** un registro recolectado no trae un identificador numérico utilizable
- **THEN** se contabiliza como fallido y el lote continúa con los demás

### Requirement: Validez del SubRip producido

El fichero `.srt` producido SHALL ser **SubRip válido**: bloques separados por una línea en blanco, numerados de forma consecutiva empezando en 1, cada uno con una línea de marcas de tiempo con el formato `HH:MM:SS,mmm --> HH:MM:SS,mmm`, con los minutos y los segundos dentro del rango `00`-`59`, en orden creciente, y al menos una línea de texto no vacía. El fichero SHALL contener **al menos un bloque** y SHALL estar codificado en **UTF-8** (admitiendo la marca de orden de bytes inicial).

#### Scenario: El SRT tiene bloques numerados consecutivos

- **WHEN** se inspecciona un SRT producido con N bloques
- **THEN** los bloques están numerados del 1 al N sin saltos

#### Scenario: Las marcas de tiempo son válidas y crecientes

- **WHEN** se inspecciona la línea de tiempos de cada bloque
- **THEN** sigue el formato `HH:MM:SS,mmm --> HH:MM:SS,mmm` y cada bloque empieza después de que termine el anterior

#### Scenario: El SRT conserva los caracteres del idioma

- **WHEN** el audio contiene caracteres como vocales acentuadas o eñes
- **THEN** el SRT los conserva correctamente por estar en UTF-8

#### Scenario: Un SRT sin bloques no se enlaza

- **WHEN** el fichero SRT producido no contiene ningún bloque de subtítulo
- **THEN** no se enlaza al episodio y ese episodio se contabiliza como fallido

#### Scenario: Un SRT con marca de orden de bytes se valida

- **WHEN** el fichero SRT empieza con una marca de orden de bytes UTF-8
- **THEN** se valida correctamente y el primer bloque conserva su numeración

#### Scenario: Marcas de tiempo fuera de rango son inválidas

- **WHEN** una marca de tiempo de un bloque tiene minutos o segundos mayores que `59`
- **THEN** el SRT se considera inválido

### Requirement: Ejecución acotada y alcance

El proceso SHALL permitir acotar su alcance de forma explícita y comprobable. SHALL aceptar un límite del número de episodios a procesar en una ejecución y un **modo de ensayo** que SHALL NOT escribir adjunto ni meta alguno. SHALL considerar **exclusivamente** episodios de tipo `podcast` en estado **publicado**.

#### Scenario: El modo de ensayo no escribe nada

- **WHEN** el proceso se ejecuta en modo de ensayo
- **THEN** no crea adjuntos ni modifica metas

#### Scenario: El límite acota el número de episodios

- **WHEN** el proceso se ejecuta con un límite N
- **THEN** procesa como máximo N episodios

#### Scenario: El alcance se restringe a podcasts publicados

- **WHEN** existen entradas de otros tipos o borradores
- **THEN** el proceso no las considera
