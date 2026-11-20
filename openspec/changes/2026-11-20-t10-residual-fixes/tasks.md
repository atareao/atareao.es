# Tasks: Residuos de T10 — filtros reales de TSF, autor canónico e `itunes:explicit`

> Estado: **APROBADO — GREEN completado** (2026-11-20). Ninguna casilla se marca sin evidencia CLI
> pegada literalmente (ver «Evidencia CLI» al final). Regla dura: RED → GREEN → REFACTOR.
>
> **Dependencia:** `seo-visibility` (no archivado, desplegado en v1.23.0) aporta las tablas
> `LISTING_TITLES`/`LISTING_DESCRIPTIONS` y el grafo JSON-LD; este change corrige su integración.
>
> Las casillas de **verificación sobre el HTML servido** y de **validador de feed** dependen de un
> despliegue que no forma parte de este change.

## 0. Contexto y decisiones previas

- [x] 0.1 Confirmar que `the_seo_framework_title` y `the_seo_framework_description` **no existen** en
      TSF 5.2.1 y que los reales son `the_seo_framework_title_from_generation` y
      `the_seo_framework_generated_description`; pegar el `rg` sobre el plugin `autodescription`
- [x] 0.2 Registrar la lista verificada de hooks de TSF (los que el plugin puede registrar) en el
      repositorio, junto al arnés, con la referencia del fichero y la línea donde TSF dispara cada uno
- [x] 0.3 Confirmar el estado de datos del autor 3 (`user_login`, `user_nicename`, `display_name`) y la
      forma del 404 de `/author/atareao/` (rewrite sí registrada, consulta sin usuario que la resuelva)
- [x] 0.4 Confirmar el rechazo del validador de W3C sobre `<itunes:explicit>no</itunes:explicit>` y
      pegar el mensaje literal
- [x] 0.5 Registrar el ajuste `atareao_podcast_explicit` (valores `no|yes|clean`, default `no`) y su
      ubicación en `includes/class-theme-options.php` y el README del plugin
- [x] 0.6 Registrar la evidencia de H1/H2/H3: `get_bare_generated_title( $args = null )` llama al
      filtro con `$args = null`; `use_branding()` decide el sufijo y aplica
      `the_seo_framework_use_title_branding`; y los ajustes de TSF de la portada
      (`homepage_title`/`homepage_tagline`/`title_rem_additions`/`title_separator`)

## 1. RED — contrato de los callbacks reales de TSF

- [x] 1.1 Arnés (estilo del proyecto, `exit(0/1)`, dobles de `tests/wp-stubs.php`) para el callback de
      título: registrar `the_seo_framework_title_from_generation` y ejercitar la firma
      `( $title, ?array $args )`
- [x] 1.2 Prueba *portada*: en `is_front_page()` devuelve el título curado y mide **≤60**
- [x] 1.3 Prueba *listados*: en `/blog/` y en las cuatro plantillas devuelve el título curado de la
      superficie; ninguna termina en el sufijo de marca
- [x] 1.4 Prueba *identidad*: en un post, un tutorial y un episodio devuelve el valor recibido **sin
      cambios**
- [x] 1.5 Prueba *contextos ajenos*: con admin, AJAX, feed, REST, sitemap o `robots` activos devuelve
      el valor recibido sin cambios
- [x] 1.6 Prueba *`$args` anulado*: con `$args = null` y con `$args` sin las claves `id`/`uid` no hay
      error y se devuelve el valor recibido
- [x] 1.7 Arnés del callback de descripción: firma `( $desc, ?array $args, string $type )` y pruebas de
      portada/listados para `search`, `opengraph` y `twitter`
- [x] 1.8 Prueba *identidad de descripción*: fuera de las superficies cubiertas devuelve el valor
      recibido sin cambios
- [x] 1.9 Prueba *no se registran los hooks muertos*: el plugin no registra `the_seo_framework_title`
      ni `the_seo_framework_description`
- [x] 1.10 Prueba *ruta auto-determinada*: con `$args = null` y petición = superficie cubierta
      (portada), el callback devuelve el título curado y **no** el valor recibido
