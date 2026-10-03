# Tasks: Higiene de secretos — rotación aceptada e idempotencia de lectura

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación del change combina análisis estático (`just php-lint`, `just phpcs`), un **arnés de stubs externo** que vive solo en `/tmp/opencode/secrets-harness/` (fuera del repo y no versionado) y E2E manual del usuario en desarrollo y producción. El arnés no forma parte del commit ni del árbol. La implementación arranca **solo tras la aprobación del usuario**. La **rotación de credenciales la ejecuta el usuario** como acción de despliegue; el change solo la **documenta y verifica**. **No se reescribe el historial de git** (decisión del usuario: rotar y aceptar). Toda tarea queda SIN marcar hasta completarse.

## 1. Phase 0 — Línea base y caracterización

- [ ] 1.1 Documentar la caracterización de `getSecret()` con evidencia fichero:línea de `class-cache-purge.php`: rama de variable de entorno (`ATAREAO_PURGE_SECRET`), rama de fichero (`ATAREAO_PURGE_SECRET_FILE`), aplicación de `trim()` en cada rama y comportamiento *fail-closed* sin secreto. **Verificación:** cada afirmación cita una línea existente; `openspec validate secrets-hygiene` válido. **Evidencia esperada:** citas `class-cache-purge.php:46-68` y resultado de validación.
- [ ] 1.2 Fijar el baseline PSR12 antes de tocar nada. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el baseline y se anota el par errores/warnings. **Evidencia esperada:** baseline conocido **752 errores / 427 warnings**; `just php-lint` → 0 errores.
- [ ] 1.3 Registrar en el arnés externo los stubs mínimos para ejercitar `getSecret()` (control de `getenv`, `is_readable`, `file_get_contents` y fichero temporal) con contadores de llamadas. **Verificación:** el arnés ejecuta los escenarios de lectura y devuelve `FAIL=0`. **Evidencia esperada:** `/tmp/opencode/secrets-harness/` adaptado (partiendo del arnés previo de `secrets-exposure`); no se versiona.

## 2. SEC-GEN-002 — Rotación aceptada y verificación del árbol

- [ ] 2.1 Consignar la decisión del usuario: **rotar y aceptar**, sin reescribir el historial de git (nada de BFG, `git filter-repo` ni `force-push`). **Verificación:** `rg` en los artefactos del change confirma que la decisión está escrita y que no hay ninguna tarea de reescritura de historial. **Evidencia esperada:** proposal/infraestructura documentan el compromiso permanente y la no reescritura.
- [ ] 2.2 Documentar en el `README.md` la rotación del **secreto de purga** (ya efectuada en producción por el usuario) y de la **contraseña root de MariaDB** como acción de despliegue del usuario, incluyendo el reinicio de los servicios que las consumen. **Verificación:** `rg -n "rotar|rotación|rotation" README.md` muestra la sección; no se edita ninguna config de producción. **Evidencia esperada:** sección de rotación documentada (el cambio no toca quadlets ni producción).
- [ ] 2.3 Verificar que el **árbol versionado (HEAD)** no contiene ningún secreto real. **Verificación:** `git grep -I 'atareao_purge_2026'` → sin coincidencias; `git grep -In 'root_password\|MYSQL_ROOT_PASSWORD'` → solo nombre de secret o texto de remediación, nunca valor literal. **Evidencia esperada:** árbol limpio; `openspec/specs/infrastructure/spec.md` lo refleja en sus escenarios.
- [ ] 2.4 Comprobar que el valor antiguo del secreto de purga deja de ser aceptado tras la rotación (la rotación la ejecuta el usuario). **Verificación:** E2E del usuario — una petición de purga con el valor antiguo no bypassa la caché y una con el valor nuevo sí. **Evidencia esperada:** purga legítima con el valor nuevo OK y valor antiguo rechazado.

## 3. SEC-GEN-003 — Normalización del secreto en ambas ramas

