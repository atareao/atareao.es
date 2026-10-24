# Tasks

> Protocolo: **RED → GREEN → REFACTOR** por cada comportamiento nuevo. Ninguna tarea de implementación empieza antes de que la tarea 1.1 haya dejado el **rojo** demostrado por CLI. Verificación **siempre** con CLI (`just test`, `just php-lint`, `just phpcs`), nunca por inspección visual.
>
> **Baseline de referencia (medido, `HEAD` = árbol limpio + este change):** `just test` → **284 asserts PASS / 0 FAIL, exit 0**. Ese recuento es el listón de no regresión: al cerrar el change, la suite debe seguir reportando los **284 asserts preexistentes en verde**, sin ningún FAIL.
>
> **Alcance:** este repositorio **no** implementa el cliente que emite el borrado (vive en el servicio Valet, `/data/rust/valet`, con su propio change). Aquí se implementa la **garantía de superficie** (filtro por identidad), se **caracteriza** el contrato del sitio con tests y se **documenta** el contrato que el consumidor debe cumplir.

## 1. Superficie REST de la colección por identidad — arreglo del defecto previo H1 (spec `valet-replies`, MODIFIED)

- [x] 1.1 **RED** — Añadir a `tests/test-valet.php` (y los stubs de `tests/wp-stubs.php` que hagan falta) los tests que **fallan** contra el filtro actual `atareao_functionality_disable_rest_comment_endpoint()`, invocándolo **por comportamiento** sobre una tabla de rutas con la forma real del core (`'/wp/v2/comments' => ['GET' => [...], 'POST' => [...]]`), sin reflexión. **Tres casos de identidad:**
  - (a) **usuario de servicio** autenticado (identificador de servicio configurado, p. ej. `7`) → la ruta de colección **sobrevive** y conserva el manejador de `POST`, de modo que el contrato de respuesta del asistente es alcanzable (el defecto previo la eliminaba siempre → hoy este caso está en rojo);
  - (b) **otro usuario autenticado** (distinto del de servicio, p. ej. `3`) → la ruta de colección queda **eliminada entera**;
  - (c) **anónimo** (`get_current_user_id()` = `0`) → la ruta de colección queda **eliminada entera**.
  **Dos casos de método** (sobre el usuario de servicio):
  - (d) `POST` de la colección **presente** → el asistente puede publicar;
  - (e) `GET` de la colección **ausente** → el asistente **no puede enumerar** comentarios.
  **Borde obligatorio (fallo a cerrado):** (f) con la opción del identificador de servicio ausente, vacía o `0`, la colección se elimina **entera** —incluido su `POST`— **aunque** la petición venga del usuario que se pretendía de servicio. Criterio de verificación: `just test` falla con asserts rojos exactamente en (a), (d), (e) y (f) —el comportamiento actual elimina la ruta en todos los casos—, y el resto de la suite (los 284 asserts preexistentes) sigue **verde**.
- [x] 1.2 **GREEN** — Reescribir el filtro `rest_endpoints` de `atareao-functionality.php` (`:77-84`) para que resuelva **por identidad del usuario ya autenticado** (`get_current_user_id()` contra el identificador de servicio, leído de la misma fuente que el sellado y la cota): sin usuario de servicio → eliminar la colección entera (fallo a cerrado); usuario de servicio → conservar el manejador de `POST` y **descartar** el de `GET`; cualquier otro → eliminar la colección entera. Sin aceptar ninguna indicación aportada por el cliente (parámetro, cabecera ni cuerpo) para decidir la apertura. La ruta de **elemento único** no se toca. Criterio de verificación: `just test` en verde con los **seis** casos de 1.1 (a)–(f) y sin FAIL en los 284 asserts preexistentes.
- [x] 1.3 **REFACTOR** — Dejar el filtro legible y sin duplicar la lectura del identificador de servicio: reutilizar el mismo acceso ya usado por el sellado y la cota (`atareao_valet_reply_user`, `class-valet-config.php:140`, con su filtro del mismo nombre, `:275`) en lugar de una lectura paralela; documentar con docblock el porqué de la exención por identidad y de que la identidad ya está resuelta cuando corre el filtro (`check_authentication()` antes de `dispatch()`). Re-ejecutar `just test` + `just php-lint` + `just phpcs`. Criterio de verificación: suite en verde, `just php-lint` exit `0` y `just phpcs` **sin violaciones nuevas** respecto del baseline.