- [x] 1.11 Prueba *sufijo de marca*: `filterUseTitleBranding()` devuelve `false` en las superficies
      cubiertas e identidad en un post y un episodio (que conservan su marca)
- [x] 1.12 Evidencia RED pegada literalmente: arnés nuevo con **exit 1** (y el resto del arnés en
      verde)

## 2. GREEN — integración por los filtros reales

- [x] 2.1 `class-seo.php`: sustituir los dos `add_filter` muertos (`:107-108`) por
      `the_seo_framework_title_from_generation` (10, 2) y `the_seo_framework_generated_description`
      (10, 3)
- [x] 2.2 Implementar `filterGeneratedTitle( $title, $args )` con **alcance estricto**: portada y
      listados cubiertos → título curado con `limitTitle()`; resto → `$title` intacto. Documentar en el
      docblock la firma de TSF y por qué el segundo argumento es anulable
- [x] 2.3 Implementar `filterGeneratedDescription( $desc, $args, $type )`: portada y listados cubiertos
      → descripción curada para los tres `$type`; resto → `$desc` intacto
- [x] 2.4 Garantizar la **identidad** en admin, AJAX, feeds, REST, sitemaps y `robots` (bail-out
      explícito antes de resolver la superficie)
- [x] 2.5 Implementar el callback de sufijo de marca: registrar
      `the_seo_framework_use_title_branding` (10, 3) con `filterUseTitleBranding( $use, $args, $social )`
      devolviendo `false` **solo** en las superficies cubiertas e identidad en el resto; **sin** tocar
      el ajuste global `title_rem_additions`
- [x] 2.6 Confirmar que no se escribe ningún ajuste de TSF y que, si `homepage_title` está definido,
      ese valor gana (el plugin no lo pisa)
- [x] 2.7 No tocar `the_seo_framework_schema_graph_data` ni `the_seo_framework_sitemap_exclude_ids`
- [x] 2.8 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 3. RED/GREEN — guardia anti-falso-verde

- [x] 3.1 RED: prueba *un hook fuera de la lista rompe el test*: con un `the_seo_framework_*` de
      mentira registrado por el plugin, el test falla e identifica el hook
- [x] 3.2 GREEN: la guardia compara los hooks `the_seo_framework_*` registrados por el plugin con la
      lista verificada y pasa cuando todos pertenecen a ella
- [x] 3.3 Prueba *sin TSF instalado*: la guardia pasa en CI (sin TSF) usando la lista; el plugin carga
      sin error fatal
- [x] 3.4 Paso de **verificación en producción** documentado y ejecutable: comprobar que cada hook de
      la lista aparece en el código de TSF instalado (`apply_filters`) y reportar los que falten
- [x] 3.5 Evidencia RED/GREEN pegada literalmente de 3.1 y 3.2
- [x] 3.6 La lista contiene **exactamente** los cinco hooks verificados
      (`the_seo_framework_title_from_generation`, `the_seo_framework_generated_description`,
      `the_seo_framework_use_title_branding`, `the_seo_framework_schema_graph_data`,
      `the_seo_framework_sitemap_exclude_ids`), con su `fichero:línea`, y marca
      `the_seo_framework_title`/`the_seo_framework_description` como prohibidos

## 4. RED/GREEN — autor canónico

- [x] 4.1 RED: prueba *`/author/yyyxfsgj/` redirige*: el path heredado responde **301** con `Location`
      canónica
- [x] 4.2 RED: prueba *sin bucle*: la URL canónica responde 200 y no redirige a sí misma
- [x] 4.3 RED: prueba *paso de datos idempotente*: la segunda ejecución no modifica `user_nicename`
- [x] 4.4 GREEN: subcomando WP-CLI del paso de datos que fija `user_nicename = atareao` para el autor 3,
      no-op si ya lo es, y reporta el resultado
- [x] 4.5 GREEN: redirección en `template_redirect` que detecta el **path** heredado (la rewrite lo
      resuelve a 404) y emite un único 301 hacia `get_author_posts_url()` del autor
