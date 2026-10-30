# Tasks: ai-catalog-enrichment

## 0. Preparación

- [ ] 0.1 Confirmar que la composición del manifiesto vive en un único punto
      (`\Atareao\MCPDiscovery`), de modo que `ard.json` y `ai-catalog.json` deriven de él y no puedan divergir
- [ ] 0.2 Localizar el punto de inyección del `<head>` (`\Atareao\MCP::addDiscoveryMeta`) y comprobar que
      no existe ya un filtro `robots_txt` en el plugin ni en el tema
- [ ] 0.3 Confirmar en el arnés (`tests/wp-stubs.php`) qué stubs hacen falta para `robots_txt` y `wp_head`

## 1. RED — los tests primero, y que fallan

- [x] 1.1 `GET /.well-known/ard.json` → `200`, `Content-Type: application/json`, objeto con array `entries`
- [x] 1.2 La entrada del servidor MCP declara `representativeQueries` con entre 2 y 5 consultas
- [x] 1.3 La entrada declara `@context` = `https://agenticresourcediscovery.org/context/v1`
- [x] 1.4 `GET /.well-known/ai-catalog.json` sigue devolviendo `200` con `application/ai-catalog+json`
- [x] 1.5 Ambas rutas declaran el **mismo conjunto de entradas** (no pueden divergir)
- [x] 1.6 `robots.txt` contiene una línea `Agentmap:` con la URL **absoluta** del manifiesto
- [x] 1.7 El `<head>` contiene `<link rel="ard">` con la URL absoluta del manifiesto
- [x] 1.8 Las tres URLs (`Agentmap`, `rel="ard"`, well-known) son idénticas
- [x] 1.9 El manifiesto no contiene credenciales y no enumera herramientas, recursos ni prompts
- [x] 1.10 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

```
$ just test
Total passed: 740, Total failed: 25
EXIT=1
```

Los **25 fallos** corresponden exclusivamente a los escenarios nuevos, por prefijo:
`(ard)` 7 · `(robots)` 2 · `(proxy)` 2 · `(head)` 2 · `(plugin)` 1 · `(diverge)` 1 · `(canales)` 1.
Los **740 aciertos** no varían respecto al estado previo: **cero regresión**.

```
$ just php '-l tests/test-valet.php'
No syntax errors detected in tests/test-valet.php
```

- Ficheros modificados: **solo** `tests/test-valet.php` (+338 líneas). `wp-content/`, `openspec/`, `nginx/` intactos.
- Los tests usan **10 guards** (`method_exists` / `class_exists` / `is_callable`) para que un seam
  aún inexistente produzca un `FAIL:` legible en vez de un error fatal que aborte el arnés.
- Ningún test vacuo.

**Escenarios no automatizables en este arnés (declarado, no maquillado):**

- *El binding DNS no es requisito* — es una afirmación de alcance sobre registros de la zona DNS,
  fuera del repositorio; no hay comportamiento que asegurar.
- *Las rutas de descubrimiento no sufren el bloqueo por User-Agent* y *la ruta llega al plugin* —
  el arnés PHP no puede ejercitar nginx. Se asegura la **configuración** (`nginx/pro/nginx.conf`:
  el mapa `$mcp_machine_path` y el mecanismo `$bad_bot_blocked` deben cubrir la ruta ARD); el
  comportamiento en ejecución se verifica **E2E** en la tarea 4.4.

## 2. GREEN — implementación mínima

- [x] 2.1 `class-mcp-discovery.php`: ruta `ard.json` y fuente de verdad única para las dos rutas
- [x] 2.2 `class-mcp-discovery.php`: `@context` y `representativeQueries` en la entrada
- [x] 2.3 `class-mcp.php`: `<link rel="ard">` junto a la etiqueta existente, en `addDiscoveryMeta`
- [x] 2.4 Filtro `robots_txt` que añada la directiva `Agentmap:` (el `robots.txt` es virtual, no un fichero)
- [x] 2.5 `nginx/pro/nginx.conf`: ampliar el mapa de exención de rutas de máquina con las rutas de descubrimiento
- [x] 2.6 `just test` → **100% verde**; `just php-lint` limpio