## 2. Contrato del borrado: caracterización en el sitio (spec `valet-moderation`, ADDED)

- [x] 2.1 **RED** — Añadir a `tests/test-valet.php` los tests que fallan para los desenlaces **del sitio** que este repositorio sí controla, ejercitando el filtro y los enganches por comportamiento:
  - (a) con usuario de servicio, un comentario que le fue notificado es alcanzable por la ruta de **elemento único** (`GET` y `DELETE` siguen registrados tras el filtro), lo que sostiene la **lectura previa** y el borrado reversible;
  - (b) el `DELETE` de elemento único **no** queda cerrado por el filtro (la restricción de superficie solo afecta a la **colección**): con usuario de servicio, la ruta `'/wp/v2/comments/(?P<id>[\d]+)'` permanece intacta;
  - (c) el rol documentado no cambia: el test comprueba que la identidad de servicio que el sitio exige para publicar es la **misma** que la que permite borrar (no aparece ninguna capacidad ni opción nueva en el módulo).
  Criterio de verificación: `just test` falla con asserts rojos en (a)–(c) —hoy la superficie de colección se elimina siempre y el contrato de elemento único no está caracterizado—, y los 284 asserts preexistentes siguen verdes.
- [x] 2.2 **GREEN** — Dejar el contrato de borrado **satisfecho por construcción**: el filtro de 1.2 no altera la ruta de elemento único y el borrado usa el controlador REST del core (`force=false` → `wp_trash_comment()`), sin código nuevo de borrado en el plugin. Criterio de verificación: `just test` en verde con (a)–(c) y sin regresión en los 284 asserts preexistentes.
- [x] 2.3 **REFACTOR** — Consolidar en `tests/wp-stubs.php` el auxiliar que construye la tabla de rutas del core (colección + elemento único) para que 1.1 y 2.1 compartan el mismo escenario, y ejecutar `just phpcs` sobre los ficheros tocados. Criterio de verificación: `just test` verde y `just phpcs` sin violaciones nuevas.

## 3. El borrado no notifica ni consume presupuesto (spec `valet-moderation`, ADDED)

- [x] 3.1 **RED/verificación** — Añadir tests de **caracterización** de la garantía «borrar no notifica», sabiendo que el comportamiento **ya existe** (`notifyOnCommentTransition()` sale salvo transición hacia `approved`, `class-valet-config.php:673-684`), de modo que pueden nacer verdes: (a) transición hacia `trash` → **cero** notificaciones despachadas; (b) transición hacia `spam` y hacia `hold` → **cero** notificaciones; (c) la transición hacia `approved` **sí** notifica (control positivo que impide un test vacuo); (d) retirar una respuesta **no** consume ranura del presupuesto de la ruta de comentarios, de modo que el aviso siguiente de un comentarista real se despacha. Criterio de verificación: `just test` en verde con (a)–(d) **y** la falsabilidad demostrada en la tarea 5.3 (mutación de la guarda → rojo). Si (a)–(d) no pueden ponerse en rojo por ninguna mutación, el test es vacuo y debe reescribirse antes de continuar.
- [x] 3.2 **GREEN** — **No hay código nuevo que escribir aquí**: la guarda existente satisface los tres requirements de `valet-moderation` en lo que al sitio toca. Si (a)–(d) no pasan tal cual, **detener el trabajo** y consignar la contradicción (significaría que el borrado sí despacha notificación y habría que revisar la guarda como cambio propio del módulo de notificaciones, no como parte de este change). Criterio de verificación: `just test` en verde sin tocar `class-valet-config.php`.

## 4. Documentación del contrato de borrado en el README del plugin

