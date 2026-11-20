# podcast-transcripts Delta

## Purpose

Permitir que el pipeline batch de transcripciones procese **episodios en borrador**, de forma
**explícita y opt-in**, sin alterar el contrato por defecto (que sigue siendo «solo publicados»).
Motivación: preparar la transcripción de los borradores del podcast antes de publicarlos.

## MODIFIED Requirements

### Requirement: Contrato de adjunto de la transcripción

Cada transcripción SHALL entregarse como un **adjunto de la biblioteca de medios** cuyo fichero sea un SubRip (`.srt`), y SHALL enlazarse al episodio escribiendo el **ID de ese adjunto** —un entero positivo— en la meta `transcript-srt`. El fichero SHALL quedar almacenado en el sistema de ficheros del sitio de modo que sea **legible** desde el contenedor de WordPress, para satisfacer el contrato que ya consume el feed. El proceso SHALL NOT escribir la meta de un post cuyo tipo no sea `podcast`. **Por defecto** SHALL NOT escribir la meta de un episodio que no esté **publicado**; SHALL aceptar episodios en estado **borrador** únicamente cuando la ejecución solicite explícitamente incluir borradores.

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

- **WHEN** el manifiesto de subida referencia un post que no es de tipo `podcast`, o un `podcast` que no está publicado y la ejecución no incluye borradores
- **THEN** la meta `transcript-srt` de ese post no se modifica y el proceso reporta el error de esa entrada

#### Scenario: Un episodio resuelto tiene su meta escrita y verificada

- **WHEN** el proceso da por subido un episodio
- **THEN** la meta `transcript-srt` de ese episodio fue escrita y se vuelve a leer con el ID del adjunto creado

#### Scenario: Con la inclusión de borradores el borrador sí se enlaza

- **WHEN** el proceso se ejecuta incluyendo borradores y el destino es un `podcast` en estado borrador
- **THEN** se crea el adjunto, se escribe su ID en la meta `transcript-srt` del borrador y se vuelve a leer para verificar

### Requirement: Ejecución acotada y alcance

El proceso SHALL permitir acotar su alcance de forma explícita y comprobable. SHALL aceptar un límite del número de episodios a procesar en una ejecución y un **modo de ensayo** que SHALL NOT escribir adjunto ni meta alguno. SHALL considerar **exclusivamente** episodios de tipo `podcast`. **Por defecto** SHALL considerar solo los `podcast` en estado **publicado**; SHALL considerar además los `podcast` en estado **borrador** únicamente cuando la ejecución solicite explícitamente incluir borradores.

#### Scenario: El modo de ensayo no escribe nada

- **WHEN** el proceso se ejecuta en modo de ensayo
- **THEN** no crea adjuntos ni modifica metas

#### Scenario: El límite acota el número de episodios

- **WHEN** el proceso se ejecuta con un límite N
- **THEN** procesa como máximo N episodios

#### Scenario: El alcance se restringe a podcasts publicados

- **WHEN** existen entradas de otros tipos o borradores y el proceso no incluye borradores
- **THEN** el proceso no las considera

#### Scenario: La inclusión de borradores amplía el alcance a los borradores

- **WHEN** el proceso se ejecuta incluyendo borradores
- **THEN** considera también los `podcast` en estado borrador, y no las entradas de otros tipos

## ADDED Requirements

### Requirement: Transcripción de episodios en borrador

El proceso SHALL poder iniciarse de forma **explícita** para transcribir episodios en estado **borrador**. En ese modo, cada borrador SHALL seguir exactamente el mismo flujo que un publicado (descarga, detección de audio sin voz, reconocimiento, saneo, validez, adjunto y meta) y SHALL cumplir las mismas garantías de **idempotencia**, **aislamiento de fallos** y detección de **audio sin voz**. El proceso SHALL NOT cambiar el estado editorial del episodio y SHALL NOT hacerlo visible en el feed.

#### Scenario: Un borrador con audio útil se transcribe y enlaza

- **WHEN** el proceso se ejecuta incluyendo borradores sobre un `podcast` en estado borrador con audio útil
- **THEN** se le crea el adjunto `.srt` y se escribe su ID en `transcript-srt`, y el episodio sigue en estado borrador

#### Scenario: Transcribir un borrador no altera el estado ni el feed

- **WHEN** se transcribe un borrador
- **THEN** su estado editorial sigue siendo borrador y el feed no referencia su transcripción

#### Scenario: Un borrador ya transcrito no se repite

- **WHEN** el proceso se ejecuta de nuevo sobre un borrador cuyo `transcript-srt` ya apunta a un adjunto accesible
- **THEN** se omite sin crear adjunto nuevo ni descargar su audio