- [x] 4.6 Confirmar que la redirección no afecta a ningún otro nicename y que
      `ensureAuthorArchive()` no es la solución (documentarlo)
- [x] 4.7 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 5. RED/GREEN — `itunes:explicit` conforme a Apple

- [x] 5.1 RED: prueba *por defecto*: sin ajuste, `itunes:explicit` es `false`
- [x] 5.2 RED: prueba *normalización*: `yes`→`true`; `no`→`false`; `clean`→`false`; otro→`false`; nunca
      `no`/`yes`/`clean`/vacío
- [x] 5.3 RED: prueba *`/feed/` principal sin el tag*
- [x] 5.4 GREEN: normalizar el valor en `class-podcast-feed.php:361-373` y cambiar el default del
      ajuste a `false`
- [x] 5.5 Actualizar el select de `includes/class-theme-options.php` y el README del plugin
      (documentar la compatibilidad de valores heredados)
- [x] 5.6 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 6. REFACTOR — limpieza y estilo

- [x] 6.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [x] 6.2 Reutilizar `limitTitle()` y las tablas de constantes existentes; sin literales sueltos
- [x] 6.3 Re-ejecutar `just test` y `just php-lint` tras el refactor, pegando la evidencia

## 7. Documentación

- [x] 7.1 `docs/t10-residual-fixes.md`: qué es, ficheros implicados, cómo funciona (filtros reales,
      guardia, 301 del autor, `itunes:explicit`), variables/config, despliegue, decisiones y follow-ups
- [x] 7.2 Actualizar el README del plugin con el contrato nuevo de `itunes:explicit` y el comando del
      paso de datos del autor

## 8. Integración y cierre

- [ ] 8.1 CI en verde: job `lint` (incluye los arneses PHP) con la guardia nueva
- [ ] 8.2 PR `feature/t10-residual-fixes` → `development` con CI `lint` en verde (sin merge directo)
- [ ] 8.3 Release y despliegue según `GIT_FLOW.md` y `release-pipeline`
- [ ] 8.4 **Verificación en producción** (tras el despliegue):
      - [ ] 8.4.1 Portada `<title>` ≤60; `/blog/` y los cuatro listados con título y descripción
            curados (120–160); `og:`/`twitter:` idénticos a `meta description`
      - [ ] 8.4.2 Sufijo de marca: las superficies cubiertas **no** lo llevan; un post y un episodio
            **sí** lo conservan; el ajuste global `title_rem_additions` sigue siendo `0`
      - [ ] 8.4.3 Ajustes de TSF: si `homepage_title` está definido, ese valor gana y el plugin no lo
            pisa; ningún ajuste de TSF ha sido escrito por el plugin
      - [ ] 8.4.4 `/author/atareao/` → 200; `/author/yyyxfsgj/` → 301 en un solo salto, sin bucle
      - [ ] 8.4.5 W3C Feed Validator sobre `/podcast/feed/`: sin el error de `itunes:explicit`;
            `/feed/` sin el tag
      - [ ] 8.4.6 Paso de datos ejecutado (idempotente) y guardia de hooks contra el TSF instalado
      - [ ] 8.4.7 No regresión: post, tutorial, episodio, `/feed/`, sitemap y admin sin cambios
- [ ] 8.5 Auditoría del diff (corrección, seguridad, contrato de los filtros)
- [ ] 8.6 `openspec archive 2026-11-20-t10-residual-fixes` tras el merge y verificar que las specs
      destino `openspec/specs/seo/` y `openspec/specs/podcast-feed/` quedan actualizadas

## Evidencia CLI (2026-11-20, ejecutada en esta sesión)

> Todos los comandos se ejecutaron en `/tmp/opencode/rel-1230` con `just` (contenedor `php:8.3-cli`).
> La evidencia remota es de **solo lectura**: `grep` sobre el código de TSF, `wp option get`,
> `wp user get` y `curl` de los feeds. No se ha escrito nada en producción.

