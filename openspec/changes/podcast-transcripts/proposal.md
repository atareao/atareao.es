# Proposal: Pipeline de transcripciones del podcast

## Why

**Ninguno de los 838 episodios publicados tiene transcripción.** La medición en producción, hecha antes de redactar este cambio, es tajante:

```
publicados                      : 838
con la meta transcript-srt      : 0
adjuntos .srt / .vtt            : 0
<item> en /podcast/feed/        : 100
podcast:transcript en el feed   : 0
```

**No es un fallo del sistema, es un hueco de contenido.** El contrato de la transcripción está implementado y probado: la meta `transcript-srt` guarda el **ID de un adjunto** de la biblioteca, y el feed emite `<podcast:transcript>` cuando ese adjunto existe y es legible (`isAccessibleTranscript`). El propio código anticipa este cambio —*«a manually attached SRT and a **pipeline-attached** one are indistinguishable downstream»* (la fuente única de verdad del contrato; el metabox y el feed la comparten)—. Lo que falta es el proceso que **produzca** los SRT y los enlace.

Consecuencia: ningún reproductor de podcast ofrece transcripción de ningún capítulo, y el requisito del feed que declara el `<podcast:transcript>` **nunca se ha ejercido en producción** (por eso quedó documentado como «no verificable E2E»: no había ni un solo caso contra el que probarlo).

El audio es remoto y pesa: **13,7 GB** en 835 episodios (media 16,8 MB). Se comprobó que `anchor.fm` redirige a CloudFront, que la CDN ignora el `User-Agent`, y que la máquina de desarrollo **puede** transcribir: RTX 4060 Ti (16 GB), 16 hilos, 62 GB de RAM, `whisper` (openai) y `ffmpeg` disponibles.

## What Changes

- **Un proceso batch externo** (no el runtime del sitio) que, para cada episodio pendiente: descarga su MP3 remoto, lo transcribe a SubRip con Whisper y **adjunta el resultado al episodio** como un adjunto de la biblioteca de medios, escribiendo su ID en la meta `transcript-srt`.
- **Idempotencia y reanudación:** la fuente de verdad es el propio WordPress —un episodio cuyo `transcript-srt` apunta a un adjunto accesible se **salta**—, de modo que una ejecución interrumpida se reanuda sin duplicar adjuntos y repetir el proceso es inocuo.
- **Aislamiento de fallos y contabilidad:** un episodio sin `mp3-url` (hoy hay 3) o con URL insegura se **omite**; un fallo de descarga o de transcripción deja el episodio **intacto** (sin adjunto y sin meta) y reintentable, y no aborta el lote. Al final se reporta el resumen resueltos / omitidos / fallidos.
- **Ejecución acotada y verificable:** `--limit N` para pruebas por lotes y `--dry-run` para ensayar sin escribir nada; solo se consideran episodios **publicados** de tipo `podcast`.
- **Huella controlada:** un episodio ya transcrito no se vuelve a descargar, y el audio temporal se elimina tras procesarlo.

Queda **explícitamente fuera**: el sitio **no** transcribe en tiempo de ejecución (no descarga audio ni ejecuta reconocimiento de voz). Cualquier diseño que meta Whisper en el contenedor de WordPress es incorrecto: no hay herramientas ni GPU, y el sitio no debe hacer trabajo pesado.

## Capabilities

### Added Capabilities

- `podcast-transcripts`: el proceso que **produce** las transcripciones y las enlaza con el contrato que el feed ya consume. Se especifica como capacidad propia para que su contrato (idempotencia, aislamiento de fallos, validez del SRT, ejecución acotada) sea verificable con independencia del vocabulario del feed.

## Impact

- **Ficheros a crear (solo tras aprobación):**
  - `scripts/podcast-transcripts/transcribe.py` — el pipeline, **solo biblioteca estándar** de Python 3 (sin dependencias que instalar; el proyecto no tiene gestor de paquetes). Orquesta: recolección de pendientes vía WP-CLI, descarga, `whisper`, validación del SRT y subida.
  - `tests/podcast-transcripts/test_transcribe.py` — pruebas unitarias de las piezas puras (escritura/validación de SRT, decisión de idempotencia, guarda de URL, selección de episodios), sin red ni GPU.
- **Ficheros a modificar:**
  - `.justfile` — receta `test-transcripts` que ejecuta las pruebas unitarias.
- **Sin cambios en el runtime del sitio.** El plugin no se toca: el feed ya emite `<podcast:transcript>` cuando hay adjunto accesible.
- **Dependencia de entorno (documentada, no versionada):** `whisper` + GPU en la máquina que ejecuta el lote, y acceso a producción vía WP-CLI. El modelo por defecto será `turbo` (mejor español que `medium`, que es el único cacheado hoy), sobreescribible.

### Limitaciones conocidas (documentadas, no corregidas aquí)

- **El `Content-Type` servido no coincide con el declarado.** El feed declara `type="application/x-subrip"`, pero nginx no tiene mapeo para `.srt` en `mime.types` (`default_type application/octet-stream`), así que el fichero se servirá como `application/octet-stream`. Muchos clientes lo toleran; corregirlo es configuración de nginx (código) y merece su propio cambio si algún directorio lo rechaza.
- **Coste del lote completo:** descargar 13,7 GB y transcribir 835 episodios es un trabajo de horas, no de minutos. Por eso `--limit` y la reanudación son parte del contrato, no un extra.
