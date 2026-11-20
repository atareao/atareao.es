# Tasks: Residuos de T10 — filtros reales de TSF, autor canónico e `itunes:explicit`

> Estado: **PENDIENTE DE APROBACIÓN** (2026-11-20). Ninguna casilla se marca sin evidencia CLI pegada
> literalmente. Regla dura: RED → GREEN → REFACTOR.
>
> **Dependencia:** `seo-visibility` (no archivado, desplegado en v1.23.0) aporta las tablas
> `LISTING_TITLES`/`LISTING_DESCRIPTIONS` y el grafo JSON-LD; este change corrige su integración.
>
> Las casillas de **verificación sobre el HTML servido** y de **validador de feed** dependen de un
> despliegue que no forma parte de este change.

## 0. Contexto y decisiones previas

- [ ] 0.1 Confirmar que `the_seo_framework_title` y `the_seo_framework_description` **no existen** en
      TSF 5.2.1 y que los reales son `the_seo_framework_title_from_generation` y
      `the_seo_framework_generated_description`; pegar el `rg` sobre el plugin `autodescription`
- [ ] 0.2 Registrar la lista verificada de hooks de TSF (los que el plugin puede registrar) en el
      repositorio, junto al arnés, con la referencia del fichero y la línea donde TSF dispara cada uno
- [ ] 0.3 Confirmar el estado de datos del autor 3 (`user_login`, `user_nicename`, `display_name`) y la
      forma del 404 de `/author/atareao/` (rewrite sí registrada, consulta sin usuario que la resuelva)
- [ ] 0.4 Confirmar el rechazo del validador de W3C sobre `<itunes:explicit>no</itunes:explicit>` y
      pegar el mensaje literal
- [ ] 0.5 Registrar el ajuste `atareao_podcast_explicit` (valores `no|yes|clean`, default `no`) y su
      ubicación en `includes/class-theme-options.php` y el README del plugin
- [ ] 0.6 Registrar la evidencia de H1/H2/H3: `get_bare_generated_title( $args = null )` llama al
      filtro con `$args = null`; `use_branding()` decide el sufijo y aplica
      `the_seo_framework_use_title_branding`; y los ajustes de TSF de la portada
      (`homepage_title`/`homepage_tagline`/`title_rem_additions`/`title_separator`)

## 1. RED — contrato de los callbacks reales de TSF

- [ ] 1.1 Arnés (estilo del proyecto, `exit(0/1)`, dobles de `tests/wp-stubs.php`) para el callback de
      título: registrar `the_seo_framework_title_from_generation` y ejercitar la firma
      `( $title, ?array $args )`
- [ ] 1.2 Prueba *portada*: en `is_front_page()` devuelve el título curado y mide **≤60**
- [ ] 1.3 Prueba *listados*: en `/blog/` y en las cuatro plantillas devuelve el título curado de la
      superficie; ninguna termina en el sufijo de marca
- [ ] 1.4 Prueba *identidad*: en un post, un tutorial y un episodio devuelve el valor recibido **sin
      cambios**
- [ ] 1.5 Prueba *contextos ajenos*: con admin, AJAX, feed, REST, sitemap o `robots` activos devuelve
      el valor recibido sin cambios
- [ ] 1.6 Prueba *`$args` anulado*: con `$args = null` y con `$args` sin las claves `id`/`uid` no hay
      error y se devuelve el valor recibido
- [ ] 1.7 Arnés del callback de descripción: firma `( $desc, ?array $args, string $type )` y pruebas de
      portada/listados para `search`, `opengraph` y `twitter`
- [ ] 1.8 Prueba *identidad de descripción*: fuera de las superficies cubiertas devuelve el valor
      recibido sin cambios
- [ ] 1.9 Prueba *no se registran los hooks muertos*: el plugin no registra `the_seo_framework_title`
      ni `the_seo_framework_description`
- [ ] 1.10 Prueba *ruta auto-determinada*: con `$args = null` y petición = superficie cubierta
      (portada), el callback devuelve el título curado y **no** el valor recibido
- [ ] 1.11 Prueba *sufijo de marca*: `filterUseTitleBranding()` devuelve `false` en las superficies
      cubiertas e identidad en un post y un episodio (que conservan su marca)
- [ ] 1.12 Evidencia RED pegada literalmente: arnés nuevo con **exit 1** (y el resto del arnés en
      verde)

## 2. GREEN — integración por los filtros reales

- [ ] 2.1 `class-seo.php`: sustituir los dos `add_filter` muertos (`:107-108`) por
      `the_seo_framework_title_from_generation` (10, 2) y `the_seo_framework_generated_description`
      (10, 3)
- [ ] 2.2 Implementar `filterGeneratedTitle( $title, $args )` con **alcance estricto**: portada y
      listados cubiertos → título curado con `limitTitle()`; resto → `$title` intacto. Documentar en el
      docblock la firma de TSF y por qué el segundo argumento es anulable
- [ ] 2.3 Implementar `filterGeneratedDescription( $desc, $args, $type )`: portada y listados cubiertos
      → descripción curada para los tres `$type`; resto → `$desc` intacto
- [ ] 2.4 Garantizar la **identidad** en admin, AJAX, feeds, REST, sitemaps y `robots` (bail-out
      explícito antes de resolver la superficie)
- [ ] 2.5 Implementar el callback de sufijo de marca: registrar
      `the_seo_framework_use_title_branding` (10, 3) con `filterUseTitleBranding( $use, $args, $social )`
      devolviendo `false` **solo** en las superficies cubiertas e identidad en el resto; **sin** tocar
      el ajuste global `title_rem_additions`
