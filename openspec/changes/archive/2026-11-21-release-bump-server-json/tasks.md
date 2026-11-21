# Tasks: Coherencia de versión del manifiesto MCP y arnés de valet en CI

## RED

- [x] Test guardián (en `tests/test-valet.php` o en el arnés correspondiente): `.vampus.yml` declara
      una entrada `replaces` para `server.json` que cubre su `version`. Confirmar que **falla** antes
      del arreglo.
- [x] Reproducir el fallo vigente: `just test` sobre `development` → `(reg) version coincide con la
      del plugin` en rojo (plugin 1.26.0 vs `server.json` 1.22.0).

## GREEN

- [x] Añadir a `.vampus.yml` la entrada `replaces` de `server.json` (patrón de su `version`).
- [x] Alinear `server.json` con la versión vigente del plugin para dejar `development` coherente.
- [x] Añadir `tests/test-valet.php` al paso de harnesses PHP de `ci.yml`.
- [x] Tests en verde.

## REFACTOR

- [x] `just test` verde; `just php-lint` limpio.
- [x] Verificar que el bump sigue teniendo un único punto de verdad (sin `sed` ad-hoc en el workflow).

## ARCHIVE

- [x] `openspec archive 2026-11-21-release-bump-server-json`.
