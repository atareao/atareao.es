# Tasks

> Referencias: requisitos en `specs/*/spec.md`, decisiones en `design.md`. Arnés: `just test`
> (procedural, `tests/test-valet.php`), `just php-lint`, `just phpcs`, `just check-spec`.
> **Bloqueante de arranque:** la decisión **D1** (fuente de verdad del `noindex`) necesita aprobación
> del usuario antes del bloque 2.

## 1. Preparación y contratos

- [x] 1.1 Resolver **D1**: verificar contra el TSF 5.2.1 instalado en producción si existe un filtro
      real de `robots` (leer el código del plugin en el contenedor) y, si existe, registrarlo en
      `tests/t10-residuals/verified-hooks.php` con su `fichero:línea`; si no existe, decidir D1-C.
      Verificar: `just test` (guardia `tsf_hook_violations`) en verde **sin** TSF instalado; el origen
      del emisor documentado en `design.md`/`docs/indexacion.md`.
- [x] 1.2 Confirmar el origen del `robots.txt` (virtual, filtro `robots_txt`, consumidor en
      `class-mcp-discovery.php:98`) y volcar la línea base de directivas servidas.
      Verificar: `curl -s https://atareao.es/robots.txt` contiene `Disallow: /wp-admin/`,
      `Allow: /wp-admin/admin-ajax.php`, `Sitemap:` y `Agentmap:` (comparar con la spec `crawl-policy`).
- [x] 1.3 Confirmar el patrón de `301` existente y el punto de enganche para el mapa nuevo.
      Verificar: `rg -n "redirectSitemapIndex|redirectLegacyAuthorNicename|wp_safe_redirect" wp-content/plugins/atareao-functionality/includes/class-seo.php`
      y `just test` en verde como base.
- [x] 1.4 Confirmar en producción las superficies y familias objetivo (códigos HTTP y estado
      indexable de partida). Verificar: `curl -sI` a `/search/linux/`, `/search/linux/page/5/`,
      `/software/`, `/software/page/3/`, `/ubuntu/`, `/como/`, `/amp/<post>/`,
      `/tutorial/opencode/<x>/`, `/instalar/<x>/`; registrar códigos y `robots` de partida.

## 2. Búsquedas (search-indexing) — TDD

- [x] 2.1 **RED** — Escribir en `tests/test-valet.php` los escenarios de `search-indexing` (con
      resultados, sin resultados, paginación profunda, sin término, una sola etiqueta). Verificar:
      `just test` → los nuevos en ROJO y el resto en VERDE.
- [x] 2.2 **GREEN** — Implementar el `noindex` de `/search/` por el mecanismo aprobado en D1.
      Verificar: `just test` y `just php-lint` en verde.
- [x] 2.3 **REFACTOR** — Limpiar el callback sin cambiar comportamiento. Verificar: `just phpcs` sobre
      los ficheros tocados (0 errores nuevos) y `just test` sin regresiones.
- [x] 2.4 Documentar en `docs/indexacion.md` la sección de búsquedas (comportamiento y mecanismo).
      Verificar: el documento describe el `/search/` final y el emisor usado.

## 3. Categorías (category-archives) — TDD

- [x] 3.1 **RED** — Escribir los escenarios de `category-archives` (término, familia medida,
      paginación, listados indexables intactos, una sola etiqueta). Verificar: `just test` → nuevos en
      ROJO, resto VERDE.
- [x] 3.2 **GREEN** — Implementar el `noindex` de los archivos de categoría y de las familias medidas
      (`/software/`, `/ubuntu/`, `/como/`, `/page/N/`), sin tocar `/blog/`, `/tutoriales/`,
      `/podcast/`, `/aplicaciones/`. Verificar: `just test` y `just php-lint` en verde.
- [x] 3.3 **REFACTOR** — Consolidar la condición de archivo en un único predicado. Verificar:
      `just phpcs` (0 errores nuevos) y `just test` sin regresiones.
- [x] 3.4 Documentar en `docs/indexacion.md` la sección de categorías, incluida la decisión D3 de
      incluir `/software/` (archivo de CPT) y excluir los hubs indexables. Verificar: el documento
      refleja el comportamiento final.

## 4. `robots.txt` (crawl-policy) — TDD

- [x] 4.1 **RED** — Escribir el escenario del filtro `robots_txt` que exige `Disallow: /*?replytocom=`
      y `Disallow: /search/` sin perder ni duplicar las directivas preexistentes. Verificar:
      `just test` → nuevo en ROJO.
- [x] 4.2 **GREEN** — Añadir el callback idempotente al filtro `robots_txt` (sin tocar el de
      `Agentmap:`). Verificar: `just test` y `just php-lint` en verde.
- [x] 4.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección `robots.txt` (directivas y origen virtual).

## 5. Mapa `301` (legacy-redirects) — TDD

- [x] 5.1 **RED** — Escribir los escenarios: familias de prefijo (`/pyldora/*`, `/pildoras`,
      `/podcat/*`), hub `/tutorial/opencode/` y equivalentes 1:1, ausencia de bucle, rutas sin
      equivalente (`/instalar/*`) que siguen siendo `404`. Verificar: `just test` → nuevos en ROJO.
- [x] 5.2 **GREEN** — Implementar el mapa mínimo en el plugin, antes del `404`. Verificar: `just test`
      y `just php-lint` en verde.
- [x] 5.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección del mapa `301`.

## 6. Meta `robots` única (robots-meta) — TDD

