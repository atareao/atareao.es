# AGENT DIRECTIVES: OPENSPEC (SDD) + TDD WORKFLOW

## ⚠️ REGLA DE ORO — LEER ANTES DE ACTUAR

**ANTES de escribir o editar CUALQUIER archivo de código fuente (Rust, TypeScript, JSX, CSS, etc.),
debes ejecutar `just check-spec` para confirmar que existe un change proposal aprobado.**

Si `just check-spec` falla:
1. DETENTE inmediatamente.
2. Informa al usuario que no hay un change proposal activo.
3. Pregunta si quiere crear uno con `openspec new change <feature>`.
4. NO escribas código hasta recibir aprobación explícita.

**SALTARSE ESTE PASO ES VIOLACIÓN DEL PROTOCOLO.**

---

## I. CORE PRINCIPLES & GOALS

- **Phase 0 — Legacy Support:** If modifying existing code without specs or tests, establish a baseline spec and characterization tests before introducing changes.
- **Phase 1 — SDD (OpenSpec):** No new code or tests may be written before a spec change proposal exists in `openspec/changes/<feature>/` and is approved by the user.
- **Phase 2 — TDD (Red-Green-Refactor):** Once the spec is approved, code MUST be developed strictly test-first using terminal commands.
- **Strict Verification:** Always run CLI test suites using terminal tools. Never assume code or tests pass/fail without CLI confirmation.

---

## II. EXECUTION WORKFLOW

### Phase 0: Legacy Code Preparation (Conditional)

*Execute this phase ONLY if modifying an existing module/file that lacks OpenSpec documentation or tests.*

1. **Characterization Spec (As-Is):**
   - Inspect the target file/module.
   - Generate a baseline spec in `openspec/specs/<module>/spec.md` reflecting current behavior.
2. **Characterization Tests:**
   - Write Rust (`#[test]`) or React/TS (`vitest` / `@testing-library/react`) tests matching current behavior.
   - Run tests via CLI (`cargo test` or `npx vitest run`) to confirm all pass in **GREEN**.

### Phase 1: SDD Protocol (OpenSpec)

When the user requests a new feature, bug fix, or refactor:

1. **Create the Change Proposal:**
   - Execute CLI command: `openspec new change <feature-name>`
2. **Draft Specifications:**
   - Populate `openspec/changes/<feature-name>/proposal.md` with intent, scope, and impact.
   - Create spec deltas in `openspec/changes/<feature-name>/specs/<module>/spec.md`.
   - Ensure the spec includes:
     - **Contracts:** Rust types/structs/enums, TypeScript interfaces/props, API endpoints, or function signatures.
     - **Scenarios (BDD style):** Detailed `Given / When / Then` clauses for happy path, error cases, and edge cases.
   - Populate `openspec/changes/<feature-name>/tasks.md` with the TDD task checklist.
3. **STOP & WAIT FOR APPROVAL:**
   - Present the created specification to the user.
   - **DO NOT** write application code or new tests until the user explicitly approves the spec.

### Phase 2: TDD Protocol (Red-Green-Refactor)

Once the user approves the spec (e.g., "Approved", "Looks good", "Proceed with TDD"):

1. **RED (Write Failing Tests):**
   - Read the `Given / When / Then` scenarios in `openspec/changes/<feature-name>/specs/`.
   - Write tests in Rust or React/TypeScript corresponding to those scenarios.
   - Execute CLI tests (`cargo test` or `npx vitest run`).
   - **Verify:** Confirm test failure for the new functionality while any legacy tests remain **GREEN**.
2. **GREEN (Minimal Implementation):**
   - Write the absolute minimum code necessary to satisfy the failing tests.
   - Execute CLI tests (`cargo test` or `npx vitest run`).
   - Run type checks (`cargo check` or `npx tsc --noEmit`).
   - **Verify:** Confirm all tests pass (100% green) and no compilation/type errors exist.
3. **REFACTOR (Clean & Consolidate):**
   - Clean up code formatting, types, and structure without altering behavior.
   - Run linters (`cargo clippy -- -D warnings` / `npm run lint`).
   - Re-run test suites via CLI to guarantee no regressions.
4. **CONSOLIDATE & ARCHIVE:**
   - Mark completed items in `tasks.md`.
   - Once all scenarios pass, run `openspec archive <feature-name>` to merge the delta into `openspec/specs/`.

