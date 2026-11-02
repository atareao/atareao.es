# podcast-transcripts Specification

## Purpose
El proceso batch que **produce** las transcripciones del podcast y las enlaza con el contrato que el feed ya consume: descarga el audio remoto de cada episodio pendiente, lo transcribe a SubRip, lo adjunta a la biblioteca de medios y escribe el ID del adjunto en la meta `transcript-srt`. Fija la idempotencia y la reanudación, el aislamiento de fallos con contabilidad, la validez del SRT producido y los límites de ejecución (solo episodios publicados, `--limit`, `--dry-run`), dejando fuera del runtime del sitio cualquier descarga o reconocimiento de voz.

## Requirements

### Requirement: Producción fuera del runtime del sitio

Las transcripciones SHALL generarse por un **proceso batch externo**, nunca en tiempo de ejecución del sitio. El sitio SHALL NOT descargar el audio de un episodio ni ejecutar reconocimiento de voz en ninguna petición, composición del feed o tarea programada.

#### Scenario: El runtime del sitio no transcribe

- **WHEN** se sirve el feed o cualquier página del sitio
- **THEN** no se descarga audio ni se ejecuta reconocimiento de voz en el proceso de la petición

#### Scenario: La transcripción procede de un proceso batch

- **WHEN** un episodio pasa a tener transcripción
- **THEN** el adjunto y la meta los escribió un proceso batch externo al runtime del sitio

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

Un fallo al descargar o al transcribir un episodio SHALL afectar **solo a ese episodio**: SHALL NOT escribir adjunto ni meta para él, SHALL dejar el lote continuar con los demás y SHALL NOT abortar el proceso. El episodio SHALL quedar reintentable en una ejecución posterior. Al terminar, el proceso SHALL reportar un **resumen** con el número de resueltos, omitidos y fallidos. Al informar de un episodio fallido, el proceso SHALL acompañar el motivo, que SHALL proceder de la salida real del comando que falló y SHALL incluir su código de salida o la señal que lo terminó: un fallo SHALL NOT reportarse con un motivo vacío cuando el comando produjo salida. Un episodio detectado como **audio sin voz** SHALL contabilizarse como **omitido con su motivo**, no como fallido, y SHALL aparecer como tal en el resumen.

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

#### Scenario: El resumen desglosa los episodios sin voz

- **WHEN** el lote detecta uno o más episodios sin voz
- **THEN** el resumen informa de esos episodios como omitidos con el motivo `sin voz`, y no como fallidos

### Requirement: Validez del SubRip producido

El fichero `.srt` producido SHALL ser **SubRip válido**: bloques separados por una línea en blanco, numerados de forma consecutiva empezando en 1, cada uno con una línea de marcas de tiempo con el formato `HH:MM:SS,mmm --> HH:MM:SS,mmm`, con los minutos y los segundos dentro del rango `00`-`59`, en orden creciente, y al menos una línea de texto no vacía. El fichero SHALL contener **al menos un bloque** y SHALL estar codificado en **UTF-8** (admitiendo la marca de orden de bytes inicial).

Antes de validar, el proceso SHALL **sanear** la salida del reconocedor: todo bloque cuya línea de texto esté **vacía o compuesta solo por espacios** SHALL descartarse, y los bloques restantes SHALL renumerarse de forma consecutiva desde 1. El saneo SHALL conservar **sin cambios** el texto y las marcas de tiempo de los bloques que sí tienen texto.

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

#### Scenario: Los cues vacíos del reconocedor se descartan

- **WHEN** el reconocedor produce un SRT con uno o más bloques cuya línea de texto está vacía o solo con espacios
- **THEN** esos bloques se descartan, los restantes se renumeran consecutivos desde 1, y el episodio NO se considera fallido por ese motivo

#### Scenario: El saneo no altera los bloques con texto

- **WHEN** un SRT contiene bloques con texto junto a otros vacíos
- **THEN** el texto y las marcas de tiempo de los bloques con texto se conservan idénticos

#### Scenario: Un SRT cuyos bloques son todos vacíos no se enlaza

- **WHEN** tras el saneo no queda ningún bloque con texto
- **THEN** no se enlaza al episodio y se trata como audio sin voz

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

### Requirement: El diagnóstico de un fallo de transcripción es utilizable

El sistema SHALL informar del motivo de un fallo de transcripción con datos reales del comando que falló: SHALL recoger la salida de error y, cuando esa salida esté vacía, la salida estándar; SHALL incluir el código de salida del comando; y cuando el proceso haya terminado por una señal, SHALL informar de la señal en lugar del código. El motivo SHALL conservar las últimas líneas de esa salida, para que una traza se pueda leer entera, y SHALL distinguir el fallo del comando del caso en que el comando termina sin error y el fichero `.srt` esperado no aparece.

#### Scenario: Con la salida de error vacía, el detalle sale de la salida estándar

- **WHEN** el comando de transcripción falla escribiendo toda su salida por la salida estándar y nada por la de error
- **THEN** el motivo del fallo incluye las últimas líneas de la salida estándar y no una etiqueta sin contenido

