# t10-residual-fixes

Feature del change `openspec/changes/2026-11-20-t10-residual-fixes/` (archivado; **desplegado en
producción en la release v1.24.1, 2026-11-20**). Cierra los **residuos** que dejó `seo-visibility`
(desplegado en v1.23.0) con el arnés en verde pero sin efecto en producción, y tapa el agujero que
permitió ese falso verde: el plugin se enganchaba a dos filtros de The SEO Framework (TSF) que **no
existen**.

Las cuatro piezas del change son independientes entre sí:

1. **Integración con TSF por sus filtros reales** (`D1`/`D2`).
2. **Guardia anti-falso-verde** sobre los hooks `the_seo_framework_*` que registra el plugin (`D5`).
3. **Autor canónico**: paso de datos idempotente + `301` del nicename heredado (`D3`).
4. **`itunes:explicit`** con booleano de Apple (`D4`).

## Qué es y ficheros implicados

- `wp-content/plugins/atareao-functionality/includes/class-seo.php` — registro de los filtros
  reales de TSF (`SEO::init()`), `filterGeneratedTitle()`, `filterGeneratedDescription()`,
  `filterUseTitleBranding()`, `coveredSurface()`, `isForeignContext()` y
  `redirectLegacyAuthorNicename()`.
- `wp-content/plugins/atareao-functionality/includes/class-author-canonical.php` — constantes del
  autor canónico, paso de datos `AuthorCanonical::canonicalize()` y subcomando
  `wp atareao-author canonicalize [--dry-run]`.
- `wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php` — normalización de
  `itunes:explicit` en `channelElements()`.
- `wp-content/plugins/atareao-functionality/includes/class-theme-options.php` — `<select>` de la
  pestaña *Tema* (`false`/`true`) y `sanitizePodcastExplicit()`.
- `wp-content/plugins/atareao-functionality/README.md` — contrato de `itunes:explicit`, comando del
  autor y verificación de hooks.
- `tests/t10-residuals/verified-hooks.php` — **fuente única de verdad** de los hooks verificados y
  prohibidos, más la guardia (`tsf_hook_violations()`) y la verificación contra la copia instalada
  (`tsf_verify_installed_hooks()`).
- `tests/t10-residuals/test_seo_tsf_integration.php`,
  `tests/t10-residuals/test_feed_explicit.php`, `tests/t10-residuals/test_author_canonical.php` —
  arneses de los tres bloques.
- `tests/t10-residuals/assertions.php` — aserciones y utilidades compartidas por los arneses.
- `scripts/verify-tsf-hooks.php` — paso ejecutable de verificación contra TSF instalado.
- `.justfile` — recetas `test-t10` y `verify-tsf-hooks`; `test` ejecuta `test-t10`.
- `.github/workflows/ci.yml` — los tres arneses en el job `lint`, sin contenedor.
- `tests/test-valet.php` — escenarios de portada/listados/singular migrados a los filtros reales y
  `itunes:explicit` esperado como `true|false`.

## Cómo funciona

### Filtros reales de TSF (`D1`)

TSF 5.2.1 (`autodescription`) **no dispara** `the_seo_framework_title` ni
`the_seo_framework_description`; el plugin los registraba y por eso no pasaba nada. El contrato
vigente es:

| Hook | Firma | Uso en el plugin |
|---|---|---|
| `the_seo_framework_title_from_generation` | `( $title, ?array $args )` | `SEO::filterGeneratedTitle()` |
| `the_seo_framework_generated_description` | `( $desc, ?array $args, string $type )` | `SEO::filterGeneratedDescription()` |
| `the_seo_framework_use_title_branding` | `( $use, ?array $args, bool $social )` | `SEO::filterUseTitleBranding()` |
| `the_seo_framework_schema_graph_data` | `( $graph, ?array $args )` | `SEO::addArticleSchema()` (sin cambios) |
| `the_seo_framework_sitemap_exclude_ids` | `( $ids )` | `SEO::filterExcludeOldPages()` (sin cambios) |

Puntos finos del contrato:

- **`$args` puede ser `null`.** En la ruta auto-determinada —la habitual en la portada— TSF llama
  con `$args = null`; por eso el callback **nunca** desreferencia `$args` y decide la superficie por
  la **petición actual** con los condicionales de WordPress.
- **Alcance estricto.** `coveredSurface()` devuelve `front`, `blog`, `tutorial`, `podcast`,
  `aplicacion` o `software`; en cualquier otra petición devuelve `''` y el callback devuelve el valor
  recibido **intacto**.
- **Contextos ajenos inertes.** `isForeignContext()` corta antes de resolver la superficie en admin,
  AJAX, feeds, REST/JSON, `robots` y el sitemap de TSF. El sitemap se detecta con
  `tsf()->query()->is_sitemap()` (guardado con `is_object()`/`method_exists()`): TSF 5.2.1 **no**
  registra la query var `the_seo_framework_sitemap`, así que consultarla sería una guardia ficticia.
