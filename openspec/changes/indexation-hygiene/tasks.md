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

- [ ] 4.1 **RED** — Escribir el escenario del filtro `robots_txt` que exige `Disallow: /*?replytocom=`
      y `Disallow: /search/` sin perder ni duplicar las directivas preexistentes. Verificar:
      `just test` → nuevo en ROJO.
- [ ] 4.2 **GREEN** — Añadir el callback idempotente al filtro `robots_txt` (sin tocar el de
      `Agentmap:`). Verificar: `just test` y `just php-lint` en verde.
- [ ] 4.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección `robots.txt` (directivas y origen virtual).

## 5. Mapa `301` (legacy-redirects) — TDD

- [ ] 5.1 **RED** — Escribir los escenarios: familias `/tutorial/opencode/*`, `/instalar/*`, `/amp/`,
      ausencia de bucle y `404` sin equivalente que sigue siendo `404`. Verificar: `just test` →
      nuevos en ROJO.
- [ ] 5.2 **GREEN** — Implementar el mapa mínimo en el plugin, antes del `404`. Verificar: `just test`
      y `just php-lint` en verde.
- [ ] 5.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección del mapa `301`.

## 6. Meta `robots` única (robots-meta) — TDD

- [ ] 6.1 **RED** — Escribir los escenarios de `robots-meta` (una sola etiqueta en `?replytocom=`,
      en `/search/` y como máximo una en un post). Verificar: `just test` → nuevos en ROJO.
- [ ] 6.2 **GREEN** — Unificar el emisor (el mismo de D1), eliminando la etiqueta duplicada.
      Verificar: `just test` y `just php-lint` en verde.
- [ ] 6.3 **REFACTOR** y documentación. Verificar: `just phpcs` (0 errores nuevos), `just test` sin
      regresiones y `docs/indexacion.md` con la sección de la meta única.

## 7. Deploy y verificación en producción

- [ ] 7.1 Desplegar el plugin en producción y purgar la caché si aplica. Verificar: el commit
      desplegado es el del change y las peticiones sirven el código nuevo.
- [ ] 7.2 Verificación integral sobre el HTML servido (todas las capabilities). Verificar con `curl`
      cache-busting: `/search/<q>/` y `/search/<q>/page/N/` → `noindex`; `/software/`,
      `/software/page/3/`, `/ubuntu/`, `/como/` → `noindex`; `/blog/` y `/tutoriales/` → sin
      `noindex`; `/robots.txt` → las dos `Disallow` + preexistentes; familias `/tutorial/opencode/*`,
      `/instalar/*`, `/amp/` → `301` y un `404` con equivalente → `301`; `?replytocom=` → una sola
      meta `robots`.
- [ ] 7.3 Cerrar `docs/indexacion.md` (diagnóstico, cambios, fuente de verdad de D1 y resultados de la
      verificación) y marcar las tareas hechas. Verificar: el documento cubre las cinco capabilities y
      `just check-spec` pasa.

## 8. Re-medición y archivo

- [ ] 8.1 A 28 días vista del deploy, ejecutar `just gsc compara --days 28` y registrar la
      comparación por página × query en `docs/indexacion.md`, con foco en las familias `noindex` y en
      las impresiones por categoría. Verificar: el informe se genera (exit 0) y queda registrado.
- [ ] 8.2 Archivar el change con `openspec archive indexation-hygiene` una vez `openspec validate
      indexation-hygiene` pasa. Verificar: las cinco capabilities aparecen en `openspec/specs/` y
      `just check-spec` queda en verde.
