# Proposal: Redirección alcanzable de `/sitemap_index.xml`

## Why

El change `seo-visibility` añadió `SEO::redirectSitemapIndex()` (301 de `/sitemap_index.xml` →
`/sitemap.xml`) enganchado en **`template_redirect`** (prioridad 1). En producción **no dispara**:
`/sitemap_index.xml` responde **200** con contenido **idéntico** (mismo md5) al de `/sitemap.xml`, y
`just gsc sitemaps` los marca como **duplicados**.

**Causa raíz (verificada en TSF 5.2.1):** The SEO Framework despacha su endpoint `sitemap_index.xml`
desde `Sitemap\Registry::_init`, enganchado en **`parse_request` prioridad 15**
(`bootstrap/init-front.php:44`), y hace `exit` antes de que corra `template_redirect`. El 301 nunca se
alcanza. El arnés actual daba verde porque solo probaba la función pura (`sitemapRedirectTarget`), no
el orden de hooks — otro falso verde del mismo tipo que documenta `verified-hooks.php`.

## What Changes

1. El 301 de `/sitemap_index.xml` pasa a engancharse en **`parse_request` con prioridad anterior a 15**
   (se usa 1), de modo que se ejecute **antes** del despacho de TSF. Se mantiene `sitemapRedirectTarget`
   como única fuente de la decisión.
2. **Guardia de orden** (anti-falso-verde): un test comprueba que el callback se registra en
   `parse_request` con prioridad `< 15`, con la referencia `bootstrap/init-front.php:44` de TSF 5.2.1
   anotada como el hecho que lo justifica.
3. **No** cambia el contrato del sitemap (mismo contenido en `/sitemap.xml`), ni `robots.txt`, ni el
   resto de hooks.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `seo`: se **añade** un requirement sobre la redirección alcanzable de `/sitemap_index.xml` y su
  guardia de orden.

## Impact

- **Código:** `wp-content/plugins/atareao-functionality/includes/class-seo.php` (hook de
  `redirectSitemapIndex`).
- **Tests:** `tests/test-valet.php` (escenario «Sitemap coherente»: guardia de orden) y/o el paso de
  verificación en producción con TSF instalado.
- **Spec:** `openspec/specs/seo/spec.md`.
- **Verificación en producción:** `curl -I https://atareao.es/sitemap_index.xml` → **301** a
  `/sitemap.xml`; `/sitemap.xml` sigue **200** sin cambios.
