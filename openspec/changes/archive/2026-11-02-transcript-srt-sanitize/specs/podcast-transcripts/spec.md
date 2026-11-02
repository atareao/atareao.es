# podcast-transcripts Delta

## Purpose

Endurecer el pipeline batch de transcripciones para que un *cue* con texto vacío emitido por
Whisper no descarte un episodio entero, y para distinguir un audio **sin voz** de un fallo real
de transcripción.

## MODIFIED Requirements

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

## ADDED Requirements

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