### §0.1 — Hooks muertos (no existen) y hooks reales

```text
$ ssh atareao 'docker exec wpcli sh -c "cd /var/www/html/wp-content/plugins/autodescription \
    && find . -name \"*.php\" -type f \
    | xargs grep -n -E \"apply_filters\\( *.[\\x27\\\"]?(the_seo_framework_title|the_seo_framework_description)[\\x27\\\"]? *[,)\" | head -20"'
EXIT_DEAD_OK          # 0 coincidencias

$ ssh atareao 'docker exec wpcli sh -c "cd /var/www/html/wp-content/plugins/autodescription \
    && find . -name \"*.php\" -type f \
    | xargs grep -n -E \"the_seo_framework_(title_from_generation|generated_description|use_title_branding|schema_graph_data)\"'
./inc/classes/meta/description.class.php:178:			'the_seo_framework_generated_description',
./inc/classes/meta/title.class.php:238:			'the_seo_framework_title_from_generation',
./inc/classes/meta/title/conditions.class.php:175:			'the_seo_framework_use_title_branding',
./inc/classes/meta/schema.class.php:143:			'the_seo_framework_schema_graph_data',
./inc/classes/sitemap/utils.class.php:97:	$excluded = (array) \apply_filters( 'the_seo_framework_sitemap_exclude_ids', [] );

$ ssh atareao 'docker exec wpcli wp plugin list'
autodescription	active	none	5.2.1
$ grep -m1 "Version:" autodescription.php
autodescription.php:19: * Version: 5.2.1
```

### §0.2 / §0.6 — Lista verificada y evidencia H1/H2/H3

`tests/t10-residuals/verified-hooks.php` contiene los cinco hooks con `fichero:línea` y los dos
prohibidos (coinciden con el `grep` anterior: `238`, `178`, `175`, `143`, `97`). H1 (la ruta
auto-determinada llama con `$args = null`) está documentada en el docblock de
`SEO::filterGeneratedTitle()` (`class-seo.php:236-253`); H2 (`use_branding()` decide el sufijo y
aplica `the_seo_framework_use_title_branding`) en el de `filterUseTitleBranding()`; H3 (los ajustes
de portada de TSF ganan) en el docblock y en `docs/t10-residual-fixes.md`.

### §0.3 — Datos del autor 3 y del resto de usuarios

```text
$ ssh atareao 'docker exec wpcli wp user get 3 --fields=ID,user_login,user_nicename,display_name,user_url --format=table'
Field	Value
ID	3
user_login	yyyxfsgj
user_nicename	yyyxfsgj
user_url	https://atareao.es
display_name	atareao

$ ssh atareao 'docker exec wpcli wp user list --fields=ID,user_login,user_nicename --format=table'
ID	user_login	user_nicename
7	admin	admin
8	openclaw	openclaw
5	Superlewis	superlewis
3	yyyxfsgj	yyyxfsgj
```

Ningún usuario ocupa `atareao`: el paso de datos no encontrará conflicto en producción.

### §0.4 — `itunes:explicit` en producción y mensaje literal del validador

```text
$ curl -s https://atareao.es/podcast/feed/ | grep -o "<itunes:explicit>[^<]*</itunes:explicit>"
<itunes:explicit>no</itunes:explicit>

$ curl -s https://atareao.es/feed/ | grep -c "itunes:explicit"
0

W3C Feed Validator (https://validator.w3.org/feed/check.cgi?url=https%3A%2F%2Fatareao.es%2Fpodcast%2Ffeed%2F):
Sorry

This feed does not validate.

line 32, column 19: itunes:explicit must be "true" or "false": no [help]
<itunes:explicit>no</itunes:explicit>
                   ^
```

### §0.5 — Ajuste `atareao_podcast_explicit` en producción

```text
$ ssh atareao 'docker exec wpcli wp option get atareao_podcast_explicit'
Error: Could not get 'atareao_podcast_explicit' option. Does it exist?
```

