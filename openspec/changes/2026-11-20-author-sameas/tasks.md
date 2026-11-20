# Tasks: Perfiles externos (`sameAs`) en la entidad `Person` del grafo

## 1. Arnés RED (contrato del delta)

- [ ] 1.1 Crear `tests/structured-data/test_person_sameas.php` —autocontenido, con contadores propios, `require_once dirname(__DIR__) . '/wp-stubs.php'`, carga de `class-seo.php` y `\Atareao\SEO::init()`— con los seis escenarios del delta: `Person` anidada en `WebSite.publisher` con `name`/`url` recibe `sameAs`; `Person` de primer nivel sigue recibiéndolo; referencia pura `{@id}` intacta; `@type`/`@id` y número de entidades invariantes; perfiles existentes no duplicados; sin opciones `atareao_social_*` no se añade `sameAs`. Verificación: `podman run --rm -v "$PWD:/workspace:Z" -w /workspace --entrypoint php docker.io/wordpress:cli-php8.3 tests/structured-data/test_person_sameas.php` **falla con exit 1** (escenarios en RED).
- [ ] 1.2 Añadir el arnés a la receta `test-t10` de `.justfile` y al paso «Test PHP harnesses» de `.github/workflows/ci.yml`. Verificación: `rg -n 'test_person_sameas' .justfile .github/workflows/ci.yml` lo muestra en ambos.

## 2. Implementación (GREEN)

- [ ] 2.1 Reescribir `enrichPersonProfiles()` en `wp-content/plugins/atareao-functionality/includes/class-seo.php` (línea 948) para recorrer el grafo de forma recursiva y enriquecer toda entidad `Person` que declare `name` o `url`, fusionando `sameAs` con `mergeProfiles()` y sin tocar referencias puras. Verificación: el arnés de 1.1 pasa (exit 0) y `just php-lint` sin errores.
- [ ] 2.2 Actualizar el docblock de `enrichPersonProfiles()` para describir la nueva regla («toda `Person` descrita, anidada o no; las referencias puras se dejan intactas»). Verificación: `php -l` sin errores y el docblock coherente con el comportamiento (revisión del diff).

## 3. Regresión en la suite existente

- [ ] 3.1 Añadir a la suite de datos estructurados de `tests/test-valet.php` los escenarios de `Person` anidada (autor con `sameAs`; referencia pura intacta). Verificación: `just test` con el mismo resultado que antes del cambio (único fallo conocido: `server.json` frente a la versión del plugin, issue #192).
- [ ] 3.2 Ejecutar `just test-t10`, `just test-guid-parity` y `just test-duration`. Verificación: los tres en verde (sin regresión en los arneses vecinos).
- [ ] 3.3 Ejecutar `just php-lint`. Verificación: sin errores de sintaxis en el plugin y el tema.

## 4. Documentación y despliegue

- [ ] 4.1 Crear `docs/author-sameas.md` (force-added: `docs/` está en `.gitignore`) con qué es, ficheros implicados (`class-seo.php:948`), cómo funciona la regla, cómo verificarla y follow-ups. Verificación: el fichero existe y describe la regla y el comando de verificación.
- [ ] 4.2 Desplegar `class-seo.php` a producción con `just deploy-prod`. Verificación: sha256 local == remoto y `owner=82:82` (salida del script).
- [ ] 4.3 Verificar en producción con `?cb=` (bypass de nginx, `x-cache-status: BYPASS`): `/author/atareao/` declara `sameAs`; la portada conserva sus perfiles; un listado y una ficha comparados antes/después; un único bloque `ld+json`. Verificación: salida de los `curl` con los `sameAs` esperados.

## 5. Cierre

- [ ] 5.1 Marcar las casillas con la evidencia y archivar el change (`openspec archive 2026-11-20-author-sameas`), comprobando que el requisito se fusiona **una sola vez** en `openspec/specs/structured-data/spec.md`. Verificación: `openspec validate --strict --all` sin fallos y `rg -c 'Perfiles externos en la entidad' openspec/specs/structured-data/spec.md` devuelve 1.