### Practical Lessons Learned (SDD + TDD)

#### Archive requires exact header matching
`openspec archive` busca el header exacto del delta en la spec destino. Si el header del delta es `"### Requirement: Pipeline evaluation order (WAF first)"` pero la spec tiene `"### Requirement: Pipeline evaluation order"`, el archive falla. **Los headers del delta deben copiar EXACTAMENTE los de la spec destino.**

#### Si reescribes la spec directamente, no intentes archivar
Si modificaste `openspec/specs/<module>/spec.md` a mano (fuera del mecanismo de archive), el change proposal correspondiente queda huérfano. No se puede archivar porque los headers ya no coinciden. **Solución: eliminar el directorio del change proposal** (`rm -rf openspec/changes/<feature>/`).

#### Cambios en cascada
Eliminar una entidad (ej. Whitelist/Blacklist) puede dejar código muerto en otras partes (ej. `AppError::Conflict`, tests de Conflict). El REFACTOR phase debe incluir la limpieza de estos artefactos. **Siempre ejecutar `cargo clippy -- -D warnings` tras el GREEN phase para detectar código/ variantes no usados.**

#### `openspec archive --yes` no bypassa validación de headers
La flag `--yes` salta la comprobación de tareas incompletas, pero NO la validación de que los headers del delta existan en la spec destino. Si los headers no matchean, el archive igual falla.

#### Mantén openspec artifacts sincronizados con el código
Si implementas un cambio en código pero no actualizas los artifacts de openspec (tasks, proposal), el change proposal queda "stuck" — no se puede archivar ni continuar. **Antes de empezar un nuevo cambio, verifica que no haya cambios activos huerfanos con `openspec list`.**

---

## III. PROJECT CONFIGURATION & CONVENTIONS

### Stack Commands

#### Backend: Rust
- **Test Runner:** `cargo test` (or `cargo nextest run` if available).
- **Type Checking & Linting:** `cargo check` and `cargo clippy -- -D warnings` (enforce zero warnings).
- **Formatting:** `cargo fmt --check`
- **Conventions:**
  - Structs and types placed in domain modules or `src/models/`.
  - Unit tests placed in the same file under `#[cfg(test)]`.
  - Integration and API tests placed in `tests/`.

#### Frontend: React + TypeScript
- **Test Runner:** `npx vitest run` or `npm test -- --watch=false` (single-pass execution).
- **Type Checking:** `npx tsc --noEmit` (mandatory during GREEN/REFACTOR steps).
- **Linting & Formatting:** `npm run lint` / `npx eslint .`
- **Conventions:**
  - Components in `src/components/`, hooks in `src/hooks/`.
  - Component tests colocated as `Component.test.tsx` using `@testing-library/react`.
  - User-centric testing behavior using `@testing-library/user-event` instead of implementation details.

### Custom Repository Rules

#### Desarrollo: Podman
El proyecto usa **Podman** como runtime de contenedores para desarrollo local.
- `just dev` → `podman compose up -d --build` (reconstruye imagen + arranca)
- `just dev-docker` → alternativa con Docker
- El binario de Podman está en `/usr/bin/podman`
- Las imágenes se construyen con `podman compose build`

#### Entorno de producción
- `docker-compose.prod.yml` despliega con frontend separado (nginx) + PocketID
- `docker-compose.yml` es para desarrollo con frontend embebido

---

## IV. RESPONSE FORMAT & STATUS MESSAGES

Always prefix your progress updates with the current status tag:

```text
[LEGACY - INSPECT] Creating baseline spec & characterization tests.
[OPENSPEC - DRAFT] Generating change proposal in openspec/changes/...
[OPENSPEC - WAITING] Spec generated. Awaiting user review and approval.
[TDD - RED] Creating tests for scenario <Name> -> Running CLI tests.
[TDD - GREEN] Implementing minimal code -> Running CLI tests & type checks.
[TDD - REFACTOR] Refactoring code -> Running Clippy/ESLint & tests.
[OPENSPEC - ARCHIVE] Archiving change into openspec/specs/.
```


## V. CURRENT PROJECT STATE

atareao.es WordPress site with a Podman quadlet dev stack and `just` task runner.