- [x] 4.1 Documentar en `wp-content/plugins/atareao-functionality/README.md`, en la sección del canal inverso: (i) la petición `DELETE /wp-json/wp/v2/comments/<id>` con **`force=false`** (papelera, reversible) y `Content-Type`/autenticación Basic con la Application Password del usuario de servicio; (ii) de dónde sale el `<id>` (`metadata.comment_id` de la notificación, contrastable con `metadata.post_id`), y la advertencia de que el asistente **no puede enumerar** la colección (garantía **real** del servidor) mientras que la restricción a «solo lo notificado» es **disciplina del consumidor** —la herramienta solo admite el identificador de la notificación—, **no** una garantía del sitio (con el riesgo residual explícito); (iii) el **ejemplo** de `curl` con `-X DELETE` y `force=false`; (iv) la **tabla de desenlaces** con `200`, `410 rest_already_trashed`, `404 rest_comment_invalid_id`, `403 rest_cannot_delete` y `501 rest_trash_not_supported`, señalando que «ya está borrado» es el `410`; (v) la **ventana de reversibilidad** de **30 días** (`EMPTY_TRASH_DAYS` por defecto, constante no definida en el repositorio) y cómo se restaura desde el panel; (vi) que el rol sigue siendo `read` + `moderate_comments`, sin cambios; (vii) que **borrar no notifica** (sin bucle) y no consume el presupuesto de comentarios. Criterio de verificación: la sección existe, los códigos y el `force=false` aparecen **literales**, y la petición coincide con el contrato de los deltas (`grep` de contraste adjunto).
- [x] 4.2 Verificar que **no se documenta ni se ofrece `force=true`**: `grep -n 'force=true' wp-content/plugins/atareao-functionality/README.md` no devuelve ninguna ocurrencia en el apartado del borrado, coherente con el no-goal del borrado definitivo. Criterio de verificación: salida literal del `grep` (sin ocurrencias) y `just php-lint` exit `0`.

## 5. Verificación por mutación de los controles nuevos (2 mutaciones exigidas + 1 de la guarda + restauración)

- [x] 5.1 **Mutación «quitar la exención»** — Devolver el filtro a su forma incondicional (`unset($endpoints['/wp/v2/comments'])` siempre) y ejecutar `just test`. Criterio de verificación: la suite **debe** ir a **rojo** con los asserts de 1.1 (a) —el usuario de servicio ya no conserva `POST`—, (d) y (e); exit code distinto de `0`; salida literal adjunta. A continuación restaurar el código y comprobar con `sha256sum` que el fichero vuelve a su hash previo.
- [x] 5.2 **Mutación «abrir `GET`»** — Conservar la colección **completa** para el usuario de servicio (no descartar el manejador de `GET`) y ejecutar `just test`. Criterio de verificación: la suite **debe** ir a **rojo** con el assert de 1.1 (e) —el usuario de servicio **sí puede enumerar**—; exit code distinto de `0`; salida literal adjunta. Restaurar y comprobar hash idéntico. Si esta mutación **no** pone el test (e) en rojo, el test no mide la garantía y debe reescribirse.
- [x] 5.3 **Mutación «neutralizar la guarda entera»** — Suprimir por completo el retorno temprano de `notifyOnCommentTransition()` (`if ($new_status !== 'approved' || $old_status === 'approved') { return; }`), de modo que el manejador notifique en **cualquier** transición, y ejecutar `just test`. Criterio de verificación: rojo en 3.1 (a)–(b) —y en las caracterizaciones previas de la guarda— mientras (c) sigue verde; exit code distinto de `0`; salida literal adjunta. **Medido:** `Total passed: 307, Total failed: 10`, exit `1`; (c) queda en verde. Restaurado el fichero con `sha256` **idéntico** al de referencia: `2dfc1e7b32f9d5ec81cd53285fe2a109380113b4d3cc6f17bbf602e9f6dc56c0`. A esta mutación se le suma, en la misma tanda, la **variante del filtro** (volver a indexar la colección por **nombre de método**), que da **6 rojos** (véanse las notas). El recuento es **307** pass, no 303, porque 3.1 incorpora los **4 asserts nuevos** de SEC-BE-004 (+3 de la respuesta sellada y +1 del sello).
- [x] 5.4 **Confirmación de restauración** — Con las tres mutaciones revertidas: `just test` → **0 FAIL, exit 0**; `git status --porcelain` solo muestra los ficheros legítimos del change; hashes de los ficheros mutados **idénticos** a los de referencia. Criterio de verificación: salida literal de los tres comandos.

## 6. No regresión, lint y aislamiento de alcance