- **Sufijo de marca.** `filterUseTitleBranding()` devuelve `false` **solo** en las superficies
  cubiertas. No se toca el ajuste global `title_rem_additions` ni se afecta a posts, tutoriales o
  episodios. Es la vía correcta (filtro) frente a escribir opciones de TSF.
- **Respeto a TSF.** El plugin no escribe ningún ajuste de TSF; si `homepage_title` está definido en
  TSF, TSF compone el título antes de este filtro y su valor gana.
- **Descripción en los tres tipos.** `search`, `opengraph` y `twitter` reciben la misma descripción
  curada, de modo que `meta description`, `og:description` y `twitter:description` coinciden.

### Guardia anti-falso-verde (`D5`)

`tests/wp-stubs.php` implementa `add_filter()` como un stub que **no comprueba que el hook exista**;
ese es el origen del falso verde. La guardia compara cada hook `the_seo_framework_*` registrado por
el plugin con `tsf_hook_policy()`:

- `verified` — los cinco hooks de la tabla anterior, con `fichero:línea` de TSF 5.2.1.
- `prohibited` — `the_seo_framework_title` y `the_seo_framework_description`.

Un hook con el prefijo `the_seo_framework_` que no esté en `verified` (o que esté en `prohibited`)
**rompe el arnés**. La guardia se ejecuta **sin TSF instalado** (como en CI), así que no necesita el
plugin real. La verificación contra el TSF instalado es un paso aparte:

```bash
just verify-tsf-hooks /ruta/a/wp-content/plugins/autodescription
```

El script comprueba que cada hook de la lista aparece como `apply_filters()` en el código de TSF y
sale con código distinto de cero si falta alguno. Como el script vive en el repositorio y no en el
volumen de producción, la verificación se hace sobre una **copia de solo lectura** del plugin
instalado (no escribe nada en el servidor):

```bash
ssh atareao 'docker exec wpcli tar -C /var/www/html/wp-content/plugins/autodescription -cf - inc autodescription.php' \
  | tar -x -C /tmp/tsf-5.2.1
just verify-tsf-hooks /tmp/tsf-5.2.1
```

Salida esperada (TSF 5.2.1):

```
TSF esperado: 5.2.1
  OK   the_seo_framework_title_from_generation
  OK   the_seo_framework_generated_description
  OK   the_seo_framework_use_title_branding
  OK   the_seo_framework_schema_graph_data
  OK   the_seo_framework_sitemap_exclude_ids

5/5 hooks verificados.
```

### Autor canónico (`D3`)

El 404 de `/author/atareao/` era un problema de **datos**, no de rewrite: el autor 3 tenía
`user_login=yyyxfsgj`, `user_nicename=yyyxfsgj` y `display_name=atareao`, así que WordPress resolvía
el archivo solo bajo `/author/yyyxfsgj/` (ilegible) y `/author/atareao/` no encontraba usuario.
`SEO::ensureAuthorArchive()` (la rewrite) **no es la solución** y no se usa para esto.

Dos piezas:

1. **Paso de datos idempotente** — `wp atareao-author canonicalize [--dry-run]`, que fija
   `user_nicename = atareao` para el autor 3. Devuelve un informe (`needed`, `changed`, `previous`,
   `error`); si el nicename ya es el canónico no escribe nada, y si otro usuario ocupa ese nicename
   falla sin tocar datos.
2. **Redirección del path heredado** — `SEO::redirectLegacyAuthorNicename()` en `template_redirect`
   (prioridad 2): detecta el prefijo **`^/author/yyyxfsgj(/|$)`** (la rewrite lo resuelve a 404, así
   que se mira el path, no el objeto consultado) y emite **un único `301`** hacia
   `get_author_posts_url()` del autor, **preservando la subruta y la query string**:
   `/author/yyyxfsgj/page/2/` → `/author/atareao/page/2/`, `/author/yyyxfsgj/feed/` →
   `/author/atareao/feed/` y `?feed=rss2` se conserva. No redirige si el dato aún no se corrigió (el
   destino sería la propia petición) ni si el destino coincide con el origen: sin bucles. Las
   subrutas canónicas (`/author/atareao/page/2/`) no se redirigen.

### `itunes:explicit` (`D4`)

Apple exige un **booleano** en `itunes:explicit`; el W3C Feed Validator rechaza `no`:

```
line 32, column 19: itunes:explicit must be "true" or "false": no
```

`PodcastFeed::channelElements()` normaliza el ajuste: `yes`/`true`/`1`/`on` → `true`; `no`,
`clean`, `0`, vacío, `null` o cualquier otro valor → `false`. El **default** pasa a `false`, y el
`<select>` de la pestaña *Tema* ofrece solo `false`/`true`, mapeando los valores heredados con el
mismo saneador (`yes`/`1`/`on` → `true`; `no`/`clean`/`0` → `false`) para que la UI muestre lo que
el feed emitirá. `sanitizePodcastExplicit()` normaliza al guardar. El feed principal `/feed/` no
lleva el tag.

## Variables y configuración