El ajuste **no está definido** en producción: se sirve el valor por defecto del código. Por eso el
cambio de default (`no` → `false`) tiene efecto sin ninguna migración de datos. Está registrado en
`includes/class-theme-options.php:81-89` (con `default => 'false'`) y documentado en el README del
plugin.

### §1 / §2 — Arnés de los filtros reales de TSF (GREEN)

```text
$ just php tests/t10-residuals/test_seo_tsf_integration.php; echo "exit=$?"
...
Resumen: 117 PASS, 0 FAIL
exit=0
```

Control negativo de RED (reproducido en esta sesión: se sustituyen temporalmente los tres filtros
reales por los dos hooks muertos y se restaura el fichero):

```text
  FAIL: el plugin registra the_seo_framework_title_from_generation (Expected: true, got: false)
  FAIL: el plugin registra the_seo_framework_generated_description (Expected: true, got: false)
  FAIL: el plugin registra the_seo_framework_use_title_branding (Expected: true, got: false)
  FAIL: el plugin no registra el hook muerto the_seo_framework_title (Expected: true, got: false)
  FAIL: el plugin no registra el hook muerto the_seo_framework_description (Expected: true, got: false)
  FAIL: todos los hooks de TSF registrados están en la lista (Expected: array (
Resumen: 103 PASS, 8 FAIL
error: recipe `php` failed with exit code 1
```

El RED original (arnés escrito antes de la implementación) no se conservó como fichero de log: se
perdió al cortarse la sesión anterior. Lo que queda arriba es el control negativo reproducible, no
aquel log.

### §3 — Guardia anti-falso-verde

Control negativo (se registra `the_seo_framework_bogus_hook` en `SEO::init()` y se restaura):

```text
--- Scenario: la guardia de hooks no deja pasar un hook inexistente ---
  FAIL: todos los hooks de TSF registrados están en la lista (Expected: array (
), got: array (
  0 => 'the_seo_framework_bogus_hook',
))
  PASS: la guardia identifica el hook fuera de la lista
  FAIL: la guardia identifica el hook prohibido (Expected: array (
  0 => 'the_seo_framework_title',
), got: array (
  0 => 'the_seo_framework_bogus_hook',
  1 => 'the_seo_framework_title',
))
Resumen: 115 PASS, 2 FAIL
```

Verificación contra el TSF **instalado** (copia de solo lectura extraída del contenedor de
producción; `scripts/verify-tsf-hooks.php` + receta `just verify-tsf-hooks`):

```text
$ ssh atareao 'docker exec wpcli tar -C /var/www/html/wp-content/plugins/autodescription \
    -cf - inc autodescription.php' | tar -x -C /tmp/opencode/tsf-5.2.1
$ just verify-tsf-hooks /tmp/opencode/tsf-5.2.1; echo "exit=$?"
TSF esperado: 5.2.1
Directorio: /tsf
  OK   the_seo_framework_title_from_generation
  OK   the_seo_framework_generated_description
  OK   the_seo_framework_use_title_branding
  OK   the_seo_framework_schema_graph_data
  OK   the_seo_framework_sitemap_exclude_ids

5/5 hooks verificados.
exit=0
```

### §4 — Autor canónico (GREEN)

```text
$ just php tests/t10-residuals/test_author_canonical.php; echo "exit=$?"
...
  PASS: se registra el subcomando `wp atareao-author canonicalize`
  PASS: el subcomando respeta --dry-run
  PASS: el subcomando escribe el nicename
  PASS: el subcomando reporta el resultado

--- Scenario: el nicename heredado redirige con un único 301 ---
  PASS: SEO::init() registra redirectLegacyAuthorNicename en template_redirect
  PASS: la petición heredada termina tras redirigir
  PASS: el nicename heredado redirige a la URL canónica
  PASS: la redirección es un 301
  PASS: la redirección también se aplica con query string
  PASS: con query string sigue siendo un 301 único

--- Scenario: la redirección no entra en bucle ---
  PASS: la URL canónica no se redirige a sí misma
  PASS: la URL canónica no emite redirección
  PASS: la URL canónica se sirve sin salir por redirección

--- Scenario: la redirección no afecta a otros nicenames ---
  PASS: otro nicename no se redirige
  PASS: una ruta normal no se redirige

--- Scenario: la rewrite de autor no es la solución ---
  PASS: registrar la rewrite no cambia el nicename: el archivo canónico sigue siendo el heredado

Resumen: 30 PASS, 0 FAIL
exit=0
```

