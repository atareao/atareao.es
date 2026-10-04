# Tasks: `post_views_count` no negativo

> Sin framework de tests ni build tools. La verificación combina estáticos (`just php-lint`, `just phpcs`), arnés externo de stubs (`/tmp/opencode/`, no versionado) y E2E. La implementación arranca solo tras la aprobación.

## 1. Línea base
- [ ] 1.1 Registrar baseline: `just php-lint` → 0 errores; `just phpcs` theme+plugin → par errores/warnings. **Evidencia:** números anotados.

## 2. RED — reproducir el defecto
- [ ] 2.1 Añadir al arnés un escenario que invoca `sanitize_meta` con el cierre actual y un valor negativo (`-7`). **Verificación:** contra el código actual devuelve `-7` (no `0`). **Evidencia:** `FAIL` del escenario «negativo → 0».
- [ ] 2.2 Escenario de valor no numérico (`'abc'`) → esperado `0`. **Verificación:** contra el actual devuelve `0` (ya pasa) o `0`; documentar.

## 3. GREEN — implementación mínima
- [ ] 3.1 Cambiar el cierre a `return max(0, absint($value));` en `class-metaboxes.php`. **Verificación:** arnés → negativo `0`, `'abc'` `0`, `5` `5`, sin `ArgumentCountError`. **Evidencia:** `FAIL=0`.
- [ ] 3.2 Guardia de regresión: el cierre no es una función interna rechazable y tolera 4 argumentos. **Verificación:** escenario ME-04c del arnés en verde.

## 4. Verificación
- [ ] 4.1 `just php-lint` → 0 errores; `just phpcs` → delta +0 respecto a 1.1.
- [ ] 4.2 `openspec validate views-sanitize-nonnegative` → valid.

## 5. E2E en producción
- [ ] 5.1 Escritura REST autenticada (`edit_posts`) de `post_views_count` con valor negativo → el valor persistido es `0` (o `update_post_meta` server-side del mismo modo). **Verificación:** petición manual. **Evidencia:** valor `0`.

## 6. Entrega
- [ ] 6.1 Marcar tareas y sincronizar `tasks.md`.
- [ ] 6.2 PR por gitflow a `development` con commits convencionales (gitmoji).
- [ ] 6.3 `openspec archive views-sanitize-nonnegative`.
