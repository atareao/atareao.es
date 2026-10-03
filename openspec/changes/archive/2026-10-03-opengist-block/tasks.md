# Tasks: Validación del servidor y protección SSRF del bloque OpenGist

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/opengist-harness/` (fuera del repo, no versionado) y E2E manual en producción. El arnés no forma parte del commit ni del árbol. **Ninguna casilla se marca hasta ejecutar la verificación correspondiente.**

## 1. Phase 0 — Caracterización y baseline

- [x] 1.1 Documentar el inventario del bloque vulnerable en `design.md` §Context con evidencia `fichero:línea`: atributo `server` (`class-opengist-block.php:71-73`), construcción de `$gist_url` (`:88`), `<script src>` del fallback (`:98-102`), las dos llamadas `wp_remote_get` (`:124-125` y `:157-158`) y el atributo en el editor (`assets/blocks/opengist/index.js:15,64-66`). **Verificación:** cada afirmación cita una línea existente; `openspec validate opengist-block --strict` válido. **Evidencia:** `design.md` §Context (inventario `fichero:línea`); `openspec validate opengist-block --strict` → *Change 'opengist-block' is valid*.
- [x] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia:** baseline de referencia documentado en `design.md`: **752 errores / 426 warnings**.

## 2. Lista blanca y validación del host

- [x] 2.1 Registrar la opción `atareao_opengist_allowed_hosts` (`show_in_rest => true`, default `''`, saneado por entrada con `sanitize_text_field`) editable solo desde la pestaña `tema` con `manage_options`. **Verificación:** arnés — la opción se registra y solo un administrador puede guardarla. **Evidencia:** arnés **A4 PASS** (`opcion_allowed=si manage_options=si sanitize_text_field=si`); `ThemeOptions::sanitizeAllowedHosts()` en `class-theme-options.php`.
- [x] 2.2 Implementar la resolución del servidor efectivo: atributo con host permitido o, si no, `atareao_opengist_server`; normalizar a minúsculas, conservar puerto y exigir el mismo esquema. **Verificación:** arnés — host permitido usa el atributo; host no permitido se ignora y cae a la opción. **Evidencia:** arnés **A1 PASS** y **A2 PASS** (`getAllowedHosts()`/`isServerAllowed()`).
- [x] 2.3 Validar el servidor efectivo con `wp_parse_url` (host no vacío, esquema permitido) antes de cualquier uso; si no es válido, mostrar el placeholder de configuración incompleta sin peticiones. **Verificación:** arnés — servidor inválido no dispara peticiones. **Evidencia:** arnés **A3 PASS** (`placeholder=si peticiones=0`).
- [x] 2.4 Construir `$gist_url` escapando `username`, `gist_id` y `file` con `rawurlencode` como segmentos de ruta. **Verificación:** arnés — caracteres especiales se codifican; la ruta resultante apunta al host permitido. **Evidencia:** arnés **C4 PASS** (`urls=https://gist.example/atareao%20user/abc%20123.js`).

## 3. Protección SSRF en las peticiones salientes

- [x] 3.1 Cambiar las dos llamadas de `fetchGistFiles()` a `wp_safe_remote_get` con `redirection => 0` y `timeout` acotado. **Verificación:** arnés — ambas peticiones usan `wp_safe_remote_get` con `redirection => 0`. **Evidencia:** arnés **C1 PASS** (`peticiones=3 seguras=3 fns=safe,safe,safe`).
- [x] 3.2 Rechazar la petición cuando el host no esté permitido, sin contactarlo. **Verificación:** arnés — `127.0.0.1`/`169.254.169.254`/host ajeno no generan ninguna llamada. **Evidencia:** arnés **C2 PASS** (`sin peticiones a hosts internos`); `isInternalHost()`.
- [x] 3.3 Verificar que no quede ninguna llamada a `wp_remote_get` en el fichero. **Verificación:** `rg -n "wp_remote_get" wp-content/plugins/atareao-functionality/includes/class-opengist-block.php` → 0 coincidencias. **Evidencia:** `rg` → 0 coincidencias (`rg_exit=1`).

## 4. Fallback y editor

- [x] 4.1 Emitir el `<script src>` del fallback solo si el host del script es permitido; si no, no emitir script externo y mostrar un aviso. **Verificación:** arnés — host permitido emite script; host no permitido no emite ningún script externo. **Evidencia:** arnés **B1 PASS** y **B2 PASS** (`evil_en_html=no script_host_no_permitido=no`); **B3 PASS** (script del propio sitio).
- [x] 4.2 Aplicar la misma validación en la vista previa del editor (`assets/blocks/opengist/index.js`) y documentar que el campo `server` solo se honra si el host está permitido. **Verificación:** arnés/inspección — el editor avisa si el host no está permitido. **Evidencia:** arnés **D4 PASS**; `normalizeHost`/`isHostAllowed`/`previewNotice` en `index.js`; `node --check` sin errores.

## 5. Compatibilidad

- [x] 5.1 Confirmar que `atareao_opengist_server` y `atareao_opengist_username` conservan nombre, saneado (`esc_url_raw`/`sanitize_text_field`) y default `''`. **Verificación:** arnés/inspección de `class-theme-options.php`. **Evidencia:** arnés **D2 PASS** (`server_esc_url_raw_default=si username_sanitize_default=si`).
- [x] 5.2 Confirmar que un bloque legítimo (servidor por defecto o atributo permitido) se renderiza igual que antes y que un host no permitido degrada a aviso sin error fatal. **Verificación:** arnés. **Evidencia:** arnés **D1 PASS** y **D3 PASS** (`placeholder=si peticiones=0`).

## 6. Verificación

- [x] 6.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia:** `just php-lint` → 0 errores (exit 0); `just phpcs` → **752 errores / 427 warnings** vs baseline 752/426 → **+0 errores** (+1 warning).
- [x] 6.2 Arnés externo completo. **Verificación:** `/tmp/opencode/opengist-harness/` → `TOTAL=N PASS=N FAIL=0`, `exit=0`; cubre host permitido/no permitido, SSRF (`wp_safe_remote_get` + `redirection => 0`), escapado de ruta, fallback y compatibilidad. **Evidencia:** **`TOTAL=15 PASS=15 FAIL=0`**, `exit=0`.
- [ ] 6.3 E2E manual en producción: bloque de gist propio intacto, bloque con `server` a host ajeno sin script, lista blanca editable solo por admin y restauración de un host propio al añadirlo. **Verificación:** checklist del `design.md` §Verification. **Evidencia:** pendiente (requiere producción).
- [ ] 6.4 No-regresión del resto del sitio: hub, analítica, microsite `/tools/` y demás bloques sin cambios. **Verificación:** E2E manual. **Evidencia:** pendiente (requiere producción).
- [x] 6.5 Spec. **Verificación:** `openspec validate opengist-block --strict` sin hallazgos. **Evidencia:** `openspec validate opengist-block --strict` → *Change 'opengist-block' is valid* (exit 0).

## 7. Entrega

- [ ] 7.1 PR por gitflow de `feature/opengist-block` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia:** pendiente.
- [ ] 7.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive opengist-block` aplica el delta (crea `openspec/specs/opengist-block/spec.md`); `openspec list` ya no muestra el change activo. **Evidencia:** pendiente.
