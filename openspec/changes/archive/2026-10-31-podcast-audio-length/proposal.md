# Proposal: Tamaño real del audio remoto en el `<enclosure>` del feed del podcast

## Why

El E2E del feed **servido** (change `podcast-feed-namespace`, archivado) destapó que los **100** episodios publicados emiten `enclosure/@length="0"`:

```
enclosures: 100 · type: {'audio/mpeg': 100}
length=0 (tamaño no determinable): 100
length>0: 0
```

No es una violación del spec —`0` es el valor neutro que el requisito autoriza cuando el tamaño no puede determinarse—, pero deja **sin ejercer la rama principal del requisito**: `audioLength()` mide el fichero con `filesize()`, y para eso necesita resolver el adjunto local con `attachment_url_to_postid()`. Todo el audio del programa es **remoto** (`anchor.fm` → cloudfront) y esa función **no resuelve una URL externa**, así que la rama del tamaño real **hoy nunca se ejecuta**. El `0` además priva a clientes y directorios de la información con la que muestran el tamaño de descarga y estiman el progreso.

Verificado empíricamente **antes** de redactar este cambio (no es una suposición): una petición `HEAD -L` a la URL real de un episodio sigue **una** redirección y devuelve `content-length: 24594909` (≈24,6 MB) en **0,39 s**; la alternativa `GET` con `Range: bytes=0-0` responde `206` con `content-range: bytes 0-0/24594909`.

## What Changes

- **Orden de resolución del tamaño** en `audioLength()`: **(1)** adjunto local legible (ruta actual, intacta); **(2)** tamaño declarado por la CDN al pedir la URL con `HEAD` **siguiendo las redirecciones**; **(3)** total derivado de un `GET` con `Range: bytes=0-0` leído del `Content-Range`; **(4)** `0`.
- **Caché del tamaño en el metadato del episodio:** un acierto se cachea con caducidad larga; un fallo con caducidad corta, para reintentar más tarde. Evita repetir **una petición por episodio en cada composición del feed**.
- **Presupuesto de red acotado por composición:** la composición del feed nunca se bloquea en la red más allá de un presupuesto de tiempo; los episodios no resueltos a tiempo emiten `0` y se resuelven en composiciones posteriores o con el comando de calentado.
- **Guarda SSRF:** solo `http`/`https`, se valida **cada salto** de redirección y se rechaza cualquier host privado, reservado, loopback o link-local; además `reject_unsafe_urls` del cliente y número de saltos acotado.
- **Un fallo nunca rompe el feed:** `is_wp_error`, timeout, código inesperado o ausencia de longitud → `0`, `<enclosure>` presente y XML válido.
- **Comando WP-CLI de calentado (recomendado):** resuelve y cachea los tamaños de todos los episodios en una pasada, para que el feed no tenga que hacerlo de forma incremental.

## Capabilities

### Modified Capabilities

- `podcast-feed`: se modifica el requisito **Episodio con audio y numeración** — se precisa el **orden de resolución del tamaño**, la **caché** del resultado, el **presupuesto de red** por composición, la **guarda SSRF** y la **no interferencia** ante el fallo —, con escenarios nuevos.

## Impact

- **Archivos a modificar (solo tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — `audioLength()` y helpers nuevos (resolución remota, guarda SSRF, caché); `itemElements()` pasa el ID del episodio; registro del comando WP-CLI.
  - `tests/test-valet.php` — aserciones de los escenarios nuevos.
  - `tests/wp-stubs.php` — dobles fieles a core de `wp_safe_remote_head`, `wp_safe_remote_get`, `wp_remote_retrieve_header`, `wp_remote_retrieve_response_code` y `wp_remote_retrieve_body`; y del registro de comandos WP-CLI si el comando se incluye.
- **Sin cambios** en el vocabulario del canal, la numeración, la transcripción, el `Content-Type` ni el feed principal.
- **Coste de red:** acotado al **primer** cálculo de cada episodio (después manda la caché), limitado por el presupuesto de composición y por el timeout.
