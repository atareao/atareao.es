# Tasks: Cadena de suministro de CI/CD (referencias inmutables)

> **Nota inicial:** el repositorio no tiene framework de tests ni build tools. La verificación de este change combina revisión de YAML, `actionlint` **si está disponible**, búsquedas `rg` de que no quedan referencias móviles, un **PR real** (check `ci.yml`) y el **siguiente release real** (publicación E2E). La implementación arranca **solo tras la aprobación del usuario**. **Restricción de alcance:** este change NO revisa, reduce ni modifica los permisos del `GH_PAT`, ni altera el flujo de release/publicación; solo fija referencias a valores inmutables. El token sigue con `contents: write` + `pull-requests: write`.

## 1. Inventario y baseline de referencias

- [x] 1.1 Inventariar todas las referencias de terceros de los tres workflows con fichero:línea y valor actual. **Verificación:** `rg -n "uses:" .github/workflows/` y anotar `ci.yml:11,14`, `release.yml:16,31`, `release-prepare.yml:34,41,46,49`. **Evidencia esperada:** lista completa de acciones y el `cargo install vampus --git ...` sin `--tag`/`--rev`.
- [x] 1.2 Anotar el baseline de comportamiento para no-regresión: eventos `on:`, bloques `permissions:`, `with:` de cada paso, uso de `secrets.GH_PAT` y artefactos publicados. **Verificación:** revisión manual de los tres YAML; `rg -n "permissions:|GH_PAT|on:" .github/workflows/`. **Evidencia esperada:** baseline registrado antes de tocar nada.

## 2. Fijar acciones de terceros a SHA de commit

- [x] 2.1 Resolver el SHA de commit completo (40 hex) de la versión vigente de cada acción, **sin inventarlos**: `actions/checkout` (v4), `shivammathur/setup-php` (v2), `taiki-e/install-action` (v2), `swatinem/rust-cache` (v2) y `softprops/action-gh-release` (v2). **Verificación:** cada SHA se obtiene de la API/UI de GitHub para el tag de versión resuelto. **Evidencia esperada:** tabla acción → versión legible → SHA.
- [x] 2.2 Fijar en `ci.yml` `actions/checkout` y `shivammathur/setup-php` a su SHA con comentario de versión (`# vX.Y.Z`). **Verificación:** `rg -n "uses:" .github/workflows/ci.yml` muestra `@<40-hex> # vX.Y.Z`; ningún `@v4`/`@v2`. **Evidencia esperada:** diff con solo el cambio de referencia.
- [x] 2.3 Fijar en `release.yml` `actions/checkout` y `softprops/action-gh-release` a su SHA con comentario de versión, conservando intactos los `with:` (ficheros publicados y `generate_release_notes`). **Verificación:** `rg -n "uses:|files:|generate_release_notes" .github/workflows/release.yml`. **Evidencia esperada:** referencias fijadas y `with:` sin cambios.
- [x] 2.4 Fijar en `release-prepare.yml` `actions/checkout`, `taiki-e/install-action` y `swatinem/rust-cache` a su SHA con comentario de versión, conservando intactos los `with:` de cada paso. **Verificación:** `rg -n "uses:" .github/workflows/release-prepare.yml`. **Evidencia esperada:** referencias fijadas y `with:` sin cambios.
- [x] 2.5 Confirmar que no queda ningún `uses:` con tag o rama móvil. **Verificación:** `rg -n "uses:\s+\S+@(v[0-9]+|main|master)\b" .github/workflows/` no devuelve resultados. **Evidencia esperada:** solo referencias a SHA de 40 hex.

## 3. Fijar `vampus` a una revisión concreta

- [x] 3.1 Determinar el último tag de release de `https://github.com/atareao/vampus` y su SHA de commit. **Verificación:** consulta al repositorio/API. **Evidencia esperada:** versión/tag elegido y su SHA.
- [x] 3.2 Sustituir `cargo install vampus --git https://github.com/atareao/vampus` por la forma fijada (`--tag <vX.Y.Z>` o `--rev <sha>`), documentando la versión/revisión elegida junto al paso. **Verificación:** `rg -n "cargo install vampus" .github/workflows/release-prepare.yml` muestra `--tag`/`--rev`; `rg -n "cargo install vampus --git [^ ]+$"` no devuelve la forma móvil. **Evidencia esperada:** paso fijado y reproducible.
- [x] 3.3 Confirmar que `git-cliff` (vía `taiki-e/install-action`) hereda el fijado inmutable de la acción ya fijada en 2.4. **Verificación:** revisión del paso `Install git-cliff`. **Evidencia esperada:** sin instalación adicional por tag móvil.

## 4. Política de actualización documentada

- [x] 4.1 Documentar dónde se anotan las versiones legibles, cómo obtener el SHA de una versión y cómo actualizar la revisión de `vampus`, en la ubicación elegida (nota/README de CI). **Verificación:** la política es localizable y menciona las cinco acciones y `vampus`. **Evidencia esperada:** sección de política redactada.
- [x] 4.2 Documentar la verificación exigida antes de fusionar una actualización: revisión del YAML y `actionlint` cuando esté disponible, sin exigir build tools ni tests. **Verificación:** revisión de la política. **Evidencia esperada:** pasos de verificación indicados.

## 5. Preservación del flujo de release y publicación

