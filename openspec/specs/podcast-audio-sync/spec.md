# podcast-audio-sync Specification

## Purpose
El autorrelleno y la reparación de la meta `mp3-url` de cada episodio del podcast desde el **RSS
público del origen** (Spotify for Podcasters / Anchor). Fija de dónde sale el enlace de audio, con qué
clave se empareja un episodio con su item del origen (identidad del fichero primero, número deducido del
título como respaldo), qué se considera ausente y se repara, la garantía de que **nunca se sobrescribe un
audio válido**, el comportamiento del comando masivo y del gancho de publicación, y el aislamiento de
fallos.

Queda **explícitamente fuera** del contrato la URL pública de Spotify y su enlace en la ficha del
episodio: exigen la Web API oficial y una app de desarrollador con cuenta Premium, y el titular decidió
no abordarlas en este change (el conocimiento medido se conserva en `proposal.md`).

## Requirements

### Requirement: El audio se resuelve desde el RSS público del origen

El sistema SHALL obtener el enlace de audio de cada episodio desde el **RSS público del origen** del
programa, cuya URL SHALL ser configurable y SHALL tener un valor por omisión. El sistema SHALL NOT
inventar, completar ni deducir un enlace de audio a partir de otra fuente. Una ejecución SHALL hacer como
máximo una descarga del origen: el índice parseado SHALL reutilizarse para todos los episodios, y el
cuerpo descargado SHALL tener un límite superior de tamaño y un tiempo máximo de espera.

#### Scenario: El audio procede del origen

- **WHEN** el sistema resuelve el enlace de audio de un episodio
- **THEN** el valor escrito es exactamente el `enclosure` del item del origen que le corresponde

#### Scenario: Una ejecución descarga el origen una sola vez

- **WHEN** el proceso revisa N episodios en la misma ejecución
- **THEN** se realiza una única descarga del RSS del origen y el índice se reutiliza para los N

#### Scenario: Un origen inalcanzable no escribe nada

- **WHEN** la descarga del RSS del origen falla, agota el tiempo de espera o supera el límite de tamaño
- **THEN** no se escribe ninguna meta en ningún episodio y el proceso informa del fallo

### Requirement: Emparejamiento por identidad de audio

El sistema SHALL emparejar cada episodio con su item del origen usando como **clave primaria la identidad
del fichero de audio**: el nombre del `.mp3` que aparece en el `enclosure`, normalizado (URL decodificada)
para que un enlace envuelto por el origen y el enlace directo al mismo fichero se consideren **la misma
identidad**. Un episodio cuya identidad de audio no exista en el origen SHALL NOT modificarse y SHALL
reportarse como divergente.

#### Scenario: La misma identidad con dos formas de URL se considera la misma

- **WHEN** el `mp3-url` de un episodio es la URL directa al fichero y el origen lo publica envuelto por su redirección de reproducción
- **THEN** el episodio se considera emparejado y su `mp3-url` no se modifica

#### Scenario: Una identidad distinta es una divergencia, no un error

- **WHEN** el `mp3-url` de un episodio es una URL válida cuyo fichero no es el que publica el origen para ese episodio
- **THEN** la meta no se modifica y el episodio se reporta como divergente con los dos valores

#### Scenario: La identidad gana al número

- **WHEN** un episodio cuya identidad de audio existe en el origen lleva un número distinto del que el origen le asigna
- **THEN** se empareja por identidad y no se escribe nada

### Requirement: El número es la clave de respaldo, con ambigüedad explícita

Cuando un episodio no tenga identidad de audio (su `mp3-url` está vacío o no es una URL), el sistema SHALL
usar como clave de respaldo el **número del episodio**, deducido del título del item del origen con
tolerancia a los formatos realmente publicados, y SHALL aceptar esa clave solo si es **inequívoca**. Si
varios items del origen comparten el número, o si ningún item lo tiene, el sistema SHALL NOT escribir nada
y SHALL reportar el episodio como ambiguo o sin par, respectivamente.

#### Scenario: Los formatos de título del origen se normalizan al mismo número

- **WHEN** el título del item es `ATA 839 ...`, `ATA839 ...`, `ATA T01E50 - ...`, `705 - ...` o `T01E36 ...`
- **THEN** el número deducido es 839, 839, 50, 705 y 36 respectivamente

#### Scenario: Un número con dos candidatos no se resuelve

- **WHEN** dos items del origen comparten el mismo número y el episodio no tiene identidad de audio
- **THEN** no se escribe ninguna meta de ese episodio y se reporta como ambiguo

#### Scenario: Un número sin item no se resuelve

- **WHEN** el número del episodio no corresponde a ningún item del origen
- **THEN** no se escribe ninguna meta de ese episodio y se reporta como sin par

### Requirement: Se repara el audio ausente o inválido

El sistema SHALL tratar como **ausente** un `mp3-url` vacío **o** cuyo valor no sea una URL absoluta
`http(s)`, y SHALL rellenarlo con el `enclosure` del item emparejado. El valor escrito SHALL ser una URL
absoluta `http(s)`.

#### Scenario: Un episodio sin enlace de audio recibe el del origen

- **WHEN** un episodio publicado tiene el `mp3-url` vacío y su número casa con un único item del origen
- **THEN** el `mp3-url` queda escrito con el `enclosure` de ese item y se vuelve a leer con ese valor

#### Scenario: Un valor que no es una URL se repara

- **WHEN** el `mp3-url` de un episodio contiene un valor que no es una URL absoluta `http(s)`
- **THEN** se sustituye por el `enclosure` del item emparejado y el episodio se reporta como reparado

#### Scenario: Tras reparar, el feed publica un enlace de audio válido

