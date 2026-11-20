# Tasks: Perfiles externos (`sameAs`) en la entidad `Person` del grafo

## 1. Arnés RED (contrato del delta)

- [x] 1.1 Crear `tests/structured-data/test_person_sameas.php` con los seis escenarios del delta. Verificación: `podman run --rm -v "$PWD:/workspace:Z" -w /workspace --entrypoint php docker.io/wordpress:cli-php8.3 tests/structured-data/test_person_sameas.php` → **9 PASS, 1 FAIL, EXIT=1**; el único fallo es «la Person anidada en WebSite.publisher declara sameAs con GitHub y el canal de YouTube normalizado» (RED confirmado). Commit `d3e43cf`.
- [x] 1.2 Añadir el arnés a la receta `test-t10` de `.justfile` y al paso «Test PHP harnesses» de `.github/workflows/ci.yml`. Verificación: `rg -n 'test_person_sameas' .justfile .github/workflows/ci.yml` → `.justfile:448` y `.github/workflows/ci.yml:49`. Commit `d3e43cf`.

## 2. Implementación (GREEN)

- [x] 2.1 Reescribir `enrichPersonProfiles()` (línea 948) para recorrer el grafo de forma recursiva y enriquecer toda entidad `Person` que declare `name` o `url`, fusionando `sameAs` con `mergeProfiles()` y sin tocar referencias puras. Verificación: arnés de 1.1 → **10 PASS, 0 FAIL, EXIT=0**; `just php-lint` EXIT=0; `tests/t10-residuals/test_seo_tsf_integration.php` → 216 PASS, 0 FAIL. Commit `d3e43cf`.
- [x] 2.2 Actualizar el docblock de `enrichPersonProfiles()` («toda `Person` descrita, anidada o no; las referencias puras se dejan intactas»), más los helpers `enrichPersons()` e `isDescribedPerson()`. Verificación: `php -l` sin errores; diff revisado. Commit `d3e43cf`.

## 3. Regresión en la suite existente

- [x] 3.1 Añadir a la suite de datos estructurados de `tests/test-valet.php` los escenarios de `Person` anidada y de referencia pura. Verificación: `just test` → los dos escenarios nuevos PASS; único fallo el preexistente de `server.json` (issue #192). Commit `c2dfc65`.
- [x] 3.2 Ejecutar `just test-t10`, `just test-guid-parity` y `just test-duration`. Verificación: `test-t10` 49 PASS + 10 PASS (arnés nuevo), `test-guid-parity` 78 PASS, `test-duration` 5 PASS; los tres EXIT=0.
- [x] 3.3 Ejecutar `just php-lint`. Verificación: EXIT=0, sin errores de sintaxis.

## 4. Documentación y despliegue

- [x] 4.1 Crear `docs/author-sameas.md` (force-added: `docs/` está en `.gitignore`) con qué es, ficheros implicados (`class-seo.php:948`), cómo funciona la regla, cómo verificarla y follow-ups. Verificación: fichero commiteado (commit `0a825fb`).
- [x] 4.2 Desplegar `class-seo.php` a producción con `just deploy-prod atareao wp-content/plugins/atareao-functionality/includes/class-seo.php=wp-content/plugins/atareao-functionality/includes/class-seo.php`. Verificación: `OK /var/www/html/.../class-seo.php sha256=b8ae6a674378a14497d97d38997c407c9e79020f54227bd6a7553dbb34565966 owner=82:82` (sha256 local idéntico).
- [x] 4.3 Verificar en producción con `?cb=` (`x-cache-status: BYPASS`): `/author/atareao/` declara `sameAs` (9 perfiles, canal de YouTube normalizado `https://www.youtube.com/channel/UCP8dTCaLNJFxfklb_GNqe6A`); la portada conserva sus perfiles; `/blog/`, `/podcast/`, `/tutoriales/` reciben `sameAs`; la ficha de post y la de episodio lo reciben en el `publisher` y el `author` conserva el suyo; un único bloque `ld+json`; `@type`/`@id` sin cambios.

## 5. Cierre

- [x] 5.1 Marcar las casillas con la evidencia y archivar el change (`openspec archive 2026-11-20-author-sameas`). Verificación: requisito fusionado una sola vez en `openspec/specs/structured-data/spec.md` (`rg -c` = 1) y `openspec validate --strict --all` sin fallos.