- [ ] 2.6 Confirmar que no se escribe ningún ajuste de TSF y que, si `homepage_title` está definido,
      ese valor gana (el plugin no lo pisa)
- [ ] 2.7 No tocar `the_seo_framework_schema_graph_data` ni `the_seo_framework_sitemap_exclude_ids`
- [ ] 2.8 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 3. RED/GREEN — guardia anti-falso-verde

- [ ] 3.1 RED: prueba *un hook fuera de la lista rompe el test*: con un `the_seo_framework_*` de
      mentira registrado por el plugin, el test falla e identifica el hook
- [ ] 3.2 GREEN: la guardia compara los hooks `the_seo_framework_*` registrados por el plugin con la
      lista verificada y pasa cuando todos pertenecen a ella
- [ ] 3.3 Prueba *sin TSF instalado*: la guardia pasa en CI (sin TSF) usando la lista; el plugin carga
      sin error fatal
- [ ] 3.4 Paso de **verificación en producción** documentado y ejecutable: comprobar que cada hook de
      la lista aparece en el código de TSF instalado (`apply_filters`) y reportar los que falten
- [ ] 3.5 Evidencia RED/GREEN pegada literalmente de 3.1 y 3.2
- [ ] 3.6 La lista contiene **exactamente** los cinco hooks verificados
      (`the_seo_framework_title_from_generation`, `the_seo_framework_generated_description`,
      `the_seo_framework_use_title_branding`, `the_seo_framework_schema_graph_data`,
      `the_seo_framework_sitemap_exclude_ids`), con su `fichero:línea`, y marca
      `the_seo_framework_title`/`the_seo_framework_description` como prohibidos

## 4. RED/GREEN — autor canónico

- [ ] 4.1 RED: prueba *`/author/yyyxfsgj/` redirige*: el path heredado responde **301** con `Location`
      canónica
- [ ] 4.2 RED: prueba *sin bucle*: la URL canónica responde 200 y no redirige a sí misma
- [ ] 4.3 RED: prueba *paso de datos idempotente*: la segunda ejecución no modifica `user_nicename`
- [ ] 4.4 GREEN: subcomando WP-CLI del paso de datos que fija `user_nicename = atareao` para el autor 3,
      no-op si ya lo es, y reporta el resultado
- [ ] 4.5 GREEN: redirección en `template_redirect` que detecta el **path** heredado (la rewrite lo
      resuelve a 404) y emite un único 301 hacia `get_author_posts_url()` del autor
- [ ] 4.6 Confirmar que la redirección no afecta a ningún otro nicename y que
      `ensureAuthorArchive()` no es la solución (documentarlo)
- [ ] 4.7 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 5. RED/GREEN — `itunes:explicit` conforme a Apple

- [ ] 5.1 RED: prueba *por defecto*: sin ajuste, `itunes:explicit` es `false`
- [ ] 5.2 RED: prueba *normalización*: `yes`→`true`; `no`→`false`; `clean`→`false`; otro→`false`; nunca
      `no`/`yes`/`clean`/vacío
- [ ] 5.3 RED: prueba *`/feed/` principal sin el tag*
- [ ] 5.4 GREEN: normalizar el valor en `class-podcast-feed.php:361-373` y cambiar el default del
      ajuste a `false`
- [ ] 5.5 Actualizar el select de `includes/class-theme-options.php` y el README del plugin
      (documentar la compatibilidad de valores heredados)
- [ ] 5.6 Evidencia GREEN pegada literalmente: arnés (**exit 0**) + `just php-lint` + `just test`

## 6. REFACTOR — limpieza y estilo

- [ ] 6.1 `just phpcs` sobre los ficheros tocados: sin errores nuevos (los avisos preexistentes del
      theme/bloques no cuentan)
- [ ] 6.2 Reutilizar `limitTitle()` y las tablas de constantes existentes; sin literales sueltos
- [ ] 6.3 Re-ejecutar `just test` y `just php-lint` tras el refactor, pegando la evidencia

## 7. Documentación

- [ ] 7.1 `docs/t10-residual-fixes.md`: qué es, ficheros implicados, cómo funciona (filtros reales,
      guardia, 301 del autor, `itunes:explicit`), variables/config, despliegue, decisiones y follow-ups
- [ ] 7.2 Actualizar el README del plugin con el contrato nuevo de `itunes:explicit` y el comando del
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

## Estado de verificación (esta sesión)

- **Fase SDD únicamente (2026-11-20).** No se ha escrito código de implementación, ni tests, ni se ha
  tocado producción. Este `tasks.md` queda en **PENDIENTE DE APROBACIÓN**.
- Correcciones de la auditoría aplicadas a la spec: (H1) eliminada la cláusula «identidad con `$args`
  `null`» —la ruta auto-determinada de la portada llega con `$args = null`—; (H2) añadido el filtro
  real `the_seo_framework_use_title_branding` para el sufijo de marca; (H3) añadido el respeto a los
  ajustes de TSF de la portada; (H4) fijada la lista verificada de cinco hooks y los dos prohibidos.
- `openspec validate 2026-11-20-t10-residual-fixes --strict` → **en verde** (salida pegada en el
  reporte de la sesión).
- Sin despliegue ni verificación en producción: todas las casillas de §8.4 quedan sin marcar por
  dependencia de despliegue.