- [x] 6.1 Ejecutar la batería completa: `just test`, `just php-lint`, `just phpcs`. Criterio de verificación: `just test` → **0 FAIL con los 284 asserts preexistentes en verde** (más los nuevos), exit `0`; `just php-lint` exit `0`; `just phpcs` **sin violaciones nuevas** respecto del baseline (si el `phpcs` global sale rojo, demostrar que es **preexistente y ajeno** comparando el inventario fichero→recuento contra `git archive HEAD`, como se hizo en el change anterior). Salida literal de los tres comandos adjunta.
- [x] 6.2 Verificar que **`rest-access-policy` NO se toca**: `grep -n "public_routes" -A4 wp-content/plugins/atareao-functionality/atareao-functionality.php` demuestra que la lista sigue conteniendo **solo** `/atareao/v1/mcp`, y `git status --porcelain -- openspec/specs/rest-access-policy` sale vacío. Criterio de verificación: salida literal de ambos comandos.
- [x] 6.3 Verificar que **`valet-notifications`, `matrix-notifications`, `comments`, `anti-abuse` y `admin-settings` NO se tocan**: `git status --porcelain` limitado a sus specs y a los ficheros de esas capabilities (`class-comment-security.php`, `class-matrix-config.php`, `class-settings.php`) sale **vacío**. Criterio de verificación: salida literal (vacía) del comando, y `git diff --stat -- openspec/specs/` limitado a `valet-replies` por lo que a spec viva se refiere.
- [x] 6.4 Validar el change con el CLI: `openspec validate valet-comment-deletion --strict` exit `0`, y `openspec status --change valet-comment-deletion` mostrando los artefactos completos. Criterio de verificación: salida literal de ambos comandos. **No archivar** hasta que el usuario lo indique.

## 7. Verificación pendiente (bloqueada — NO forma parte de este change)

- [ ] 7.1 **Prueba de punta a punta del borrado — BLOQUEADA, no se marca.** Requiere el **stack arrancado** y el **aprovisionamiento manual** de la identidad de servicio (usuario dedicado `read` + `moderate_comments` y su Application Password, más SSL o `WP_ENVIRONMENT_TYPE=local`). Consiste en: (i) que un `POST` real del servicio deje de devolver `404 rest_no_route` y publique la respuesta; (ii) que un `DELETE .../<id>` real con `force=false` deje el comentario en la papelera; (iii) que una segunda petición devuelva `410 rest_already_trashed`; (iv) que `GET` de la colección siga devolviendo `404 rest_no_route` para el usuario de servicio. Criterio de verificación: salida literal de los `curl` y nota en el change. **No es requisito de este change**: es deuda de verificación heredada de `2026-10-22-valet-comment-replies`, que este change **desbloquea** pero no puede cerrar por sí solo. Dejar la casilla **sin marcar** y reportarlo.
  > **BLOQUEO — la casilla queda sin marcar.** Requiere la pila arrancada y el aprovisionamiento
  > manual. En esta máquina **no hay secretos de podman ni WordPress instalado**: la pila nunca se ha
  > aprovisionado, y por **decisión del usuario no se aprovisiona en este trabajo**. Es deuda de
  > verificación heredada de `2026-10-22-valet-comment-replies` (el encargo la cita como «1.3»; en
  > este fichero la prueba de punta a punta es la **7.1**).
- [x] 7.2 Consignar en el informe final, si aparece, cualquier **discrepancia** entre lo que asume el diseño de este change y lo que diga el repositorio o el core (por ejemplo, cifras o líneas citadas de `wp-includes/`), con el dato real y el comando que lo demuestra. Criterio de verificación: apartado de discrepancias del informe, con evidencia.

## 8. Re-auditoría de cierre (SEC-BE-001/002/003 + typo)