### Prerequisites

- **fish shell** — all `just` recipes use `#!/usr/bin/env fish`.
- **podman** — containers run rootless as user systemd units.
- **crypta** — required for `just install` (podman secret creation).
- **just** — command runner. Run `just --list` to see all recipes.

### Architecture

- Only `wp-content/` is tracked. WordPress core lives in containers — never edit core files in containers.
- Theme: `wp-content/themes/atareao-theme/` (handwritten PHP, monolithic `style.css`, no framework).
- Plugin: `wp-content/plugins/atareao-functionality/` (registers 5 CPTs, Gutenberg block, `/tools/` microsite with rewrite rules).
- **Separation rule:** all functionality and business logic goes in the plugin. The theme is for presentation only — templates, styles, and front-end scripts.
- Quadlets: `quadlets/` — systemd container units (`.container`, `.network`, `.volume`). Symlinked into `~/.config/containers/systemd/` by `just install`.
- Nginx: `nginx/` — config snippets symlinked into `~/.config/nginx/`. Acts as reverse proxy to the WordPress FPM container.
- PHP-FPM overrides: `php-fpm/zz-atareao-performance.conf` — bind-mounted into the WordPress container.

### Dev environment

```
just install   # link quadlets + nginx config, create podman secrets
just start     # start all services
just stop      # stop all services
just status    # show link/run status per container
```

Theme and plugin directories are bind-mounted into the WordPress container — edits reflect immediately with no rebuild.

Containers: wordpress (FPM), mariadb, nginx (port 8080), valkey (Redis-alternative cache), phpmyadmin (port 8081), php-cli (persistent workspace).

### Coding standards

**No build tools exist.** No `package.json`, `composer.json`, webpack, or CSS preprocessors. All JS and CSS are edited directly as source files.

```bash
just php-lint          # lint all PHP files
just php-lint-changed  # lint only git-changed PHP files
just phpcs             # PSR12 check (default paths: theme + plugin)
just phpcbf            # auto-fix PSR12 violations
```

- PSR12 is the enforced standard.
- PHP version: 8.3 (matches `wordpress:cli-php8.3` image).
- Gutenberg block JS uses vanilla `wp.element.createElement` — no JSX or transpile step.
- Plugin version constant: `ATAREAO_PLUGIN_VERSION` in `atareao-functionality.php`.

### Running commands

```bash
just php -- -l path/to/file.php          # lint single file
just php-shell                           # interactive shell (requires php-cli running)
just wp -- search-replace 'old' 'new'    # WP-CLI (requires wordpress+mariadb running)
just logs service=atareao-wordpress      # journalctl follow for a service
```

### Building for distribution

```bash
just build   # creates atareao-theme.zip and atareao-functionality.zip in repo root
```

Zip files are gitignored.

### Important gotchas

- **No tests.** No phpunit, no Jest, nothing. Manual verification only.
- **No autoloader.** PHP classes are manually `require_once`'d. Keep includes in sync.
- **Secret-dependent.** WP-CLI commands depend on `podman secret` + `crypta`. If secrets are missing, re-run `just install`.
- **The plugin is a microsite.** Custom rewrite rules for `/tools/crontab/`, `/tools/uuid/`, etc. are in `class-post-types.php` and served from `templates/`. Do not delete those templates without updating rewrite rules.
- **Matrix protocol integration.** Contact form (`includes/class-contact-form.php`) and comment notifications (`includes/class-matrix-config.php`) go to Matrix (not email). Credentials stored as WP options. Both classes live in the plugin. The theme's `page-contact.php` is the presentation template only — no business logic.
- **No i18n files.** `Text Domain` headers are declared but no `.po`/`.mo` files exist.
- **Volumes are persistent.** Use `just clean_volumes` to wipe DB and WP data. Bind-mounts (theme/plugin) are not affected.
- **PHP-FPM config is bind-mounted.** `php-fpm/zz-atareao-performance.conf` is mounted into the WordPress container as `/usr/local/etc/php-fpm.d/`. If deleted, the container may fail to start.

### Git Flow

This project follows strict gitflow. See [GIT_FLOW.md](./GIT_FLOW.md) for:
- Branch structure (main, development, feature/*, hotfix/*)
- Conventional commits with gitmoji
- How to create features, hotfixes, and releases