Control negativo (se retira el `add_action('template_redirect', …)` y se restaura):

```text
  FAIL: SEO::init() registra redirectLegacyAuthorNicename en template_redirect (Expected: true, got: false)
Resumen: 29 PASS, 1 FAIL
error: recipe `php` failed with exit code 1
```

> La primera pasada de este control **no** falló (29 PASS, exit 0): el arnés ejercitaba el método a
> mano y no comprobaba que `SEO::init()` lo registrara. Se añadió esa aserción (el falso verde que
> originó el change era exactamente eso) y, con ella, el control negativo ya rompe el arnés.

### §5 — `itunes:explicit` (GREEN)

```text
$ just php tests/t10-residuals/test_feed_explicit.php; echo "exit=$?"
...
Resumen: 18 PASS, 0 FAIL
exit=0
```

Control negativo (default `no` y sin normalización; restaurado después):

```text
  FAIL: sin ajuste, itunes:explicit vale false (Expected: 'false', got: 'no')
  FAIL: el canal emite el elemento itunes:explicit (Expected: true, got: false)
  FAIL: un ajuste vacío se normaliza a false (Expected: 'false', got: 'no')
  FAIL: el valor heredado «yes» se emite como true (Expected: 'true', got: 'yes')
  FAIL: el valor heredado «yes» nunca se emite literal (Expected: true, got: false)
  FAIL: el valor heredado «no» se emite como false (Expected: 'false', got: 'no')
Resumen: 7 PASS, 11 FAIL
error: recipe `php` failed with exit code 1
```

Salida real del canal en los arneses del proyecto (`<itunes:explicit>false</itunes:explicit>`), y el
`<select>` de la pestaña *Tema* ya ofrece solo `false`/`true`, mapeando `yes`→`true` y
`no`/`clean`→`false` (`class-theme-options.php:393-424`).

### §2.8 / §4.7 / §5.6 / §6.3 — `just test`, `just php-lint`, `just phpcs`

```text
$ just test; echo "exit=$?"
Total passed: 1295, Total failed: 1
FAILED TESTS:
  - (reg) version coincide con la del plugin (Expected: '1.24.0', got: '1.22.0')   # preexistente, issue #192
Resumen: 39 PASS, 0 FAIL    # podcast-feed-soberania (feed completo)
Resumen: 26 PASS, 0 FAIL    # podcast-feed-soberania (guid mapping)
Resumen: 16 PASS, 0 FAIL    # podcast-guid-parity (emisión)
Resumen: 78 PASS, 0 FAIL    # podcast-guid-parity (importador)
Resumen: 117 PASS, 0 FAIL   # t10 — filtros de TSF
Resumen: 18 PASS, 0 FAIL    # t10 — itunes:explicit
Resumen: 30 PASS, 0 FAIL    # t10 — autor canónico
exit=1                      # solo por el fallo preexistente de `server.json`

$ just php-lint; echo "exit=$?"
70 × "No syntax errors detected in ..."    # theme + plugin
exit=0

$ just php-lint-changed; echo "exit=$?"
No syntax errors detected in tests/t10-residuals/assertions.php
No syntax errors detected in tests/t10-residuals/test_author_canonical.php
No syntax errors detected in tests/t10-residuals/test_feed_explicit.php
No syntax errors detected in tests/t10-residuals/test_seo_tsf_integration.php
No syntax errors detected in tests/t10-residuals/verified-hooks.php
No syntax errors detected in tests/test-valet.php
No syntax errors detected in tests/wp-stubs.php
No syntax errors detected in wp-content/plugins/atareao-functionality/atareao-functionality.php
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-author-canonical.php
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-podcast-feed.php
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-seo.php
No syntax errors detected in wp-content/plugins/atareao-functionality/includes/class-theme-options.php
exit=0
```