## 3. REFACTOR

- [x] 3.1 Eliminar la duplicación entre las dos rutas del manifiesto (una sola composición, dos rutas)
- [x] 3.2 Revisar que no quede una lista de rutas hardcodeada en dos sitios (rutas, exención nginx, tests)
- [x] 3.3 `just phpcs` limpio y `just php-lint-changed` limpio

### Evidencia — fase GREEN

```
$ just test
Total passed: 773, Total failed: 0
All tests passed successfully!
EXIT=0
```

De **740 passed / 25 failed** (RED) a **773 passed / 0 failed**. Ningún test preexistente se ha
modificado: los ficheros bajo `tests/` están intactos (`git status --short -- tests` → vacío).

```
$ just php-lint
PHP_LINT_EXIT=0        # 63 ficheros, ninguno con error
$ just php-lint-changed
No syntax errors detected in .../class-mcp-discovery.php
No syntax errors detected in .../class-mcp.php
```

**PSR12 — matiz honesto sobre la tarea 3.3.** `just phpcs` **no** queda globalmente limpio: el
repositorio arrastra deuda previa (cientos de errores en el tema, las plantillas de `templates/`
y ficheros `*.min.js`) que no tiene relación con este change y que no se debe arreglar aquí.
Lo que sí queda demostrado:

- Los dos ficheros tocados: **0 ERRORS**.
- `class-mcp.php`: 5 warnings, todos preexistentes (1 file-level + 4 de longitud de línea en
  líneas que no se han tocado). La única línea añadida mide **110 caracteres** (< 120), luego no
  puede introducir un aviso de longitud.
- `class-mcp-discovery.php`: 1 warning file-level preexistente (la estructura del fichero no se ha
  tocado en su cabecera).

**Tarea 3.2 — matiz honesto.** El literal de la ruta no puede tener una única fuente entre PHP y
nginx: son dos runtimes distintos. La fuente de verdad en el código es la constante `ARD_PATH`, y
nginx repite el literal en su propio mapa. Los tests lo repiten por naturaleza. Lo que sí se ha
eliminado es la **duplicación interna en PHP** (una sola composición: `discoveryEntries()`).

## 4. Verificación

- [ ] 4.1 `openspec validate ai-catalog-enrichment --strict` → valid
- [ ] 4.2 `just check-spec` → en verde
- [ ] 4.3 Auditoría de seguridad del backend antes de la entrega
- [ ] 4.4 Verificación E2E en producción tras el despliegue: `GET /.well-known/ard.json`,
      `GET /.well-known/ai-catalog.json`, `robots.txt` con el `Agentmap`, `<link rel="ard">` en el `<head>`,
      y las rutas nuevas alcanzables **con User-Agent `curl`** (sin `444`)
- [ ] 4.5 Comprobar que el manifiesto servido no expone credenciales ni primitives

## 5. Entrega

- [ ] 5.1 PR `feature/ai-catalog-enrichment → development` con **merge commit** (convención del proyecto)
- [ ] 5.2 Archivar el change fusionando el delta en `openspec/specs/mcp-discovery/spec.md`

## Notas de implementación

- **Headers de los deltas**: los tres requisitos MODIFIED conservan **literalmente** su header original
  para que `openspec archive` los localice en la spec destino. El requisito del catálogo se ensancha en
  el cuerpo, no en el título; el título canónico se reorganizará al archivar si procede.
- **Nota de riesgo**: `representativeQueries` es un *warning* de conformidad, no un fallo duro. El test
  debe comprobar presencia y forma (2–5, no vacías), no que un validador externo las puntúe.
- **Fuera de alcance, en el change del backlog**: entradas para `/tools/` (exige autorar skills Markdown
  públicas), entrada OpenAPI (WordPress no la publica) y binding DNS (`_entries._agents.…`).
