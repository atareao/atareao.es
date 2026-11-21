# Tasks: Redirección alcanzable de `/sitemap_index.xml`

## RED

- [x] Guardia de orden en `tests/test-valet.php` (escenario «Sitemap coherente»): el callback
      `redirectSitemapIndex` se registra en `parse_request` con prioridad **anterior a 15**. Confirmar
      que **falla** hoy (está en `template_redirect`).
- [ ] Reproducir en producción (read-only): `curl -I https://atareao.es/sitemap_index.xml` → hoy **200**.

## GREEN

- [x] Mover el `add_action` de `redirectSitemapIndex` de `template_redirect` a `parse_request`
      (prioridad 1), sin tocar `sitemapRedirectTarget`.
- [x] Todo verde: `just test`, `just php-lint`.

## REFACTOR

- [x] Añadir el hecho verificado de TSF (`bootstrap/init-front.php:44`) a la documentación de la
      guardia, en el estilo de `verified-hooks.php`.
- [x] Re-ejecutar la suite.

## VERIFICACIÓN EN PRODUCCIÓN

- [ ] Tras el deploy: `/sitemap_index.xml` → **301** a `/sitemap.xml`; `/sitemap.xml` → **200** sin
      cambios; `just gsc sitemaps` deja de marcar duplicado.

## ARCHIVE

- [ ] `openspec archive 2026-11-21-sitemap-index-redirect`.