#### Scenario: El código de salida se informa siempre

- **WHEN** el comando de transcripción termina con un código distinto de cero
- **THEN** el motivo del fallo incluye ese código de salida

#### Scenario: Una muerte por señal se informa como señal

- **WHEN** el comando de transcripción es terminado por una señal
- **THEN** el motivo del fallo indica la señal y no un código de salida positivo

#### Scenario: El comando termina bien pero no deja el SRT

- **WHEN** el comando de transcripción termina sin error y el fichero `.srt` esperado no existe
- **THEN** el motivo del fallo lo dice explícitamente y no se confunde con un fallo del comando

#### Scenario: El motivo conserva varias líneas

- **WHEN** el comando de transcripción falla dejando una traza de varias líneas
- **THEN** el motivo conserva sus últimas líneas y no solo la última

#### Scenario: Un fallo sin salida no inventa un detalle

- **WHEN** el comando de transcripción falla sin producir ninguna salida y sin morir por una señal
- **THEN** el motivo informa del código de salida observado y no de un detalle inventado

### Requirement: Audio sin voz detectable

El proceso SHALL determinar si el audio de un episodio tiene **voz transcribible** *antes* de
transcribirlo, **midiendo el propio audio**: SHALL calcular la **fracción audible** del fichero con el
detector de silencios (`ffmpeg silencedetect`, umbral de ruido y duración mínima de silencio fijos)
sobre su duración total. Si esa fracción audible es **inferior al 5 %**, el proceso SHALL tratar el
episodio como **sin voz**, contabilizarlo como **omitido con el motivo `sin voz`**, y SHALL NOT
transcribirlo ni escribir adjunto ni meta para él. El proceso SHALL NOT abortar el lote por este
motivo. Como segunda condición, si tras el saneo del SRT no queda ningún bloque con texto, el proceso
SHALL aplicar el mismo tratamiento de **sin voz**.

#### Scenario: Un audio prácticamente mudo se detecta antes de transcribir

- **WHEN** la fracción audible del audio de un episodio es inferior al 5 %
- **THEN** el episodio se contabiliza como omitido con el motivo `sin voz`, sin lanzar la transcripción y sin escribir adjunto ni meta

#### Scenario: Un audio con voz no se marca como sin voz

- **WHEN** la fracción audible del audio es alta (hay voz a lo largo del episodio)
- **THEN** el episodio se procesa con normalidad y NO se contabiliza como `sin voz`

#### Scenario: Un audio sin voz no se enlaza ni escribe meta

- **WHEN** un episodio se detecta sin voz
- **THEN** no se crea adjunto ni se modifica la meta `transcript-srt` de ese episodio

#### Scenario: Sin voz no se contabiliza como fallo

- **WHEN** un episodio se detecta sin voz
- **THEN** el resumen lo informa como omitido con el motivo `sin voz` y NO como fallido, y el lote continúa

#### Scenario: Sin voz no se confunde con un fallo del comando

- **WHEN** el comando de transcripción termina bien pero no hay voz transcribible
- **THEN** el motivo informado es `sin voz` y no un fallo del comando de transcripción

### Requirement: Preprocesado de audio para el reconocimiento

Antes de invocar al reconocedor, el proceso SHALL producir una **copia normalizada** del audio con
`ffmpeg`: **16 kHz**, **mono**, **PCM con signo de 16 bits** (`pcm_s16le`), y **limitada en banda** con
un **paso alto a 80 Hz** y un **paso bajo a 8000 Hz**. El reconocedor SHALL recibir esa copia, no el
fichero descargado. El proceso SHALL eliminar la copia temporal al terminar el episodio. El
preprocesado SHALL NOT alterar el fichero descargado.

#### Scenario: El reconocedor recibe el audio normalizado

- **WHEN** el proceso va a transcribir un episodio con voz
- **THEN** invoca al reconocedor sobre una copia de 16 kHz, mono y `pcm_s16le`, no sobre el fichero descargado

#### Scenario: La copia está limitada en banda

- **WHEN** se produce la copia normalizada
- **THEN** se aplica un paso alto a 80 Hz y un paso bajo a 8000 Hz antes de entregarla al reconocedor

#### Scenario: La copia temporal se elimina al terminar

- **WHEN** el episodio termina (resuelto o fallido)
- **THEN** la copia normalizada temporal ya no está en el disco

### Requirement: La detección de audio sin voz usa el original

La determinación de **audio sin voz** SHALL medirse sobre el **fichero de audio original descargado**,
**antes** de cualquier preprocesado, de modo que la normalización (que puede elevar el nivel del ruido
de fondo) no altere el veredicto.

#### Scenario: La medición se hace sobre el original

- **WHEN** se decide si un episodio es "sin voz"
- **THEN** la fracción audible se calcula sobre el fichero descargado, no sobre la copia preprocesada

#### Scenario: Un fallo del preprocesado no contamina el episodio

- **WHEN** el preprocesado de audio de un episodio falla
- **THEN** ese episodio queda sin adjunto y sin meta, se contabiliza como fallido y el lote continúa

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
