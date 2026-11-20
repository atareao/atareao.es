# 2026-11-20-t10-residual-fixes

## Why

El change `seo-visibility` se desplegó en **v1.23.0** con el arnés en verde y, sin embargo, en
producción **no funciona**: el HTML servido sigue con el `<title>` de portada de 305 caracteres,
`/blog/` sin `meta description`, los listados con las descripciones de The SEO Framework (TSF) en
lugar de las curadas y `/author/atareao/` devolviendo 404. Es decir: **verde en CI, roto en
producción**. Este change arregla los residuos reales y, sobre todo, cierra el agujero que permitió
ese falso verde.

**Causa raíz del falso verde.** El arnés del proyecto (`tests/test-valet.php` y los arneses de
`tests/`) ejecuta el plugin con **dobles de WordPress** (`tests/wp-stubs.php`), donde `add_filter()`
es un stub que **registra el filtro sin comprobar que el hook exista**. Con eso, un
`add_filter('the_seo_framework_title', …)` a un hook que TSF nunca dispara pasa el test tan
campante: el arnés verifica que *el plugin registra un filtro*, no que *el filtro se llegue a
ejecutar*. La prueba, además, invocaba el callback a mano, saltándose por completo la cadena de
disparo de TSF. Nada en el arnés podía detectar el fallo, porque el contrato que se estaba probando
era inventado.

Y el contrato **era** inventado: en TSF 5.2.1 (plugin `autodescription`) **no existen** los filtros
`the_seo_framework_title` ni `the_seo_framework_description` — 0 ocurrencias de
`apply_filters('the_seo_framework_title'` y de `apply_filters('the_seo_framework_description'` en su
código. Los filtros reales son otros, con **otra firma**. Por eso este change introduce, además de
las correcciones, una **guardia anti-falso-verde** que ata cada hook `the_seo_framework_*` que
registra el plugin a una lista de hooks **verificados** contra el código de TSF.

## What Changes

- **D1 — Integración con TSF por sus filtros reales.** Se sustituyen los dos hooks muertos por los
  que TSF 5.2.1 dispara de verdad:
  - Título: `the_seo_framework_title_from_generation` → `( $title, ?array $args )`, con
    `$args['id']`, `$args['tax']`, `$args['pta']` y `$args['uid']` (puede ser `null`).
  - Descripción: `the_seo_framework_generated_description` → `( $desc, ?array $args, string $type )`,
    con `$type` ∈ `search|opengraph|twitter`.
  - `the_seo_framework_schema_graph_data` (JSON-LD) y `the_seo_framework_sitemap_exclude_ids` **sí
    existen** y **no se tocan**.
- **D2 — Efecto visible.** Con los filtros reales: el `<title>` de la portada baja a **≤60
  caracteres** y los títulos y descripciones curados de `LISTING_TITLES` / `LISTING_DESCRIPTIONS`
  (`class-seo.php:33-54`) llegan a `/blog/`, `/tutoriales/`, `/podcast/`, `/aplicaciones/` y
  `/software/`, también en `og:`/`twitter:`. Fuera de esas superficies, el valor recibido se devuelve
  **intacto**.
- **D3 — Autor.** El 404 de `/author/atareao/` es un problema de **datos**: el autor 3 tiene
  `user_login=yyyxfsgj`, `user_nicename=yyyxfsgj` y `display_name=atareao`. Se renombra el
  `user_nicename` a `atareao` con un **paso de datos idempotente** (WP-CLI) y se añade un **301** desde
  el nicename heredado `yyyxfsgj` hacia la URL canónica, en **una sola redirección y sin bucle**. La
  regla de rewrite de `ensureAuthorArchive()` (`class-seo.php:1033`) no resuelve el problema y **no
  es** la solución.
- **D4 — `itunes:explicit` válido.** El feed emite hoy `<itunes:explicit>no</itunes:explicit>`
  (`class-podcast-feed.php:361-373`) y el **W3C Feed Validator lo rechaza**: *«itunes:explicit must be
  "true" or "false"»*. Debe emitir `true`/`false`, con compatibilidad de los valores heredados
  (`yes`→`true`; `no`/`clean`→`false`; cualquier otro→`false`). El ajuste se define en
  `includes/class-theme-options.php` (select) y en el README del plugin.
- **D5 — Guardia anti-falso-verde.** Un test exige que **cada** hook `the_seo_framework_*` que
  registra el plugin figure en una **lista verificada** de hooks de TSF, de modo que un hook muerto no
  pueda volver a colarse. En CI (sin TSF instalado) la guardia comprueba la pertenencia a la lista;
  un paso de verificación en producción comprueba que la lista sigue coincidiendo con el código de
  TSF instalado.

## Falsos positivos descartados

No se incluyen en el alcance, tras verificarlos:

- `href="#apple"` / `#ivoox` / `#youtube` **no** son enlaces rotos: son referencias al sprite SVG
  (`<use href="#…"/>`).
- El canal de YouTube servido ya es el correcto (`youtube.com/channel/UCP8dTCaLNJFxfklb_GNqe6A`).
- La URL de iVoox almacenada es la estándar.
- El JSON-LD (`PodcastEpisode`, un único bloque) y el `<h1>` único por listado ya están bien.

## Capabilities

### Modified Capabilities

- `seo`: la integración con The SEO Framework se hace por sus **filtros reales**, con contrato
  explícito de los dos callbacks (nombre, firma, alcance estricto y valor devuelto fuera de alcance);
  se añade la **guardia** que liga los hooks registrados a la lista verificada; se fija el contrato de
  títulos y descripciones curados de la portada y los listados (incluido `og:`/`twitter:`); se fija el
  archivo de autor canónico con el 301 del nicename heredado y la idempotencia del paso de datos; y se
  fija la **no regresión** fuera de las superficies cubiertas.
- `podcast-feed`: `itunes:explicit` SHALL emitirse con un valor booleano conforme a Apple
  (`true`/`false`), con valor por defecto `false` y compatibilidad de los valores heredados, de forma
  que el feed completo pase el validador.

## Impact

- **Specs al archivar:** delta ADDED sobre `openspec/specs/seo/` y delta MODIFIED sobre
  `openspec/specs/podcast-feed/`.
- **Código (solo tras aprobación):**
  - `wp-content/plugins/atareao-functionality/includes/class-seo.php` (hooks y callbacks de TSF,
    guardia del arnés y 301 del autor).
  - `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` (normalización de
    `itunes:explicit`).
  - `wp-content/plugins/atareao-functionality/includes/class-theme-options.php` (select del ajuste) y
    `wp-content/plugins/atareao-functionality/README.md` (documentación del ajuste).
  - Un subcomando WP-CLI para el paso de datos del autor (idempotente), a ubicar donde vivan los
    comandos del plugin.
- **Datos de producción:** renombrar el `user_nicename` del autor 3 a `atareao` (paso idempotente).
- **Tests:** arnés del proyecto con la lista verificada de hooks de TSF y los escenarios de este
  change; más un paso de verificación en producción (TSF instalado) de la lista contra el código real.
- **Config:** ninguna credencial nueva. El ajuste `atareao_podcast_explicit` cambia su valor por
  defecto a `false`.
- **No-goals (fuera de alcance, con su issue ya abierto):**
  - **#192** — `server.json` sin bumpear en el release.
  - **#193** — `/sitemap_index.xml` devuelve 200 en vez de 301.
  - **#194** — backfill/formato de `duration`.
  - **#199** — backfill de `transcript-srt` de los episodios 19746 y 19749.
  - **No** se migra el feed ni se emite `<itunes:new-feed-url>`.
