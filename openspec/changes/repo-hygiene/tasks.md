# Tasks: Higiene del repositorio (artefactos, documentación y cadena de suministro JS)

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación de este change es de **limpieza y documentación**: análisis estático (`just php-lint` si algún PHP se toca), `just build` inspeccionando el contenido de los zips, comprobación de hashes (`sha256sum`) y revisión manual. La implementación arranca **solo tras la aprobación del usuario**. Ninguna tarea altera la lógica del plugin/tema, ni renombra/borra opciones, rutas REST ni hooks. Objetivo rector: **que el repositorio no arrastre artefactos de depuración, que su documentación diga la verdad y que el JS vendorizado sea auditable**.

> **Evidencia de implementación (worktree `fix/repo-hygiene`).**
> - `just php-lint` → 0 errores. Baseline `phpcs PSR12` (theme+plugin): **752 errores / 429 warnings en 70 ficheros** (sin cambios respecto al baseline: no se toca ningún PHP salvo la baja de `debug-block.php`, fuera de las rutas de lint).
> - `git rm wp-content/debug-block.php`; `git ls-files` y `ls` confirman su ausencia.
> - Zips: `just build` (`.justfile:181-239`) y `.github/workflows/release.yml` solo empaquetan `atareao-theme/` y `atareao-functionality/`; `debug-block.php` vive en la raíz de `wp-content/` y **no puede entrar** por construcción (no se ejecutó `just build` aquí porque su `BASE_DIR` apunta al repo principal y escribiría fuera del worktree; 2.3/2.4/5.2 quedan pendientes de verificación con el stack).
> - `README.md`: puertos `8091`/`127.0.0.1:8095`, sin `docker`/`docker-compose`, sin `8080`/`8081`, sin `ChangeMe123`; layout corregido a `wp-content/`.
> - `THIRD-PARTY.md`: registro con versión, origen, licencia y SHA-256 de `qrcode.min.js` (`c541ef06…`) y `js-yaml.min.js` (`45dc3dd0…`), decisión razonada sobre SRI y correspondencia de los `js/*.min.js` con sus fuentes. Los `.min.js` no cambian de contenido.
> - `openspec validate repo-hygiene --strict` → «Change 'repo-hygiene' is valid».
> - **Pendiente del usuario/stack:** 2.3, 2.4, 5.2 (ejecución real de `just build`/`curl`), 6.1 y 6.2 (PR y archivo).

## 1. Phase 0 — Línea base y caracterización

- [x] 1.1 Dejar constancia de la caracterización actual en el `proposal.md`/notas: `wp-content/debug-block.php` versionado y servido como `/wp-content/debug-block.php`; `README.md` con `docker`/`docker-compose` y puertos `8080`/`8081` (inexistentes) y `admin_password=ChangeMe123`; JS vendorizado sin registro de procedencia. **Verificación:** cada afirmación cita fichero:línea real. **Evidencia esperada:** proposal con las citas (`debug-block.php`, `README.md:61,100`, `assets/vendor/js-yaml.min.js`, `assets/blocks/crontab-helper/qrcode.min.js`).
- [x] 1.2 Fijar el baseline PSR12 antes de tocar nada (por si algún PHP se ve afectado). **Verificación:** `just php-lint` → 0 errores; `just phpcs` (theme+plugin) registra el par errores/warnings de partida. **Evidencia esperada:** baseline anotado (theme+plugin) y `just php-lint` → 0 errores.
- [x] 1.3 Confirmar cómo entra (o no) `debug-block.php` en los zips: revisar `just build` en `.justfile:181-239` y `.github/workflows/release.yml:18-35`. **Verificación:** el análisis muestra que ambos zips solo `atareao-theme/` y `atareao-functionality/`, por lo que el fichero de `wp-content/` raíz **no** entra por construcción; se anota como evidencia. **Evidencia esperada:** conclusión documentada con citas de las recetas.

## 2. GE-13 — Eliminar el script de depuración

- [x] 2.1 Eliminar `wp-content/debug-block.php` del control de versiones y del árbol de trabajo (`git rm`). **Verificación:** `git ls-files wp-content/debug-block.php` → vacío; el fichero no existe en el árbol. **Evidencia esperada:** ausencia confirmada por `git ls-files` y `ls`.
- [x] 2.2 Hacer explícita/verificable la exclusión en el empaquetado (si no lo está ya por construcción), de modo que ningún fichero suelto de `wp-content/` pueda entrar en un zip. **Verificación:** las recetas de `just build` y `release.yml` siguen zipsando solo los dos directorios; se añade, si procede, una comprobación del contenido. **Evidencia esperada:** recetas revisadas (y, si aplica, aserción de contenido).
- [ ] 2.3 Comprobar que `debug-block.php` no está en los zips generados. **Verificación:** `just build` y `unzip -l`/`unzip -Z1` sobre `atareao-theme.zip` y `atareao-functionality.zip` → el fichero no aparece. **Evidencia esperada:** salida de `unzip -l` sin `debug-block.php`.
- [ ] 2.4 Confirmar que la URL pública deja de servirse. **Verificación:** `curl -s -o /dev/null -w '%{http_code}' /wp-content/debug-block.php` en el entorno local → recurso no disponible (404). **Evidencia esperada:** código distinto de 200 y sin cuerpo del script (revisión manual; producción queda fuera de alcance).

## 3. GE-15 — Sincronizar la documentación de desarrollo

