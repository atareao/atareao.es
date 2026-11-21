# Design: Feed de podcast completo, compatible con Anchor y con transcripciones

## Context

Ver `proposal.md` — Why. Hechos **medidos hoy (2026-11-19)** que condicionan el diseño; ninguno es una
suposición:

- **Quién lee qué.** `itunes.apple.com/lookup?id=1437197202` resuelve a
  `https://anchor.fm/s/5a5b39c/podcast/rss` (**850 items**, todos con `itunes:duration` e
  `itunes:image`, orden descendente por `pubDate`, GUID en formato UUID). El feed de WordPress
  `/podcast/feed/` (**100 items**) **no lo consume ningún directorio**.
- **El archivo completo está en WordPress.** 849 entradas publicadas del CPT `podcast`
  (2018-09-10 → 2026-11-19, ~100–105/año). El feed corta en 100 por `posts_per_rss = 100`.
- **El audio no es propio.** Los `<enclosure>` del feed de WordPress apuntan al CDN de Anchor
  (`anchor.fm/s/5a5b39c/podcast/play/…` → `d3ctxlq1ktw2nl.cloudfront.net`). En Medios hay **1** audio y
  1 GB de uploads (imágenes): el lock-in real es el audio, no el RSS.
- **GUIDs distintos.** Anchor usa UUID (`773cd246-f771-…`); WordPress usa el permalink
  (`https://atareao.es/?post_type=podcast&p=19746`). Migrar sin replicar los GUID de Anchor duplicaría
  los 849 episodios en Apple y Spotify.
- **Metadatos del feed de WordPress incompletos**: `itunes:duration` 32/100, `itunes:image` 0/100,
  descripciones ~136 caracteres (Anchor: mediana 1.513, máx 4.036), `<itunes:type>` serial, sin
  `copyright`, sin `podcast:transcript` ni `podcast:chapters`.
- **La maquinaria de transcripciones ya existe y no se usa del todo.** `includes/class-srt-upload.php`
  registra `.srt` como `application/x-subrip`; el feed ya emite `<podcast:transcript>`
  (`class-podcast-feed.php:438-445`); el pipeline `scripts/podcast-transcripts/` produce y adjunta SRTs
  (834 adjuntos en producción) y hay arneses `just test-transcripts`, `just test-audio` y
  `just test-srt-upload`. Lo que **falta**: (a) mostrar la transcripción como texto en la página y
  (b) la vía de capítulos. No hay que rehacer el pipeline.
- **Muestra de enclosures medida hoy** (40 items aleatorios de Anchor, `HEAD -L`):

  | métrica | valor |
  |---|---|
  | mínimo | 9,9 MB |
  | mediana | 17,3 MB |
  | media | 21,8 MB |
  | máximo | 70,8 MB |
  | archivo completo (×849) | **≈ 18,5 GB** |
  | ritmo actual (~105/año) | **≈ 2,3 GB/año** |

- Restricciones: solo `wp-content/` está trackeado; el core vive en contenedores; **no hay build
  tools**; el trabajo programado está deshabilitado (`DISABLE_WP_CRON=true`), así que lo diferido se
  hace por WP-CLI o en la petición.

## Goals / Non-Goals

**Goals:**

- Que el feed de WordPress sea **completo, válido y con la identidad de Anchor**, de modo que un día
  pueda sustituir al de Anchor sin duplicar episodios.
- Publicar las transcripciones como texto indexable y mantener el contrato del feed intacto.
- Decidir y costear el desacoplamiento del audio con datos medidos.

**Non-Goals:**

- Migrar el feed (301 / Apple) o el audio: se documenta, no se ejecuta.
- Producir documentos de capítulos (no existe productor hoy).
- Tocar el feed principal `/feed/`, las URLs del feed o los adjuntos.

## Decisions

### D1. Feed completo sin tocar el resto de feeds

El techo de 100 viene de `posts_per_rss`. Se sube **solo** para el feed del podcast, con un filtro
(`option_posts_per_rss` / `pre_get_posts` sobre `is_feed() && is_post_type_archive('podcast')`), de modo
que el feed principal y los de otros CPT **conservan su límite**. El orden es el de core (fecha
descendente), que coincide con Anchor.

*Alternativa descartada:* subir `posts_per_rss` globalmente — cambiaría el feed principal y el de
comentarios, que están fuera de alcance.

### D2. Metadatos completos: rellenar datos, no reescribir el contrato

- **`itunes:duration` al 100 %**: el requisito del feed ya emite la duración desde el metadato
  `duration`; lo que falta es el **dato**. Se rellena con el comando existente
  `wp atareao podcast-audio-length` (cambio archivado `podcast-audio-length`), ampliándolo si hace
  falta para derivar la duración del audio. No se toca el requisito de emisión.
- **`itunes:image` por episodio**: se añade un metadato de imagen del episodio (ID de adjunto), con
  **respaldo a la portada del canal** cuando no exista, para que **todos** los items declaren
  `itunes:image`. Anchor ya da imagen a los 850 items.
- **Descripción completa**: el `<description>` de core publica un extracto; pasa a llevar las **notas
  completas** del episodio. El contenido íntegro sigue en `<content:encoded>`; el cambio afecta solo a
  `<description>`.
- **Canal**: `<copyright>` (titular + año) y `<itunes:type>episodic</itunes:type>` por defecto (el
  programa publica episodios independientes, no una serie que deba consumirse en orden), más
  propietario y categorías ya soportados pero vacíos en producción.