- [x] 6.1 **RED** — Escribir los escenarios de `robots-meta` (una sola etiqueta en `?replytocom=`,
      en `/search/` y como máximo una en un post). Verificar: `just test` → nuevos en ROJO.
- [x] 6.2 **GREEN** — Unificar el emisor (el mismo de D1), eliminando la etiqueta duplicada.
      Verificar: `just test` y `just php-lint` en verde.
- [x] 6.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección de la meta única.
- [x] 6.4 **Corrección de auditoría** (Hallazgos 1, 3 y 5) manteniendo el emisor único TSF.
      **RED:** endurecer el doble de `get_query_var()` a la lista blanca de query vars públicos (para
      que `replytocom` no cuele), hacer que el doble de `wp_robots()` ejecute de verdad la cadena de
      filtros del núcleo y contar las etiquetas **renderizando** `wp_head` (no por `has_action`);
      añadir escenarios de `?unapproved=`+`moderation-hash`, embed, `blog_public` off y un control
      negativo. **GREEN:** detectar `?replytocom=` por `$_GET` (Hallazgo 3), conservar el emisor de
      core cuando el sitio no es público y reinyectar el `noindex` de embeds (Hallazgo 1).
      **REFACTOR:** corregir la afirmación falsa de `docs/indexacion.md` («la misma fuente que consulta
      el núcleo») y documentar la decisión. Verificar: `just test` (RED → GREEN), `just phpcs` (0
      errores nuevos) y `just check-spec` en verde.

## 7. Deploy y verificación en producción

- [x] 7.1 Desplegar el plugin en producción y purgar la caché si aplica. Verificar: el commit
      desplegado es el del change y las peticiones sirven el código nuevo. — **2026-11-22:** deploy por
      ficheros con `sha256` verificado y `owner 82:82` (`class-seo.php`
      `8fd251bbf9a0796c6327ac415a644e1b1cf57c451c5e0e6888f8170ebb53b027`,
      `atareao-functionality.php` `c2301f64bfdac4eec89ab72ea429ac1f4f6b4794977271d59067268385590443`);
      versión servida **1.33.1** (antes 1.31.0).
- [x] 7.2 Verificación integral sobre el HTML servido (todas las capabilities). Verificar con `curl`
      cache-busting: `/search/<q>/` y `/search/<q>/page/N/` → `noindex`; `/software/`,
      `/software/page/3/`, `/ubuntu/`, `/como/` → `noindex`; `/blog/` y `/tutoriales/` → sin
      `noindex`; `/robots.txt` → las dos `Disallow` + preexistentes; familias `/tutorial/opencode/*`,
      `/instalar/*`, `/amp/` → `301` y un `404` con equivalente → `301`; `?replytocom=` → una sola
      meta `robots`. — **2026-11-22:** verificado sobre HTML servido real, con **una sola** etiqueta
      `robots` por página: `noindex` en `/search/docker/`, `/category/ubuntu/` y `/software/` (más sus
      paginaciones) y en `?replytocom=1`; sin `noindex` en `/`, `/tutorial/docker/`, `/podcast/`,
      `/tutoriales/`, `/blog/` y `/aplicaciones/`; `robots.txt` con `Disallow: /*?replytocom=` y
      `Disallow: /search/`; 301 de `/docker/`→`/tutorial/docker/`, `/pyldoras`→`/tutorial/pyldoras/`,
      `/telegram`→`/software/telegram/` y `/podcat/*`→`/podcast/`; `/instalar/*` sigue 404.
- [x] 7.3 Cerrar `docs/indexacion.md` (diagnóstico, cambios, fuente de verdad de D1 y resultados de la
      verificación) y marcar las tareas hechas. Verificar: el documento cubre las cinco capabilities y
      `just check-spec` pasa. — **2026-11-22:** purga de caché de **12 bases + paginaciones**
      (categorías `/category/*` y `/software/`, hasta `page/6`) con `X-Cache-Purge`, verificada `HIT`
      con contenido nuevo; `just test` **1453 PASS / 0 FAIL** y `just php-lint` OK.
- [x] 7.4 **Corrección post-deploy** — la verificación detectó que TSF 5.2.1 normaliza el array de
      robots **antes** de `apply_filters('the_seo_framework_robots_meta_array')`: `noindex => true`
      llegaba al `implode(',', array_filter(...))` y se servía `content="1,max-snippet:-1,..."`
      (ignorado por Google). Causa: orden normalización → filtro → `implode`. Fix: `'noindex' =>
      'noindex'` (contrato de los compat oficiales, p. ej. `_set_wc_noindex_defaults`); el arnés mide
      ahora el contrato de salida (aplica el filtro real, compone la cadena como TSF y rechaza el token
      `1` suelto). Commit `32b7224`, PR **#271**: RED reproducido con 24 FAIL del síntoma exacto de
      producción → GREEN `just test` 1453 PASS / 0 FAIL y `just php-lint` OK.

## 8. Re-medición y archivo

- [ ] 8.1 A 28 días vista del deploy, ejecutar `just gsc compara --days 28` y registrar la
      comparación por página × query en `docs/indexacion.md`, con foco en las familias `noindex` y en
      las impresiones por categoría. Verificar: el informe se genera (exit 0) y queda registrado.
- [ ] 8.2 Archivar el change con `openspec archive indexation-hygiene` una vez `openspec validate
      indexation-hygiene` pasa. Verificar: las cinco capabilities aparecen en `openspec/specs/` y
      `just check-spec` queda en verde.
