# AGENT DIRECTIVES: OPENSPEC (SDD) + TDD WORKFLOW

## ⚠️ REGLA DE ORO — LEER ANTES DE ACTUAR

**ANTES de escribir o editar CUALQUIER archivo de código fuente (Rust, TypeScript, JSX, CSS, etc.) debes ejecutar `just check-spec` para confirmar que existe un change proposal aprobado.**

Si `just check-spec` falla: DETENTE, informa de que no hay change proposal activo, pregunta si crear uno con `openspec new change <feature>`, y NO escribas código hasta recibir aprobación explícita.

**SALTARSE ESTE PASO ES VIOLACIÓN DEL PROTOCOLO.**

---

## Protocolo SDD + TDD

Sin change proposal aprobado en `openspec/changes/<feature>/` no se escribe código ni tests: `openspec new change <feature-name>`, rellena `proposal.md` + `specs/<module>/spec.md` + `tasks.md` y **espera aprobación explícita**. TDD: RED → GREEN (mínimo código + type check) → REFACTOR (lint + re-test) → `openspec archive <feature-name>`. Quirks: headers del delta EXACTOS a la spec destino o `archive` falla; spec a mano ⇒ `rm -rf openspec/changes/<feature>/`. Verifica por CLI. Etiquetas:

```text
[LEGACY - INSPECT] Creating baseline spec & characterization tests.
[OPENSPEC - DRAFT] Generating change proposal in openspec/changes/...
[OPENSPEC - WAITING] Spec generated. Awaiting user review and approval.
[TDD - RED] Creating tests for scenario <Name> -> Running CLI tests.
[TDD - GREEN] Implementing minimal code -> Running CLI tests & type checks.
[TDD - REFACTOR] Refactoring code -> Running Clippy/ESLint & tests.
[OPENSPEC - ARCHIVE] Archiving change into openspec/specs/.
```

---

## Estado actual del proyecto

WordPress + Podman quadlets + `just`. Solo `wp-content/` está trackeado; **el core vive en contenedores — nunca editarlo.** Requisitos: fish, podman, crypta, just. Theme = presentación; plugin = 5 CPTs + bloque Gutenberg + microsite `/tools/`; **la funcionalidad va en el plugin.**

```
just install   # link quadlets + nginx config, create podman secrets
just start     # start all services
just stop      # stop all services
just status    # show link/run status per container
```

**No hay build tools** (ni `package.json`, `composer.json`, webpack ni preprocesadores); JS/CSS se editan como fuente. **PSR12**; PHP 8.3; Gutenberg con `wp.element.createElement` (sin JSX).

```bash
just php-lint          # lint all PHP files
just php-lint-changed  # lint only git-changed PHP files
just test              # run the procedural test harness (tests/test-valet.php)
just phpcs             # PSR12 check (default paths: theme + plugin)
just phpcbf            # auto-fix PSR12 violations
```

Contenedores: wordpress, mariadb, nginx (8080), valkey, phpmyadmin (8081), php-cli; theme/plugin bind-mounted.

```bash
just php -- -l path/to/file.php          # lint single file
just php-shell                           # interactive shell (requires php-cli running)
just wp -- search-replace 'old' 'new'    # WP-CLI (requires wordpress+mariadb running)
just logs service=atareao-wordpress      # journalctl follow for a service
```

```bash
just build   # creates atareao-theme.zip and atareao-functionality.zip in repo root
```

**Gotchas:** sin test framework ni autoloader (solo `tests/test-valet.php`; `just test` propaga exit code, 0 = todo pasa; `require_once` manual). WP-CLI necesita `podman secret` + crypta. Microsite: rewrite rules `/tools/...` en `class-post-types.php` → `templates/` (no borrar templates sin actualizar reglas). Matrix (contact form, notificaciones) → Matrix, no email. `just clean_volumes` borra DB/WP. Git flow: `GIT_FLOW.md`.

## Git Flow

Este proyecto sigue **Git Flow** estricto. Ver [GIT_FLOW.md](./GIT_FLOW.md):

- Estructura de ramas: `main`, `development`, `feature/*`, `hotfix/*`.
- Conventional commits con **gitmoji**.
- Cómo crear features, hotfixes y releases.

---

## Dónde está el resto

| Tema | Fichero |
| --- | --- |
| Protocolo SDD+TDD y lecciones (literal) | `docs/sdd-tdd-workflow.md` |
| Estado del proyecto (literal) | `docs/project-state.md` |
| Git flow | `GIT_FLOW.md` |
