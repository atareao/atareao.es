# Tasks: Validación del servidor y protección SSRF del bloque OpenGist

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/opengist-harness/` (fuera del repo y no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol. **Ninguna casilla se marca hasta ejecutar la verificación correspondiente.**

## 1. Phase 0 — Caracterización y baseline

- [ ] 1.1 Documentar el inventario del bloque vulnerable en `design.md` §Context con evidencia `fichero:línea`: atributo `server` (`class-opengist-block.php:71-73`), construcción de `$gist_url` (`:88`), `<script src>` del fallback (`:98-102`), las dos llamadas `wp_remote_get` (`:124-125` y `:157-158`) y el atributo en el editor (`assets/blocks/opengist/index.js:15,64-66`). **Verificación:** cada afirmación cita una línea existente; `openspec validate opengist-block --strict` válido. **Evidencia:** pendiente.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia:** pendiente (baseline de referencia documentado en `design.md`: 752 errores / 426 warnings).

## 2. Lista blanca y validación del host

- [ ] 2.1 Registrar la opción `atareao_opengist_allowed_hosts` (`show_in_rest => true`, default `''`, saneado por entrada con `sanitize_text_field`) editable solo desde la pestaña `tema` con `manage_options`. **Verificación:** arnés — la opción se registra y solo un administrador puede guardarla. **Evidencia:** pendiente.
- [ ] 2.2 Implementar la resolución del servidor efectivo: atributo con host permitido o, si no, `atareao_opengist_server`; normalizar a minúsculas, conservar puerto y exigir el mismo esquema. **Verificación:** arnés — host permitido usa el atributo; host no permitido se ignora y cae a la opción. **Evidencia:** pendiente.
- [ ] 2.3 Validar el servidor efectivo con `wp_parse_url` (host no vacío, esquema permitido) antes de cualquier uso; si no es válido, mostrar el placeholder de configuración incompleta sin peticiones. **Verificación:** arnés — servidor inválido no dispara peticiones. **Evidencia:** pendiente.
- [ ] 2.4 Construir `$gist_url` escapando `username`, `gist_id` y `file` con `rawurlencode` como segmentos de ruta. **Verificación:** arnés — caracteres especiales se codifican; la ruta resultante apunta al host permitido. **Evidencia:** pendiente.

## 3. Protección SSRF en las peticiones salientes

- [ ] 3.1 Cambiar las dos llamadas de `fetchGistFiles()` a `wp_safe_remote_get` con `redirection => 0` y `timeout` acotado. **Verificación:** arnés — ambas peticiones usan `wp_safe_remote_get` con `redirection => 0`. **Evidencia:** pendiente.
- [ ] 3.2 Rechazar la petición cuando el host no esté permitido, sin contactarlo. **Verificación:** arnés — `127.0.0.1`/`169.254.169.254`/host ajeno no generan ninguna llamada. **Evidencia:** pendiente.
- [ ] 3.3 Verificar que no quede ninguna llamada a `wp_remote_get` en el fichero. **Verificación:** `rg -n "wp_remote_get" wp-content/plugins/atareao-functionality/includes/class-opengist-block.php` → 0 coincidencias. **Evidencia:** pendiente.

## 4. Fallback y editor

- [ ] 4.1 Emitir el `<script src>` del fallback solo si el host del script es permitido; si no, no emitir script externo y mostrar un aviso. **Verificación:** arnés — host permitido emite script; host no permitido no emite ningún script externo. **Evidencia:** pendiente.
- [ ] 4.2 Aplicar la misma validación en la vista previa del editor (`assets/blocks/opengist/index.js`) y documentar que el campo `server` solo se honra si el host está permitido. **Verificación:** arnés/inspección — el editor avisa si el host no está permitido. **Evidencia:** pendiente.

## 5. Compatibilidad

- [ ] 5.1 Confirmar que `atareao_opengist_server` y `atareao_opengist_username` conservan nombre, saneado (`esc_url_raw`/`sanitize_text_field`) y default `''`. **Verificación:** arnés/inspección de `class-theme-options.php`. **Evidencia:** pendiente.
- [ ] 5.2 Confirmar que un bloque legítimo (servidor por defecto o atributo permitido) se renderiza igual que antes y que un host no permitido degrada a aviso sin error fatal. **Verificación:** arnés. **Evidencia:** pendiente.

## 6. Verificación

- [ ] 6.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia:** pendiente.
- [ ] 6.2 Arnés externo completo. **Verificación:** `/tmp/opencode/opengist-harness/` → `TOTAL=N PASS=N FAIL=0`, `exit=0`; cubre host permitido/no permitido, SSRF (`wp_safe_remote_get` + `redirection => 0`), escapado de ruta, fallback y compatibilidad. **Evidencia:** pendiente.
- [ ] 6.3 E2E manual en producción: bloque de gist propio intacto, bloque con `server` a host ajeno sin script, lista blanca editable solo por admin y restauración de un host propio al añadirlo. **Verificación:** checklist del `design.md` §Verification. **Evidencia:** pendiente (requiere producción).
- [ ] 6.4 No-regresión del resto del sitio: hub, analítica, microsite `/tools/` y demás bloques sin cambios. **Verificación:** E2E manual. **Evidencia:** pendiente.
- [ ] 6.5 Spec. **Verificación:** `openspec validate opengist-block --strict` sin hallazgos. **Evidencia:** pendiente (validación de la propuesta ya ejecutada en la entrega de este change).

## 7. Entrega

- [ ] 7.1 PR por gitflow de `feature/opengist-block` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente.
- [ ] 7.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive opengist-block` aplica el delta (crea `openspec/specs/opengist-block/spec.md`); `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