- [x] 8.1 **SEC-BE-001 (redacción)** — Retirar la premisa falsa de que el daño queda «acotado por la reversibilidad»: el core, con `force` verdadero, lee `$force` de la petición (`class-wp-rest-comments-controller.php:1071`) y ejecuta `wp_delete_comment($comment->comment_ID, true)` (`:1087-1089`), **destruyendo el comentario de forma irreversible**. Reescrito en `README.md` (riesgo residual y ventana de reversibilidad), `specs/valet-moderation/spec.md:17` y `:68`, `specs/valet-replies/spec.md:21` y `proposal.md:47,78` (la reversibilidad **solo** opera con `force=false`, es **disciplina del consumidor**; el control *server-side* queda como trabajo siguiente). D3 **no** cambia: el asistente sigue enviando **siempre** `force=false`.
- [x] 8.2 **SEC-BE-002 (control del sello por la ruta que lo lee)** — El caso `(e)` medía el camino `trash`, que sale por la guarda de estado **antes** de consultar `isServiceReply()`; su assert de sello afirmaba sobre la fábrica del arnés. Añadidos `(f)` transición **hacia `approved`** de una respuesta **sellada** → **cero** notificaciones y **cero** ranuras, y `(g)` **control positivo** sin sello → **una** notificación. **Mutación:** neutralizar la guarda (`if (false && self::isServiceReply($comment))`, `class-valet-config.php:718-723`) → `just test` **ROJO** con `(f)` en rojo y `(g)` **verde**: `Total passed: 311, Total failed: 10`, exit `1`. Restaurado: `sha256` **idéntico** `2dfc1e7b32f9d5ec81cd53285fe2a109380113b4d3cc6f17bbf602e9f6dc56c0`.
- [x] 8.3 **SEC-BE-003 (el registro del hook medido)** — El escenario 1.1 **re-registraba a mano** el filtro porque `harness_reset_state()` vaciaba `mock_filters`, así que borrar el `add_filter()` de producción **no** ponía la suite en rojo. Mecanismo elegido (el que menos ensucia el arnés): `harness_reset_state()` **preserva únicamente** el hook `rest_endpoints` registrado por el bootstrap (`atareao-functionality.php:204`) y se añade un stub `has_filter()` fiel; el test sustituye el `add_filter()` manual por `assert_true(has_filter('rest_endpoints', 'atareao_functionality_disable_rest_comment_endpoint'), …)` y aplica el filtro por `apply_filters`. Ningún otro escenario cambia (los demás hooks se siguen vaciando). **Mutación:** comentar la línea **204** de producción → `just test` **ROJO**: `Total passed: 310, Total failed: 11`, exit `1`, con **`(0) el bootstrap registra el filtro rest_endpoints de la colección de comentarios`** entre los fallos. Restaurado: `sha256` **idéntico** `a03fe554d88c5ce6a26c3d430a2c4104a256f17fa1c67a39310928424e8cc624`.
- [x] 8.4 **Typo de conjugación corregido.** Corregida la tercera persona errónea del verbo *retirar* en `README.md:449`, `specs/valet-replies/spec.md:21` y el docblock del filtro (`atareao-functionality.php:113`), sustituida por «retire». `grep` del error sobre el corpus del change → **vacío** (el delta de `valet-replies` se archiva a la spec canónica, así que el error se habría propagado).

## Notas de ejecución (evidencia de cierre)

- **Sin `design.md` (3/4 artefactos), por decisión del usuario.** El change está acotado a un
  filtro y su documentación; el contrato vive en los deltas de `valet-replies` / `valet-moderation`
  y en el README del plugin, y `openspec validate valet-comment-deletion --strict` pasa (exit `0`).
  No se añade `design` y no se reabre la decisión.
- **Receta de `just phpcs` (discrepancia medida).** Con `just 1.59.0` los argumentos con nombre
  **no se interpolan**: `just phpcs paths='wp-content/plugins/atareao-functionality'` falla con
  `ERROR: The file "paths=…" does not exist` (exit `3`). Hay que pasar el path **posicional**:
  `just phpcs wp-content/plugins/atareao-functionality`.
- **Cambio colateral del arnés (para el revisor).** El modo padre de `tests/test-valet.php` carga el
  **bootstrap real** del plugin (para ejercitar el filtro `rest_endpoints` tal como lo registra el
  sitio) y, por eso, `tests/class-matrix-config-stub.php` solo declara `MatrixConfig` si la clase no
  existe (`class_exists(…, false)`). El modo hijo —que nunca carga el bootstrap— sigue usando el stub
  grabador, así que los asserts de `matrix_calls` siguen midiendo el stub.
- **Discrepancia tasks↔medición en 1.1.** La nota de 1.1 anticipaba rojos «exactamente en (a), (d),
  (e) y (f)»; los rojos **medidos** contra el filtro previo fueron **5 asserts: (a) ×3 y (b) ×2**
  (con la ruta de colección eliminada siempre, el `GET` de la colección se conserva trivialmente y
  (e)/(f)/(g) —que afirman ausencia— también pasaban). Se ejecutó la matriz completa (a)–(g). La
  auditoría posterior (ver la nota siguiente) rehizo esta medición sobre la **forma real** del core:
  contra el filtro anterior los rojos pasan a ser **6** (se añade el recuento de **grupos de
  manejador** y la conservación **verbatim** de las opciones de ruta).