### D3. GUID compatible con Anchor mediante mapeo determinista

El `<guid>` de cada item pasa a ser el **UUID del item homólogo de Anchor**, no el permalink:

- **Fuente**: el RSS público de Anchor (ya usado por `podcast-audio-sync`).
- **Clave de emparejamiento**: la **identidad del fichero de audio** (el UUID del `.mp3` en CloudFront
  normalizado), que en Anchor es única por item — es la misma clave determinista que ya validó el
  change `podcast-audio-sync` (844/844 identidades únicas). El número del título queda como respaldo
  cuando no hay audio.
- **Persistencia**: el UUID resuelto se guarda en un metadato del episodio (p. ej. `anchor-guid`), que
  pasa a ser la **única fuente de verdad** del `<guid>`; el mapeo es reproducible y auditable.
- **Garantía**: el feed emite `<guid isPermaLink="false">` con ese valor; dos items no comparten guid.
  Un episodio sin UUID resuelto conserva un guid estable propio y se reporta (no se inventa).

*Alternativa descartada:* derivar el UUID de forma sintética desde el permalink — no coincidiría con
Anchor y **duplicaría** los episodios en los directorios, que es justo lo que se quiere evitar.

### D4. Capítulos: contrato sí, productor no

Se define el elemento `<podcast:chapters>` para el día que exista un documento de capítulos, pero
**este change no produce capítulos**: la maquinaria de transcripción no los genera y no se añade un
productor. Queda como follow-up explícito. (La transcripción sí se publica: el feed ya emite
`<podcast:transcript>`.)

### D5. Estrategia de audio: desacoplar del CDN de Anchor

Decisiones con datos medidos (D5.0). El criterio no es solo el coste, sino **la soberanía**: hoy el
audio vive en CloudFront y el día que Anchor cierre, el archivo completo desaparece aunque el RSS sea
nuestro.

| Opción | Coste estimado (18,5 GB, ~2,3 GB/año) | Ancho de banda | Veredicto |
|---|---|---|---|
| **A. Autoalojado en el VPS** | disco del VPS + 0 € extra; riesgo de llenar el disco y de picos de tráfico | el VPS sirve **todo** el audio: un episodio popular (p. ej. 1.000 descargas × 22 MB ≈ 22 GB) agota el ancho de banda del VPS | Viable a corto, frágil a escala |
| **B. Objetos + CDN** (S3/R2/B2 + Cloudflare) | almacenamiento ~0,3–1 €/mes por 18,5 GB; CDN con *egress* barato o nulo (R2) | el CDN absorbe los picos; el VPS no sirve audio | **Recomendada** |
| **C. Hosting de podcast dedicado** | ~10–20 €/mes según plan | incluido, con analítica de descargas | Válida, pero vuelve a atar el audio a un tercero |

**Decisión propuesta:** **B (objetos + CDN)**, con migración incremental por episodio reescribiendo
`<enclosure>` (ya hay un módulo que escribe la URL de audio). A corto plazo, **A** es aceptable como
prueba de concepto para el archivo histórico si el disco y el ancho de banda del VPS lo permiten, pero
**no** como estrategia de crecimiento. La decisión final la confirma el titular; este change solo la
documenta y la costea.

### D6. Migración futura: cómo se haría el día que toque (sin ejecutarla)

1. **Completar y verificar** este feed (849 items, guid de Anchor, metadatos, validadores en verde).
2. **Spotify for Podcasters**: pedir la **redirección 301** del feed de Anchor hacia
   `https://atareao.es/podcast/feed/`; Spotify conserva la audiencia si el nuevo feed respeta los GUID.
3. **Apple Podcasts**: seguir el flujo documentado de **«cambio de proveedor de hosting»**
   (`podcastsconnect.apple.com` → «Mover un podcast»), con el nuevo feed y los mismos GUID.
4. **Añadir `<itunes:new-feed-url>`** solo **después** de decidir la migración y avisar a los
   directorios; nunca antes, para no redirigir a quien hoy lee el feed de WordPress.
5. **Cerrar Anchor** cuando los directorios hayan migrado.

**Este change se detiene en el paso 1.** No emite `<itunes:new-feed-url>` ni pide redirecciones.

### D7. Verificación

- **Paridad item a item** contra el feed de Anchor: títulos, orden (`pubDate`), GUID y duraciones de
  los 849 episodios; para 10 episodios elegidos al azar, el `<guid>` de WordPress **coincide** con el
  de Anchor.
- **Validadores de feed** (W3C / Podcast Feed Validator) sobre el feed completo.
- **Arneses en verde**: `just test-transcripts`, `just test-audio`, `just test-srt-upload`, además del
  arnés nuevo de paridad/guid.

## Risks / Trade-offs

- **El feed completo es más pesado** (~849 items): se mitiga con la caché de front ya existente
  (`feed-performance-and-caching`). Añadir 750 items más sube el tamaño del XML y el coste de
  composición; la caché lo amortigua.
- **El mapeo de GUID depende del RSS de Anchor**: si Anchor cambia o desaparece **antes** de migrar, el
  mapeo se congela con lo ya resuelto (metadato persistido) y se documenta el estado. Por eso el mapeo
  se persiste, no se recalcula en cada composición.
- **El audio sigue en Anchor** hasta decidir D5: es la deuda aceptada y el lock-in real. El change la
  hace explícita y la costea, pero no la resuelve.
- **Capítulos sin productor**: contrato definido, datos inexistentes; se documenta como follow-up.
