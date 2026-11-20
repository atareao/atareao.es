# Change: Transcribir episodios en borrador (opt-in)

## Why

Hay **5 borradores** de podcast (episodios n.º 630, 631, 652, 729 y 730) con `mp3-url` válida y
audio descargable, pero **sin transcripción**. Se quieren preparar (transcripción, y a partir de
ella el artículo y la metadescripción) antes de publicarlos.

El pipeline actual **no puede** procesarlos: por contrato explícito, recolecta **solo** episodios
`post_status = publish` (`wp-collect.php`) y el helper de subida **rechaza** cualquier destino que
no sea un `podcast` publicado (`wp-attach.php`). Esa restricción es deliberada y protege la
producción; el cambio la mantiene **por defecto** y la abre **solo de forma explícita**.

## What Changes

1. **Opción explícita para incluir borradores** en el pipeline (`--include-drafts`), apagada por
   defecto: sin ella, el comportamiento es **idéntico** al actual (solo publicados).
2. **Recolección**: `wp-collect.php` acepta un argumento que amplía el estado a `publish` + `draft`.
3. **Subida**: `wp-attach.php` acepta el conjunto de estados permitidos como argumento (por defecto
   `publish`); con la opción, también `draft`. Se conserva la verificación de adjunto legible y de
   meta releída.
4. Las garantías existentes (**idempotencia**, **aislamiento de fallos**, **audio sin voz**,
   saneo y validez del SRT, reanudación) aplican igual a los borradores.
5. El proceso **no cambia el estado editorial** del episodio ni lo hace visible en el feed.

## Impact

- **Código:** `scripts/podcast-transcripts/transcribe.py` (argumento + recolección + subida),
  `scripts/podcast-transcripts/wp-collect.php`, `scripts/podcast-transcripts/wp-attach.php` y
  `tests/podcast-transcripts/test_transcribe.py`.
- **Spec:** `openspec/specs/podcast-transcripts/spec.md`:
  - MODIFIED *Ejecución acotada y alcance* (alcance por defecto publicados; opt-in borradores).
  - MODIFIED *Contrato de adjunto de la transcripción* (destino permitido según la ejecución).
  - ADDED *Transcripción de episodios en borrador*.
- **No cambia:** el contrato del sitio ni el feed (los borradores no entran en el feed), ni el
  comportamiento por defecto del pipeline.
- **Fuera de alcance:** **publicar** los borradores; y la fase de **contenido** (artículo +
  metadescripción + datos), que no es código y se trata aparte una vez existan las transcripciones.

## Contexto de la fase siguiente (no incluida aquí)

Una vez transcritos, se redactará para cada borrador su **artículo** (`post_content`) a partir de
la transcripción, su **metadescripción** y los **datos del episodio**. Publicar queda aparte.