- [ ] 3.1 RED: escribir el escenario del arnés para la rama de **variable de entorno** con salto de línea/espacio final en `ATAREAO_PURGE_SECRET`, esperando el valor normalizado con `trim()`. **Verificación:** el escenario falla contra el comportamiento previo y el resto del arnés sigue en verde. **Evidencia esperada:** escenario RED documentado con su salida.
- [ ] 3.2 RED: escribir el escenario del arnés para la rama de **fichero** (`ATAREAO_PURGE_SECRET_FILE`) con salto de línea final, esperando el mismo valor normalizado. **Verificación:** el escenario de fichero pasa o falla de forma coherente con la caracterización y no rompe el resto. **Evidencia esperada:** escenario RED/GREEN documentado.
- [ ] 3.3 RED: escribir el escenario *fail-closed* sin secreto (ni variable ni fichero, o ambos vacíos tras `trim()`): `getSecret()` devuelve `''`, `verifySecret()` devuelve `false` y no se usa ningún secreto por defecto. **Verificación:** el escenario pasa sin fuga y sin fallback hardcodeado. **Evidencia esperada:** salida del arnés.
- [ ] 3.4 GREEN: aplicar `trim()` de forma incondicional en **ambas** ramas de `getSecret()` en `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php`, manteniendo la comparación `hash_equals` y el comportamiento *fail-closed*. **Verificación:** `just php-lint` → 0 errores; arnés → `FAIL=0`; sin cambios en el header, el `map` ni el flujo de purga. **Evidencia esperada:** diff mínimo en `getSecret()`; arnés verde.

## 4. Verificación

- [ ] 4.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2 (objetivo: 752 errores / 427 warnings, +0/+0). **Evidencia esperada:** salida de ambas recetas.
- [ ] 4.2 Arnés externo completo. **Verificación:** `/tmp/opencode/secrets-harness/` → `PASS` = total, `FAIL=0`, `exit=0`; cubre normalización por entorno, normalización por fichero, *fail-closed* sin secreto, ausencia del literal en HEAD y las comprobaciones estructurales de higiene (rotación documentada, sin reescritura de historial). **Evidencia esperada:** salida `PASS=N FAIL=0`, exit 0.
- [ ] 4.3 Auditoría de no-regresión de contratos. **Verificación:** `rg` confirma que el header `X-Cache-Purge`, la comparación `hash_equals`, las variables de entorno `ATAREAO_PURGE_SECRET`/`ATAREAO_PURGE_SECRET_FILE` y el flujo de purga se conservan; no se renombra ni se borra ningún contrato. **Evidencia esperada:** `rg` sin hallazgos de regresión.
- [ ] 4.4 Spec. **Verificación:** `openspec validate secrets-hygiene` sin hallazgos. **Evidencia esperada:** «Change 'secrets-hygiene' is valid».

## 5. E2E manual (usuario)

- [ ] 5.1 Rotación en producción: el usuario confirma que el secreto de purga y la contraseña root de MariaDB rotados están activos y que los valores antiguos son rechazados. **Verificación:** comprobación manual del usuario. **Evidencia esperada:** servicios operativos con los valores nuevos; valores antiguos invalidados.
- [ ] 5.2 Flujo legítimo end-to-end: `CachePurge::firePurgeRequests` (secreto desde fichero, `trim`) → `X-Cache-Purge` → `map` de Nginx casa la clave → bypass de `fastcgi_cache` y refresco de la caché. **Verificación:** publicación de prueba en el stack en marcha. **Evidencia esperada:** `purge=[1]` con el valor correcto y `[]` con el incorrecto.
- [ ] 5.3 No-regresión del sitio público: HTML, microsite `/tools/`, analítica, login/logout y notificaciones Matrix sin cambios. **Verificación:** navegación y E2E manual. **Evidencia esperada:** sin cambios observables.

## 6. Entrega

- [ ] 6.1 Comprobar que la documentación del `README.md` coincide con la rotación realmente efectuada (purga y root de MariaDB, compromiso permanente de los valores antiguos, no reescritura del historial). **Verificación:** revisión final frente al estado desplegado. **Evidencia esperada:** documentación sincronizada; pendiente.
- [ ] 6.2 PR por gitflow de `feature/secrets-hygiene` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia esperada:** pendiente.
- [ ] 6.3 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive secrets-hygiene` fusiona los deltas en `openspec/specs/cache-purge/spec.md` y `openspec/specs/infrastructure/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** pendiente.