- **Defecto bloqueante cazado por la auditoría (árbol REST indexado por nombre de método) — reparado.**
  El filtro original indexaba el árbol por **nombre de método** (`unset($collection['GET'])`,
  `isset($collection['POST'])`), pero `rest_endpoints` **no** recibe `ruta => método => manejador`: la
  forma real es, por ruta, una **lista de grupos de manejador con clave numérica** (el método va en el
  campo `'methods'` de cada grupo) más las **opciones de ruta** en claves no numéricas. Citas del core
  7.1.3: `register_route()` guarda la forma (`wp-includes/rest-api/class-wp-rest-server.php:928-932`),
  `get_routes()` aplica el filtro **sobre esa forma** (`:974`) y normaliza **después** (`:998` mueve
  las opciones, `:1009-1010` admite `methods` como cadena con comas o como array); el controlador
  registra la colección `[0 => 'GET', 1 => 'POST', 'schema', 'namespace']`
  (`class-wp-rest-comments-controller.php:48-63`) y el elemento único
  `['args', 0 => 'GET', 1 => 'POST, PUT, PATCH', 2 => 'DELETE', 'schema']` (`:68-99`). Con la forma
  real el filtro anterior era un **no-op informativo**: `isset($collection['POST'])` resultaba
  **siempre falso** y la rama `else` **eliminaba la colección entera**, de modo que H1 seguía sin
  reparar (`POST /wp-json/wp/v2/comments` = `404 rest_no_route`, igual que `HEAD`). El arnés lo tapaba
  porque `harness_rest_route_table()` fabricaba `['GET' => …, 'POST' => …]`, forma que el core **no
  produce nunca**. Reparación: (1) el arnés reconstruye la forma real **citando la línea del core** en
  el propio auxiliar y añade `harness_rest_dispatchable_methods()` —traduce a «lo que el core
  despacharía»: recorre solo claves numéricas, parte `methods` por comas, admite array—; los tests
  afirman sobre esa traducción, no sobre el stub; (2) el filtro recorre el array por **clave
  numérica**, conserva las no numéricas **verbatim**, y conserva un grupo **solo si su `methods`
  contiene `POST` y no contiene `GET`**; si no sobrevive ningún grupo numérico, elimina la ruta
  entera. Evidencia literal: RED (arnés + tests sobre la forma real, filtro anterior) =
  `311 pass / 6 FAIL`, exit `1`; GREEN (filtro corregido) = `317 pass / 0 FAIL`, exit `0`.
- **Desplazamiento 313 → 317 asserts (SEC-BE-004).** La suite preexistente tenía **313** asserts; el
  escenario 3.1 gana **4** (los 3 de la **respuesta sellada**: el comentario de prueba lleva el sello
  `_atareao_valet_reply` —el objeto que de verdad se borra— y la transición a `trash` no notifica ni
  consume ranura; más el assert del propio sello, que impide un caso vacuo). Total **317**.
- **Corrección de redacción (decisión del usuario).** La frase que elevaba a **garantía del servidor**
  el «solo se retira lo que llegó por notificación» era **falsa** (la credencial tiene
  `moderate_comments` sobre la ruta de elemento único, que este change conserva a propósito). Se
  reescribió en el **delta** (`specs/valet-replies/spec.md`, `specs/valet-moderation/spec.md`), en
  `proposal.md`, en el **docblock** del filtro y en el **README**. Dato verificado: la frase falsa
  **no** estaba en la spec canónica `openspec/specs/valet-replies/spec.md` (solo decía «acepta y hace
  cumplir»); vivía en el delta, el proposal y el docblock. Como el delta **MODIFIED** copia el header
  exacto (`### Requirement: Contrato de respuesta del asistente`), el archivado propagará la
  corrección y no dejará la spec mintiendo. La redacción honesta conserva el cierre de la
  **enumeración** como garantía real y declara la restricción a «solo lo notificado» como **disciplina
  del consumidor** (la herramienta solo admite el identificador de la notificación) + **aprobación
  explícita**, con el **riesgo residual** explícito (credencial comprometida → recorrer IDs y retirar
  cualquier comentario, sin cota, reversible 30 días).
- **`valet-moderation` es ADDED.** No existe spec canónica previa (`openspec/specs/valet-moderation/`
  no está creada): su texto vive **únicamente** en el delta de este change, que es el único sitio del
  corpus donde hay que corregirlo. `valet-replies` sí tiene canónica, y su bloque MODIFIED es el
  mecanismo de propagación (nota anterior).
