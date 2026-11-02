# Podcast Transcripts Delta

## MODIFIED Requirements

### Requirement: Aislamiento de fallos y contabilidad del lote

Un fallo al descargar o al transcribir un episodio SHALL afectar **solo a ese episodio**: SHALL NOT escribir adjunto ni meta para él, SHALL dejar el lote continuar con los demás y SHALL NOT abortar el proceso. El episodio SHALL quedar reintentable en una ejecución posterior. Al terminar, el proceso SHALL reportar un **resumen** con el número de resueltos, omitidos y fallidos. Al informar de un episodio fallido, el proceso SHALL acompañar el motivo, que SHALL proceder de la salida real del comando que falló y SHALL incluir su código de salida o la señal que lo terminó: un fallo SHALL NOT reportarse con un motivo vacío cuando el comando produjo salida.

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

## ADDED Requirements

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
