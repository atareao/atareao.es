# Design: Transcribir episodios en borrador (opt-in)

## Context

El pipeline `transcribe.py` recolecta episodios vía `wp-collect.php` (que filtra
`post_status = publish` en la propia consulta) y sube los SRT vía `wp-attach.php`, cuyo guardián
rechaza cualquier destino que no sea `podcast` **publicado**. `select_episodes()` en Python vuelve
a exigir `status == "publish"`.

Los 5 borradores objetivo están en `post_status = draft` con `mp3-url` válida.

## Decisions

### D1 — Opt-in por bandera, no cambio de defecto

Se añade `--include-drafts` (booleano, por defecto **desactivado**). Sin la bandera, la recolección
y la subida se comportan **exactamente** como hoy. Esto preserva el contrato de seguridad y evita
sorpresas en ejecuciones automáticas.

### D2 — La recolección consulta el estado permitido

`wp-collect.php` recibe un argumento opcional; sin él, `post_status = publish` (igual que hoy).
Con el valor de inclusión, consulta `post_status = ['publish', 'draft']`. El filtro sigue
aplicándose **en la consulta**, no en Python.

### D3 — El guardián de subida recibe los estados permitidos

`wp-attach.php` recibe como argumento el conjunto de estados permitidos (por defecto `publish`).
Mantiene la comprobación de tipo (`podcast`) y añade la de estado según ese conjunto. El pipeline
pasa `publish,draft` cuando `--include-drafts` está activo. Se conserva, sin cambios, la
verificación de que el adjunto creado existe y es legible, y la relectura de la meta.

### D4 — Mismo flujo y mismas garantías

Un borrador recorre idéntico pipeline: descarga → `measure_audible_fraction` (gate `sin voz`) →
`preprocess_audio` → `transcribe_audio` → `_sanitize_srt` → `_ensure_valid_srt` → subida. La
idempotencia (saltar si `transcript-srt` apunta a un adjunto accesible), el aislamiento de fallos,
el saneo y la detección de `sin voz` aplican igual.

### D5 — No se cambia el estado editorial ni el feed

El proceso escribe adjunto y meta, **no** el `post_status`. Los borradores no aparecen en el feed
(la consulta del feed es de publicados), así que no hay efecto visible. Publicar es una acción
**aparte**, fuera de este change.

### D6 — La fase de contenido va aparte

Redactar el artículo, la metadescripción y completar los datos del episodio es **contenido**, no
código: no entra en este change. Se hará cuando existan las transcripciones.

## Alternatives considered

- **Publicar temporalmente y revertir**: evita tocar código, pero expone los episodios en el feed
  unos momentos (riesgo de fuga/navegación y notificaciones). Descartado.
- **Script puntual de un solo uso**: requeriría igualmente relajar el guardián de `wp-attach.php`;
  además dejaría el comportamiento sin especificar ni testear. Descartado frente a extender el
  pipeline de forma explícita y probada.
- **Ampliar el guardián a borradores de forma permanente**: debilita la seguridad por defecto.
  Descartado; se prefiere el opt-in.
