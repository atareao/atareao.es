# Proposal: Higiene del repositorio (artefactos, documentación y cadena de suministro JS)

## Why

Tres hallazgos de la auditoría (GE-13, GE-15 y GE-16/TB-04) afectan a la **limpieza y a la trazabilidad del repositorio**, no a la lógica de WordPress:

1. **GE-13 (LOW) — Script de depuración servido públicamente.** `wp-content/debug-block.php` está **versionado** y, al vivir en la raíz pública de `wp-content`, se sirve como `/wp-content/debug-block.php`. Es un script de depuración (`WP_Block_Type_Registry`, constantes y rutas internas del plugin) que no tiene ninguna función en el sitio: si en el futuro incluyera `wp-load.php`, revelaría información interna. Debe salir del repositorio y garantizarse que **no entre en los artefactos de distribución**.

2. **GE-15 (INFO) — Documentación desactualizada y con credenciales de ejemplo.** El `README.md` describe un stack que no existe en el repo: habla de `docker-compose` y de puertos `8080`/`8081`, cuando solo hay **quadlets** de Podman que publican **8091** (nginx) y **8095** (phpMyAdmin, en loopback desde `infrastructure`). Además usa `--admin_password=ChangeMe123` como ejemplo, un password reutilizable que invita a copiarlo en instalaciones reales. La documentación de desarrollo debe describir el stack real y no contener credenciales de ejemplo.

3. **GE-16 / TB-04 (INFO) — JavaScript vendorizado sin procedencia ni integridad.** Hay JS de terceros minificado y versionado sin versión, origen ni licencia documentados y sin `integrity`: `assets/blocks/crontab-helper/qrcode.min.js` **no tiene ni cabecera de licencia** y `assets/vendor/js-yaml.min.js` solo lleva un comentario interno (`js-yaml 4.1.0`, MIT) que no forma parte de ningún registro auditable. Los minificados propios del tema (`js/*.min.js`) tampoco documentan su correspondencia con las fuentes `.js`. Sin este registro no se puede auditar la cadena de suministro front-end ni detectar versiones vulnerables.

**Naturaleza del change.** Es un change **mayormente de documentación y limpieza**. Su único efecto observable es que `debug-block.php` deja de servirse (cierre de la superficie pública); el resto corrige documentación y añade trazabilidad sin tocar la lógica de la aplicación. Sí hay, por tanto, un **delta de comportamiento mínimo** (desaparición de un recurso público), razón por la que esta propuesta crea la capability `repo-hygiene` y se archivará de forma normal (`openspec archive repo-hygiene`); **no** se archivará con `--skip-specs` porque el delta es real y merece conservarse.

## What Changes

- **GE-13 — Eliminar el script de depuración.**
  - Borrar `wp-content/debug-block.php` del control de versiones y del árbol de trabajo.
  - Verificar y **hacer explícito** que los dos empaquetados (`just build` y `.github/workflows/release.yml`) zips **solo** el tema `atareao-theme/` y el plugin `atareao-functionality/`; ningún fichero suelto de `wp-content/` (ni de depuración) debe entrar en un zip. Si el empaquetado actual ya lo excluye por construcción, se deja constancia y se añade la comprobación de contenido del zip.
  - Confirmar que `/wp-content/debug-block.php` deja de existir y de servirse.

- **GE-15 — Sincronizar la documentación de desarrollo.**
  - Reescribir en el `README.md` los comandos de operación con **Podman/quadlets/`just`**; eliminar las referencias a `docker`/`docker-compose` (no existen en el repo).
  - Corregir los puertos a los **reales**: nginx en `8091`, phpMyAdmin en `127.0.0.1:8095` (loopback); no `8080`/`8081`. Actualizar las URLs locales de `core install` y de `search-replace` en consecuencia.
  - Eliminar **todas** las credenciales de ejemplo (`--admin_password=ChangeMe123` y similares) y sustituirlas por marcadores o por provisión vía `podman secret`/variable de entorno; no dejar passwords por defecto copiables.
  - Corregir el layout documentado (`wp-content/`, `quadlets/`, `nginx/`, `.justfile`) y cualquier comando muerto.

- **GE-16 / TB-04 — Documentar la procedencia del JS vendorizado y razonar el SRI.**
  - Añadir un **registro de procedencia** versionado (por ejemplo `THIRD-PARTY.md` en la raíz del repo y/o un `README.md` junto a cada dependencia) que, para cada JS de terceros, indique **versión exacta, URL/origen upstream, licencia y hash de integridad** (SHA-256 del fichero tal como se sirve). Cubre al menos `qrcode.min.js` y `js-yaml.min.js`.
  - Documentar la **decisión razonada sobre SRI**: al ser recursos locales del mismo origen, el SRI no protege frente a la modificación del propio fichero en el servidor; la integridad se cubre con el hash versionado y, si se decide aplicar `integrity`, debe registrarse y mantenerse. Nada de SRI silencioso o desincronizado.
  - Documentar los **minificados propios** del tema (`js/*.min.js`) como derivados de sus fuentes `.js` versionadas (o su proceso de minificación), de modo que la fuente legible sea la referencia y la versión quede ligada a la versión del tema.

## Capabilities

### New Capabilities

- `repo-hygiene`: limpieza de artefactos del repositorio (eliminación del script de depuración y garantía de que no entra en los zips de distribución), fidelidad de la documentación de desarrollo al stack real (Podman/quadlets/`just`, puertos correctos, sin credenciales de ejemplo) y trazabilidad de la cadena de suministro JavaScript (versión, origen, licencia e integridad del JS vendorizado, decisión razonada sobre SRI y correspondencia de los minificados propios con sus fuentes).

### Modified Capabilities

Ninguna.

## Impact

- **Ficheros a tocar (solo en la fase de implementación, tras aprobación):**
  - `wp-content/debug-block.php` — **eliminación** del control de versiones.
  - `README.md` — comandos, puertos, URLs, layout y eliminación de credenciales de ejemplo.
  - Registro de procedencia nuevo: `THIRD-PARTY.md` (raíz) y/o `README.md` en `wp-content/plugins/atareao-functionality/assets/vendor/` y en `assets/blocks/crontab-helper/`.
  - `.justfile` (`build`) y `.github/workflows/release.yml` — **solo si** hace falta hacer explícita la exclusión/verificación; el empaquetado actual ya zips únicamente tema y plugin (verificado: `zip -r … atareao-theme` / `atareao-functionality`).
  - Nuevo (spec al archivar): `openspec/specs/repo-hygiene/spec.md` a partir del delta `specs/repo-hygiene/spec.md`.
- **Rutas y contratos que NO se tocan:** la lógica del plugin/tema, los bloques Gutenberg, el micrositio `/tools/`, la analítica, el login, las notificaciones Matrix ni la configuración nginx salvo lo estrictamente documental. No se renombra ni se borra ninguna opción, ruta REST ni hook.
- **Compatibilidad:** ninguno de los minificados cambia de contenido; solo se documenta. El único cambio observable es que `/wp-content/debug-block.php` deja de servirse (no lo consume nadie).
- **Dependencias:** ninguna nueva. No hay build tools ni framework de tests; la verificación es análisis estático (`just php-lint` si aplica), `just build` inspeccionando el contenido del zip, comprobación de hashes y revisión manual.
- **Compatibilidad técnica:** PHP 8.3, PSR12, WordPress 6.0+, Podman/quadlets en desarrollo.
- **Fuera de alcance:** este change **no toca producción** ni los hallazgos de severidad Alta/Media (GE-01, GE-02, GE-03, GE-04, TB-01, TB-02), que se abordan en sus propios changes.