- [x] 5.1 Confirmar que el diff **no** modifica los eventos `on:`, los bloques `permissions:` ni el uso de `secrets.GH_PAT` en ninguno de los tres workflows. **Verificación:** `rg -n "permissions:|GH_PAT|on:" .github/workflows/` coincide con el baseline de 1.2 y `git diff -- .github/workflows/` no muestra cambios en `on:`/`permissions:`. **Evidencia esperada:** solo cambian las referencias `uses:` y el paso de `vampus`.
- [x] 5.2 Confirmar que `release-prepare.yml` sigue usando `GH_PAT` con `contents: write` y `pull-requests: write` y que la lógica de validación, bump, changelog, tag/PR y sync no cambia. **Verificación:** revisión del diff de `release-prepare.yml`. **Evidencia esperada:** tokens y lógica intactos.
- [x] 5.3 Confirmar que `release.yml` sigue publicando `atareao-theme.zip` y `atareao-functionality.zip` con `contents: write`. **Verificación:** revisión del diff de `release.yml`. **Evidencia esperada:** pasos y artefactos intactos.

## 6. Verificación estática

- [x] 6.1 Parsear los tres workflows para descartar YAML inválido. **Verificación:** `python3 -c "import yaml; [yaml.safe_load(open(f)) for f in ['.github/workflows/ci.yml','.github/workflows/release.yml','.github/workflows/release-prepare.yml']]"` con exit 0. **Evidencia esperada:** sin excepción.
- [x] 6.2 Ejecutar `actionlint` **si está disponible** sobre los tres workflows. **Verificación:** `command -v actionlint && actionlint .github/workflows/*.yml`; si no está disponible, registrar que no se pudo ejecutar y continuar con el parseo de 6.1. **Evidencia esperada:** sin hallazgos o constancia de no disponibilidad. **Evidencia:** `actionlint` no estaba disponible en el entorno de implementación (`command -v actionlint` vacío); se aplicó el fallback de 6.1 (parseo YAML correcto).
- [x] 6.3 Comprobar con `rg` que no quedan referencias móviles (acciones con `@vN`/`@main` y `vampus` sin `--tag`/`--rev`). **Verificación:** los dos `rg` de 2.5 y 3.2 sin resultados inesperados. **Evidencia esperada:** cero referencias móviles.
- [x] 6.4 Validar los artefactos OpenSpec. **Verificación:** `openspec validate ci-supply-chain` sin hallazgos. **Evidencia esperada:** «Change 'ci-supply-chain' is valid».

## 7. Verificación en PR real (requiere PR / ejecución real de GitHub Actions)

> **Pendiente de prueba del usuario:** estas tareas requieren abrir un PR real y ejecutar GitHub Actions; el subagente no hace push, PR ni merge.

- [ ] 7.1 Abrir un PR por gitflow contra `development` con el fijado y comprobar que el check `ci.yml` sigue ejecutándose y en verde con las acciones fijadas a SHA. **[requiere prueba en un PR real]** **Verificación:** revisión del run de `ci.yml` en el PR (PHP lint + suite de bump). **Evidencia esperada:** run completado en verde.
- [ ] 7.2 Confirmar en el PR que `release-prepare.yml` **no** se dispara (solo corre en push a `main`/`workflow_dispatch`) y que el PR no recibe secretos. **Verificación:** lista de checks del PR. **Evidencia esperada:** sin ejecución ni exposición del `GH_PAT`.
- [ ] 7.3 Revisar que el diff del PR es exclusivamente el fijado de referencias (y el paso de `vampus`), sin cambios en `on:`, `permissions:` ni `GH_PAT`. **Verificación:** `git diff` del PR. **Evidencia esperada:** diff acotado.

## 8. Verificación E2E del release (requiere un release real; no se puede provocar con un run espurio)

> **Pendiente de prueba del usuario:** estas tareas requieren el siguiente release real; no se pueden provocar con un run espurio.

- [ ] 8.1 En el siguiente release real, confirmar que `release-prepare.yml` valida el `GH_PAT` (HTTP 200), instala `vampus` desde la revisión fijada y crea la rama/tag/PR igual que antes. **[requiere prueba en un release real]** **Verificación:** revisión del run en GitHub Actions. **Evidencia esperada:** pipeline de preparación completado.
- [ ] 8.2 Confirmar que el tag `v*` dispara `release.yml` y publica `atareao-theme.zip` y `atareao-functionality.zip` con las acciones fijadas a SHA. **[requiere prueba en un release real]** **Verificación:** revisión del run y del GitHub Release. **Evidencia esperada:** release publicado con ambos zips.
- [ ] 8.3 Confirmar la sincronización `main -> development` por PR igual que antes. **[requiere prueba en un release real]** **Verificación:** revisión del PR de sync creado. **Evidencia esperada:** PR de sync con el flujo actual.

## 9. Entrega

- [x] 9.1 Marcar las tareas completadas en `tasks.md` y actualizar el estado del change conforme avance la implementación. **Verificación:** casillas marcadas solo con evidencia real. **Evidencia esperada:** tasks sincronizadas.
- [ ] 9.2 Archivar el change una vez verificado el E2E. **Verificación:** `openspec archive ci-supply-chain` fusiona el delta en `openspec/specs/release-pipeline/spec.md`; `openspec list` ya no muestra el change activo. **Evidencia esperada:** spec actualizada y change archivado.