- **WHEN** un episodio reparado se compone en el feed del podcast
- **THEN** su `<enclosure>` apunta a una URL absoluta `http(s)` del origen

### Requirement: Nunca se sobrescribe un audio válido

Un episodio cuyo `mp3-url` **ya sea una URL absoluta `http(s)`** SHALL NOT modificarse, con independencia
de lo que publique el origen, y el sistema SHALL NOT ofrecer ninguna opción que lo sobrescriba. El
`enclosure` que el feed publica para esos episodios SHALL permanecer idéntico antes y después de una
ejecución.

#### Scenario: Una ejecución no cambia el audio ya correcto

- **WHEN** se ejecuta la sincronización completa sobre los episodios que ya tienen un `mp3-url` válido
- **THEN** el `<enclosure>` que el feed publica para ellos es idéntico al de antes de la ejecución

#### Scenario: Una divergencia de identidad solo se informa

- **WHEN** un episodio tiene un `mp3-url` válido cuyo fichero no es el que publica el origen para ese episodio
- **THEN** su `mp3-url` sigue siendo exactamente el mismo valor de antes y el episodio se reporta como divergente

#### Scenario: Un emparejamiento fallido no borra el valor existente

- **WHEN** un episodio tiene un `mp3-url` válido y su emparejamiento resulta ambiguo o sin par
- **THEN** su `mp3-url` sigue siendo el mismo valor de antes de la ejecución

### Requirement: Sincronización al publicar

Al **publicarse** un episodio, el sistema SHALL intentar resolver su `mp3-url` dentro de la misma petición,
y SHALL hacerlo sin bloquear ni romper la publicación: un fallo del origen o de la red SHALL NOT impedir
que el episodio quede publicado ni SHALL propagar un error fatal. El intento SHALL ser idempotente:
guardar de nuevo un episodio ya resuelto SHALL NOT repetir la descarga del origen ni alterar sus metas. El
proceso SHALL afectar solo al episodio publicado y SHALL NOT tocar borradores ni otros tipos de contenido.

#### Scenario: Un episodio nuevo queda resuelto al publicarse

- **WHEN** se publica un episodio cuyo número casa con un único item del origen
- **THEN** su `mp3-url` queda escrito con el `enclosure` de ese item y se vuelve a leer con ese valor

#### Scenario: Un origen que aún no tiene el episodio no rompe la publicación

- **WHEN** se publica un episodio que el RSS del origen todavía no lista
- **THEN** el episodio queda publicado con normalidad, no se escribe ninguna meta y el episodio se reporta como sin par

#### Scenario: Un fallo de red no rompe el guardado

- **WHEN** el origen no responde durante la publicación de un episodio
- **THEN** el episodio queda publicado, sus metas quedan como estaban y no se registra ningún error fatal

#### Scenario: Volver a guardar no repite el trabajo

- **WHEN** se guarda de nuevo un episodio ya sincronizado
- **THEN** no se realiza una nueva descarga del origen y sus metas no cambian

#### Scenario: Los borradores y otros tipos no se tocan

- **WHEN** se guarda un borrador de episodio o cualquier entrada que no sea de tipo `podcast`
- **THEN** ninguna meta de ese contenido se modifica

### Requirement: Comando de sincronización masiva

El sistema SHALL ofrecer un comando de WP-CLI que sincronice el histórico completo en una ejecución, y
SHALL admitir: **ensayo sin escritura**, **límite de episodios** y **solo los episodios con el audio
ausente o inválido**. El comando SHALL informar al terminar de un recuento por clase (reparados, ya
correctos, divergentes, ambiguos, sin par y fallidos) y SHALL ser **idempotente**: una segunda ejecución
sobre los mismos datos SHALL NOT escribir nada.

#### Scenario: El ensayo no escribe nada

- **WHEN** el comando se ejecuta en modo ensayo
- **THEN** ninguna meta de ningún episodio se modifica, aunque el resumen describa lo que se habría escrito

#### Scenario: La segunda ejecución no escribe nada

- **WHEN** el comando se ejecuta por segunda vez sobre los mismos datos y con las mismas opciones
- **THEN** no se escribe ninguna meta y el resumen informa de los episodios ya correctos

#### Scenario: El límite acota la ejecución

- **WHEN** el comando se ejecuta con un límite de N episodios
- **THEN** se revisan como máximo N episodios y el resumen indica cuántos quedan pendientes

#### Scenario: Solo los episodios con el audio ausente o inválido

- **WHEN** el comando se ejecuta con la opción de revisar únicamente los episodios con el audio ausente o inválido
- **THEN** no se modifica el `mp3-url` de ningún episodio que ya tuviera una URL válida

#### Scenario: El comando informa de cada fallo sin abortar

- **WHEN** un episodio concreto no puede resolverse
- **THEN** el comando continúa con el resto, cuenta ese episodio y termina informando del total

### Requirement: Seguridad de los datos

El sistema SHALL escribir únicamente URLs absolutas `http(s)` en las metas, pasando todo valor por el
saneado de WordPress antes de guardarlo. Un valor que no se pueda sanear como URL absoluta `http(s)`
SHALL NOT escribirse.

#### Scenario: Solo se guardan URLs absolutas http(s)

- **WHEN** el sistema va a escribir la meta del enlace de audio
- **THEN** escribe una URL absoluta `http(s)` saneada, o no escribe nada

#### Scenario: Un valor no saneable no se escribe

- **WHEN** el `enclosure` del origen no es una URL absoluta `http(s)` (por ejemplo un esquema no permitido)
- **THEN** la meta no se modifica y el episodio se cuenta como fallido