- [x] 3.1 Reescribir los comandos de operación del `README.md` con **Podman/quadlets/`just`** y eliminar las referencias a `docker`/`docker-compose`. **Verificación:** `rg -n "docker-compose|docker " README.md` → sin coincidencias operativas; los comandos usan `podman`/`just`. **Evidencia esperada:** `README.md` actualizado.
- [x] 3.2 Corregir los puertos a los reales: nginx `8091`, phpMyAdmin `127.0.0.1:8095`; actualizar las URLs locales (quick start de `core install`, `search-replace`, `home`/`siteurl`). **Verificación:** contraste con `quadlets/atareao-nginx.container:11` (`PublishPort=8091:80`) y `quadlets/atareao-phpmyadmin.container:7` (`PublishPort=127.0.0.1:8095:80`); `rg -n "8080|8081" README.md` → sin coincidencias de stack. **Evidencia esperada:** puertos y URLs corregidos.
- [x] 3.3 Eliminar todas las credenciales de ejemplo (`--admin_password=ChangeMe123` y similares) y sustituirlas por marcadores o provisión vía `podman secret`/entorno. **Verificación:** `rg -n "ChangeMe123|admin_password=" README.md` → sin credencial reutilizable. **Evidencia esperada:** sin credenciales de ejemplo.
- [x] 3.4 Corregir el layout documentado (`wp-content/`, `quadlets/`, `nginx/`, `.justfile`) y cualquier comando muerto. **Verificación:** contraste con el árbol real (`git ls-files`); `rg -n "wp/" README.md` no describe una ruta inexistente. **Evidencia esperada:** layout fiel al repositorio.

## 4. GE-16 / TB-04 — Trazabilidad e integridad del JS vendorizado

- [x] 4.1 Crear el **registro de procedencia** versionado (p. ej. `THIRD-PARTY.md` en la raíz y/o `README.md` junto a cada dependencia) con versión, origen upstream y licencia de cada JS de terceros. **Verificación:** el registro cubre `assets/blocks/crontab-helper/qrcode.min.js` y `assets/vendor/js-yaml.min.js` con esos tres campos. **Evidencia esperada:** registro creado y revisado.
- [x] 4.2 Registrar el **hash de integridad** (SHA-256) de cada JS de terceros tal como se sirve. **Verificación:** `sha256sum <fichero>` coincide con el valor del registro. **Evidencia esperada:** hashes calculados y anotados.
- [x] 4.3 Documentar la **decisión razonada sobre SRI**: recursos locales del mismo origen (el SRI no protege frente a la modificación del propio fichero), integridad cubierta por hash versionado; si se decide aplicar `integrity`, registrarlo y mantenerlo sincronizado. **Verificación:** la nota de SRI está presente y razonada; no hay `integrity` sin valor registrado. **Evidencia esperada:** sección de SRI documentada.
- [x] 4.4 Documentar los **minificados propios** del tema (`js/main.min.js`, `js/navigation.min.js`, `js/share.min.js`, `js/comment-ajax.min.js`) como derivados de sus fuentes `.js` versionadas (o su proceso). **Verificación:** el registro hace corresponder cada `.min.js` con su `.js` y liga su versión a la del tema. **Evidencia esperada:** correspondencia documentada.
- [x] 4.5 Confirmar que **ningún** minificado cambia de contenido con este change. **Verificación:** `git diff --stat` sobre los `.min.js` → sin cambios. **Evidencia esperada:** sin modificaciones en los minificados.

## 5. Verificación

- [x] 5.1 Análisis estático. **Verificación:** `just php-lint` → 0 errores (si algún PHP se ha tocado); `just phpcs` (theme+plugin) con delta **+0 errores** respecto al baseline de 1.2. **Evidencia esperada:** salida de `just php-lint`/`just phpcs`.
- [ ] 5.2 Contenido de los zips. **Verificación:** `just build`; `unzip -l atareao-theme.zip` y `unzip -l atareao-functionality.zip` no listan `debug-block.php` ni ficheros fuera de las dos carpetas. **Evidencia esperada:** listados del zip.
- [x] 5.3 Documentación. **Verificación:** `README.md` sin `docker`/`docker-compose`, sin `8080`/`8081` de stack y sin `ChangeMe123`; puertos `8091`/`8095`; registro de procedencia presente y con hashes correctos. **Evidencia esperada:** revisión manual y salidas de `rg`/`sha256sum`.
- [x] 5.4 No-regresión de contratos. **Verificación:** `git diff` no toca lógica de plugin/tema, opciones, rutas REST ni hooks; los `.min.js` no cambian de contenido. **Evidencia esperada:** diff acotado a documentación, `.gitignore`/recetas y eliminación de `debug-block.php`.
- [x] 5.5 Spec. **Verificación:** `openspec validate repo-hygiene --strict` sin hallazgos. **Evidencia esperada:** «Change 'repo-hygiene' is valid».

## 6. Entrega

- [ ] 6.1 PR por gitflow de `fix/repo-hygiene` a `development` con commits convencionales (gitmoji). **Verificación:** PR abierto/mergeado; `git log --oneline` muestra el cambio. **Evidencia esperada:** pendiente.
- [ ] 6.2 Marcar las tareas completadas y archivar el change. **Verificación:** todas las casillas marcadas; `openspec archive repo-hygiene` crea `openspec/specs/repo-hygiene/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** pendiente.
