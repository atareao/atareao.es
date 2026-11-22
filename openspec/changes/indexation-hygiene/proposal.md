# Proposal

> Issue: **atareao/visibilidad#21** — «T11 — Higiene de indexación (GSC): noindex en búsquedas y
> categorías + robots.txt». Fuente de verdad del diagnóstico y de las decisiones:
> `plans/PLAN-008.md` del repo `visibilidad`.
>
> **No se busca indexar más contenido.** Este change es **higiene de rastreo**: reducir el ruido que
> Google Search Console mide, no ganar posiciones.

## Why

El cierre de GSC del 2026-11-16 (16.635 URLs conocidas, 5.445 indexadas) presenta **1.345 URLs
«rastreadas: actualmente sin indexar»** que son ruido, no contenido: las **búsquedas** —`/search/`
responde `200`, indexable y con paginación ilimitada (visto `page/309`) salvo cuando no hay
resultados— y los **archivos de categoría** (`/software/` 143 páginas, `/ubuntu/` 72, `/como/` 40,
todas indexables y self-canonical). Además, 7.218 URLs quedan fuera del índice por `?replytocom=`
(dos metas `robots` conviviendo) y hay 352 `404`, de los que ~10 % son estructuras antiguas propias
(`/tutorial/opencode/*`, `/instalar/*`, `/amp/`) sin `301`. Es el momento porque el cliente de solo
lectura de GSC de `gsc-reporting` (`just gsc`) permite re-medir el efecto del cambio.

## What Changes

- **Búsquedas fuera del índice.** Toda URL de `/search/` —con y sin resultados, incluida su
  paginación— pasa a `noindex, follow`. Hoy TSF solo lo aplica a las búsquedas **sin** resultados.
- **Archivos de categoría fuera del índice.** Los archivos de categoría (`/ubuntu/`, `/como/`, …) y
  las familias medidas en GSC (`/software/`) pasan a `noindex, follow`, incluidas sus páginas de
  paginación. Los listados que se mantienen como superficies indexables (`/blog/`, `/tutoriales/`,
  `/podcast/`, `/aplicaciones/`) no se tocan.
- **`robots.txt` de rastreo.** Se añaden `Disallow: /*?replytocom=` y `Disallow: /search/` al
  `robots.txt` (que es virtual: lo genera WordPress y lo filtra The SEO Framework).
- **Mapa mínimo de `301`.** Se cubren las familias `/tutorial/opencode/*`, `/instalar/*` y `/amp/` y
  se fija la regla de que un `404` con equivalente conocido se sirve como `301`.
- **Una sola meta `robots`.** Las páginas de comentario (`?replytocom=`) dejan de emitir dos metas
  `robots` (WordPress y TSF) y emiten una sola.
- **BREAKING (operativo, no de API).** `Disallow: /search/` saca las búsquedas del rastreo: deja de
  ser posible diagnosticar el sitio a través de búsquedas indexadas. Google tarda en reprocesar
  `robots.txt`, así que el efecto es de medio plazo.

## Capabilities

### New Capabilities

- `search-indexing`: la indexabilidad de las páginas de búsqueda del sitio y de su paginación.
- `category-archives`: la indexabilidad de los archivos de categoría y de las familias de listado
  medidas en GSC, incluida su paginación.
- `robots-meta`: la emisión de la etiqueta `<meta name="robots">` en el documento servido, con la
  invariante de una sola etiqueta por documento.
- `crawl-policy`: las directivas `Disallow` del `robots.txt` servido.
- `legacy-redirects`: el mapa de redirecciones `301` para las estructuras antiguas de URL y la regla
  de que un `404` con equivalente conocido se sirva como `301`.

### Modified Capabilities

Ninguna. `seo` documenta títulos y descripciones y `mcp-discovery` la directiva `Agentmap:` del
`robots.txt`; este change no cambia esos contratos, los respeta y los extiende por adición (las
nuevas capabilities conviven con ellos).

## Impact

- **Código (solo tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-seo.php` para la emisión del `noindex`
    de búsquedas y categorías y para el punto de extensión del filtro `robots_txt`; el mapa `301`
    vive junto a las redirecciones ya existentes del mismo plugin (o en una clase nueva con el mismo
    contrato, p. ej. `class-legacy-redirects.php`).
  - Pospuesta la verificación del **hook real** de TSF que emite el `robots` (su código no está
    versionado, solo vive en el contenedor de producción): ver `design.md` §D1.
- **Config:** el `robots.txt` es **virtual** (no existe fichero en disco); este change NO crea ningún
  `robots.txt` estático. Sin credenciales nuevas. Posible ajuste de The SEO Framework 5.2.1 en
  producción si el `noindex` se aplica por su interfaz en lugar de por código.
- **Tests:** el arnés procedural `tests/test-valet.php` (`just test`) y la guardia de hooks
  `tests/t10-residuals/verified-hooks.php`; cualquier hook de TSF nuevo SHALL registrarse en la
  guardia **antes** de usarse (lección del issue #200).
- **Docs:** `docs/indexacion.md` con el diagnóstico, los cambios y la re-medición a 28 días;
  `openspec/specs/` gana cinco capabilities nuevas al archivar.
- **Fuera de alcance:** contenido editorial, sitemap, lista de exclusión de IDs del sitemap,
  taxonomía de tags (el sitio no la tiene) y el core de WordPress / `nginx/pro/wp.conf` más allá de
  lo estrictamente necesario.