- **SEC-BE-001 — defecto de redacción (reversibilidad vendida como cota del sitio).** El core, en
  `delete_item()`, hace `$force = isset($request['force']) ? (bool) $request['force'] : false`
  (`class-wp-rest-comments-controller.php:1071`) y con `force` verdadero ejecuta
  `wp_delete_comment($comment->comment_ID, true)` (`:1087-1089`): **destrucción irreversible**, sin
  papelera ni ventana. Era falso, por tanto, que «el daño queda acotado por la reversibilidad» como
  propiedad del sitio. Reescrito en `README.md` (riesgo residual + sección «Ventana de reversibilidad»
  condicionada a `force=false`), en `specs/valet-moderation/spec.md:17` y `:68` (requirement
  «Desenlaces del borrado»), en `specs/valet-replies/spec.md:21` y en `proposal.md:47,78`: la
  reversibilidad **solo** opera mientras el consumidor **nunca** envíe `force=true` (**disciplina del
  consumidor**, no garantía del sitio), la **misma** credencial puede destruir cualquier comentario de
  forma irreversible y sin cota, y el control *server-side* (forzar `force=false` para el usuario de
  servicio, p. ej. en `rest_pre_dispatch`) queda como **trabajo siguiente**. **D3 no cambia**: el
  asistente sigue enviando siempre `force=false` (eso es de Valet).
- **SEC-BE-002 — el sello deja de medirse por un camino que no lo lee.** El caso `(e)` usaba la
  transición a `trash`, que sale por la guarda de estado (`class-valet-config.php:681`) **antes** de
  que `notifyApprovedComment()` consulte `isServiceReply()` (`:718-723`), de modo que sellado y no
  sellado eran indistinguibles allí; su único assert de sello afirmaba sobre la fábrica del arnés. Se
  añaden `(f)` sellado → `approved` (cero notificaciones, cero ranuras) y `(g)` no sellado → `approved`
  (una notificación, control positivo). El caso `(e)` se conserva como **caracterización del camino
  `trash`**, ya sin venderse como la medición del sello. **Falsación:** neutralizar la guarda
  `isServiceReply()` → `just test` **ROJO** (`Total passed: 311, Total failed: 10`, exit `1`), con
  `(f)` en rojo y `(g)` **verde**; restauración con `sha256` **idéntico**
  (`2dfc1e7b32f9d5ec81cd53285fe2a109380113b4d3cc6f17bbf602e9f6dc56c0`).
- **SEC-BE-003 — el registro del hook pasa a estar medido.** `harness_reset_state()` vaciaba
  `mock_filters` (`tests/wp-stubs.php:1019`), así que el escenario 1.1 **re-registraba a mano** el
  filtro y borrar el `add_filter()` de producción (`atareao-functionality.php:204`) **no** rompía nada.
  Mecanismo elegido —el menos invasivo—: el reset **preserva únicamente** el hook `rest_endpoints` que
  el bootstrap registró, y se añade un stub `has_filter()` fiel a WordPress; el test afirma
  `has_filter('rest_endpoints', 'atareao_functionality_disable_rest_comment_endpoint')` **sin**
  re-registrar y aplica el filtro con `apply_filters`. Ningún otro hook se preserva, así que los
  escenarios que dependen de una tabla vacía no cambian. **Falsación:** comentar la línea `204` de
  producción → `just test` **ROJO** (`Total passed: 310, Total failed: 11`, exit `1`), con
  `(0) el bootstrap registra el filtro rest_endpoints de la colección de comentarios` entre los fallos;
  restauración con `sha256` **idéntico** al de la mutación (`a03fe554…8cc624`).
- **Desplazamiento del recuento de asserts (re-auditoría).** De **317** a **321** asserts: **+1** por el
  assert del registro del hook (SEC-BE-003), **+2** por el par sellado→`approved` (SEC-BE-002 `(f)`) y
  **+1** por el control positivo `(g)`. El typo de SEC-BE-001/4 corrige texto del docblock sin añadir
  asserts (de ahí que `atareao-functionality.php` pase de `a03fe554…` al hash nuevo tras la corrección
  tipográfica, ya con la suite en verde).
- **Typo de conjugación.** Corregida la tercera persona errónea del verbo *retirar* en `README.md:449`,
  `specs/valet-replies/spec.md:21` (este último se **archiva** a la spec canónica, así que el error se
  habría propagado) y el docblock del filtro (`atareao-functionality.php:113`); el `grep` del error
  sobre el corpus del change queda **vacío**.