`just phpcs` (PSR12) sobre los cuatro ficheros tocados del plugin: **0 errores**. Avisos:

| Fichero | Avisos ahora | Avisos en `HEAD` |
|---|---|---|
| `class-seo.php` | 8 (líneas largas 48-70 + side effects) | 8 (idénticos) |
| `class-podcast-feed.php` | 1 (side effects) | 1 (idéntico) |
| `class-theme-options.php` | 12 | 12 (idénticos) |
| `class-author-canonical.php` | 1 (side effects, igual que el resto de módulos) | fichero nuevo |

El único aviso nuevo es el patrón «side effects» que ya tienen **todos** los ficheros del plugin
(`if (!defined('ABSPATH')) exit;`): no se introduce ningún aviso de longitud ni de estilo.

### §7 — Documentación

`docs/t10-residual-fixes.md` (nuevo) y `wp-content/plugins/atareao-functionality/README.md`
(«Metadatos SEO (The SEO Framework)», fila de `atareao_podcast_explicit` y comandos `wp
atareao-author canonicalize` y `just verify-tsf-hooks`).

### §8.1 — CI (comando verificado sin `just` ni Docker)

```text
$ podman run --rm -v "$PWD:/workspace:Z" -w /workspace --entrypoint sh docker.io/library/php:8.3-cli \
    -c 'php tests/t10-residuals/test_seo_tsf_integration.php ...'
seo exit=0 Resumen: 117 PASS, 0 FAIL
feed exit=0 Resumen: 18 PASS, 0 FAIL
author exit=0 Resumen: 30 PASS, 0 FAIL
```

Los tres arneses están añadidos al paso `Test PHP harnesses` del job `lint`
(`.github/workflows/ci.yml`). El job en sí queda **sin verificar** hasta que se abra el PR.

## Estado de verificación (esta sesión)

- **GREEN completo de §0–§7 (2026-11-20).** Los tres arneses de `tests/t10-residuals/` en verde
  (117 + 18 + 30 PASS, 0 FAIL), `just test` con **un único fallo preexistente** (`server.json`
  `1.22.0` frente al plugin `1.24.0`, issue #192), `just php-lint` en verde y `just phpcs` sin
  errores en los ficheros tocados.
- **Evidencia remota de §0 obtenida en solo lectura**: hooks muertos = 0 coincidencias en TSF 5.2.1;
  los cinco verificados con `fichero:línea`; autor 3 con `user_nicename = yyyxfsgj` y ningún usuario
  ocupando `atareao`; `itunes:explicit>no<` en el feed de producción y el mensaje literal del W3C
  Feed Validator; `atareao_podcast_explicit` **sin definir** en producción (manda el default del
  código).
- **Cambio de cobertura hecho en esta sesión:** el arnés del autor ejercitaba
  `SEO::redirectLegacyAuthorNicename()` a mano y **no** comprobaba que `SEO::init()` lo registrara;
  se añadió esa aserción (control negativo verificado: sin el `add_action` el arnés falla).
- Correcciones de la auditoría aplicadas a la spec: (H1) eliminada la cláusula «identidad con `$args`
  `null`» —la ruta auto-determinada de la portada llega con `$args = null`—; (H2) añadido el filtro
  real `the_seo_framework_use_title_branding` para el sufijo de marca; (H3) añadido el respeto a los
  ajustes de TSF de la portada; (H4) fijada la lista verificada de cinco hooks y los dos prohibidos.
- `openspec validate 2026-11-20-t10-residual-fixes --strict` → **en verde** (revalidado tras esta
  sesión).
- **Sin marcar**: §8.1 (el job `lint` solo se comprueba al abrir el PR), §8.2 (PR pendiente), §8.3 y
  §8.4 (dependen de despliegue) y §8.6 (archivado tras el merge). Ninguna escritura en producción en
  esta sesión.
