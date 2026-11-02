# podcast-transcripts Delta

## Purpose

Añadir al pipeline batch un **preprocesado de audio** antes del reconocimiento: una copia normalizada
(16 kHz, mono, PCM) y limitada en banda (paso alto 80 Hz, paso bajo 8 kHz), que es la entrada nativa
de Whisper. Se mide el "sin voz" **sobre el audio original**, antes de preprocesar.

## ADDED Requirements

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
