# Proposal: Coherencia de versión del manifiesto MCP y arnés de valet en CI

## Why

`release-prepare.yml` bumpea la versión del plugin y del tema, pero **no** `server.json` (descriptor
del servidor MCP). Efecto en `development` hoy: el plugin está en **1.26.0** y `server.json` sigue en
**1.22.0**. El test `(reg) version coincide con la del plugin` (`tests/test-valet.php:6145`) falla
tras **cada** release → `just test` en rojo (1295 PASS / 1 FAIL). Por eso `tests/test-valet.php` no
puede entrar en CI: tumbaría el check requerido `lint` de todos los PR. Además, el registro MCP
reportaría una versión obsoleta.

## What Changes

1. El bump de release actualiza también la `version` de `server.json`, desde un **único punto de
   verdad** (`vampus` / `.vampus.yml`), sin paso manual.
2. Se deja `development` coherente (bumpear `server.json` a la versión vigente del plugin) para que
   `tests/test-valet.php` quede verde.
3. `ci.yml` incorpora `tests/test-valet.php` al paso de harnesses PHP, ahora que pasa.
4. Un test guarda contra la regresión: el arnés verifica que `.vampus.yml` declara la actualización de
   `server.json`.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `release-pipeline`: se **añaden** requirements sobre la coherencia de versión del manifiesto MCP en
  el bump y sobre la ejecución del arnés de valet en CI.

## Impact

- **Configuración:** `.vampus.yml` (nueva entrada `replaces` para `server.json`) y/o
  `.github/workflows/release-prepare.yml`.
- **CI:** `.github/workflows/ci.yml` (ejecuta `tests/test-valet.php`).
- **Datos:** `server.json` (`version` alineada con el plugin).
- **Spec:** `openspec/specs/release-pipeline/spec.md`.
- **No cambia:** runtime de WordPress, feed, ni el contrato del manifiesto (`mcp-registry` sigue igual).