| Opción | Elemento del feed | Valores | Default |
|---|---|---|---|
| `atareao_podcast_explicit` | `itunes:explicit` | `true`/`false` (acepta `yes`/`no`/`clean`/`1`/`on` heredados) | `false` |

La integración con TSF y la redirección del autor **no** añaden opciones: se resuelven por código y
por datos.

## Cómo habilitar / desplegar

1. `just test` (incluye `test-t10`) y `just php-lint`.
2. Release según `GIT_FLOW.md`; el cambio entra por PR a `development`.
3. Tras desplegar, ejecutar el paso de datos **una vez** (es idempotente):
   `wp atareao-author canonicalize --dry-run` y después sin `--dry-run`. Sin este paso,
   `/author/atareao/` sigue en 404 y la redirección no se activa a propósito (evita el bucle).
4. Verificar los hooks contra el TSF instalado: `just verify-tsf-hooks <plugin_dir>` (o la variante
   en solo lectura sobre la copia extraída del contenedor).
5. Vaciar la caché del feed si aplica (`/podcast/feed/`).

Ningún paso de este change escribe ajustes de TSF ni toca producción por sí mismo.

## Resultado en producción (2026-11-20, v1.24.1)

Desplegado con `just deploy-prod` (`sha256` + `owner=82:82` verificados) y medido con
***cache-busting*** (`?cb=$RANDOM`, `x-cache-status: BYPASS`) porque el TTL del HTML es de **6 h**.

| Superficie | `<title>` | `meta description` |
|---|---|---|
| `/` | **17** chars: `atareao con Linux` | 150 (curada) |
| `/blog/` | 42: `Blog de Linux, Docker, Rust y self-hosting` | 154 (curada) |
| `/tutoriales/` | 10: `Tutoriales` | 150 |
| `/podcast/` | 7: `Podcast` | 152 |
| `/aplicaciones/` | 12: `Aplicaciones` | 142 |
| `/software/` | 8: `Software` | 137 |

Ninguna de las seis lleva sufijo de marca. Sin regresión en singular: el post
`/ubuntu/extension-manager-una-herramienta-perfecta-para-ubuntu/` mantiene 75 chars **con**
` - atareao con Linux` (descripción 149) y el episodio
`/podcast/tu-nube-es-tuya-self-hosting-con-podman/` 60 chars **con** la marca (descripción 138).

**Autor:** `/author/atareao/` → **200**; `/author/yyyxfsgj/` → **301** a
`https://atareao.es/author/atareao/?cb=…`; `/author/yyyxfsgj/page/2/` → **301** a
`/author/atareao/page/2/`; `/author/yyyxfsgj/?feed=rss2` → **301** a `/author/atareao/?feed=rss2&…`
(subruta y query preservadas). El paso de datos dejó `user_login=yyyxfsgj`,
`user_nicename=atareao`, `display_name=atareao`.

**Feed:** `/podcast/feed/` emite `<itunes:explicit>false</itunes:explicit>` y conserva **850 items y
850 guids, 0 con `http://`** (sin regresión de `podcast-guid-parity`); `/feed/` principal con **0**
ocurrencias del tag. El **W3C Feed Validator** responde «This is a valid RSS feed.» y el error
`itunes:explicit must be "true" or "false"` ya no aparece.

**Guardia contra el TSF real:** `just verify-tsf-hooks /tmp/tsf/autodescription` (TSF 5.2.1,
descargado de wordpress.org) → **5/5 hooks verificados** (`title_from_generation`,
`generated_description`, `use_title_branding`, `schema_graph_data`, `sitemap_exclude_ids`).

## Decisiones

- **Un solo `301` desde el path heredado**, no una rewrite ni un `canonical`: el dato se corrige y la
  redirección solo cubre enlaces antiguos.
- **`filterUseTitleBranding` en lugar de tocar `title_rem_additions`**: no se altera un ajuste global
  ni el título de posts y episodios.
- **Guardia con lista en el repositorio**: el arnés no puede detectar hooks inexistentes por sí solo;
  la lista verificada convierte ese conocimiento en un test que corre en CI sin TSF instalado.
- **`selected()` sobre un valor normalizado**: la UI enseña `true`/`false` aunque el ajuste guardado
  sea heredado, sin reescribirlo al abrir la página.

## Follow-ups

- **Verificación en producción hecha** (v1.24.1, 2026-11-20; ver «Resultado en producción»). Quedan
  sin cubrir por esa evidencia: `title_rem_additions` (8.4.2), ajustes de `homepage_title` (8.4.3) y
  no regresión de tutorial, sitemap y admin (8.4.7).
- El fallo preexistente de `tests/test-valet.php` (`server.json` con `1.22.0` frente al plugin
  `1.24.0`, issue #192) sigue abierto y es ajeno a este change.
- La guardia solo vigila hooks con el prefijo `the_seo_framework_`; si TSF renombra un hook en una
  versión futura, el paso `verify-tsf-hooks` lo detecta pero CI no (CI no tiene TSF instalado).