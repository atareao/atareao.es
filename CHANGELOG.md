# Changelog
## [1.15.0] - 2026-10-05

### Bug Fixes

- *(mastodon)* Procesar las acciones en admin_init y sacar el registro de la app del render
- *(comentarios)* Escapar el autor y eliminar el innerHTML del JS de respuesta
- *(comentarios)* Alinear emisor y verificador del captcha y cubrir el formulario de contacto
- *(metaboxes)* No activar registerMetaFields (array post_type provoca TypeError fatal)
- *(metaboxes)* Sanear post_views_count con un cierre compatible con sanitize_meta
- *(cpt)* Ligar metadatos y taxonomias al CPT aplicacion
- *(views)* Post_views_count saneado a entero no negativo
- *(mcp)* Alinear el enum de post_type con la allowlist del dominio

### Documentation

- *(openspec)* Cerrar la E2E del pipeline de release
- *(openspec)* Cerrar las últimas tareas de los changes archivados
- *(openspec)* Proponer la absorción del importador de Mastodon
- *(openspec)* Sincronizar el diseño y las tareas con la implementación
- *(openspec)* Proponer el arreglo del XSS del autor de comentarios
- *(openspec)* Marcar las tareas verificadas del arreglo del XSS
- *(openspec)* Proponer el endurecimiento del endpoint MCP
- *(openspec)* Anotar los límites del MCP y marcar las tareas verificadas
- *(openspec)* Proponer el endurecimiento del bloque OpenGist
- *(openspec)* Proponer el cierre de la exposición de secretos
- *(openspec)* Archivar los cambios de seguridad y consolidar las specs
- *(openspec)* Archivar el change de respuestas de Mastodon
- *(openspec)* Propuestas de hardening del backlog medio/bajo
- *(openspec)* Marca tareas verificadas de ci-supply-chain
- *(openspec)* Elimina mención móvil @v4 en el delta de ci-supply-chain
- *(readme)* Sincronizar stack real (Podman/just), puertos 8091/8095 y eliminar credenciales de ejemplo
- Registrar procedencia, licencia e integridad del JS vendorizado y decisión SRI
- *(openspec)* Marcar tareas verificadas del change repo-hygiene
- Corregir hallazgos de revisión (loopback phpMyAdmin, rutas vendor, argv y rama fix/repo-hygiene)
- *(seguridad)* Documentar la rotación aceptada y el compromiso permanente
- *(openspec)* Corregir el baseline phpcs y sanear literales del árbol
- *(pocketid)* Documentar el endurecimiento del login passwordless y la preservación de REST
- *(pocketid)* Corregir la premisa falsa sobre Application Passwords en XML-RPC y ampliar la preservación
- *(openspec)* Documentar la normalización de errores de credenciales y registrar la tarea
- *(openspec)* Sincronizar las tareas de rest-blocks-hardening
- *(openspec)* Cerrar 3.2/3.3 de rest-blocks-hardening y anotar el delta PSR12
- *(openspec)* Alinear el contrato REST y el registro efectivo en init
- *(openspec)* Marcar metadata/registerMetaFields fuera de alcance
- *(antiabuse)* Documenta la limitación del captcha aritmético
- *(antiabuse)* Caracteriza el change y actualiza las tareas
- *(antiabuse)* Corrige la rama del PR a fix/antiabuse
- *(openspec)* Proponer el change metaboxes-meta-hardening
- *(openspec)* Archivar los 7 changes y consolidar las specs
- *(openspec)* Propuestas de form-challenge, views-sanitize y aplicacion-cpt
- *(openspec)* Archivar los 3 changes y consolidar las specs
- *(nginx)* Documentar TTL de caché de HTML de referencia (6h)
- *(plugin)* Documentar la capa WebMCP
- *(openspec)* Proposal del change webmcp-content-tools
- *(openspec)* Evidencias E2E del change webmcp-content-tools
- *(openspec)* Archivar webmcp-content-tools y consolidar specs
- *(openspec)* Archivar los changes de endurecimiento REST y contrato post_type
- *(openspec)* Cerrar la verificación de purga de caché del change WebMCP
- *(openspec)* Verificar 6.4 WebMCP en Chrome 154 (E2E real)
- *(openspec)* Sincronizar nota de seguimiento 6.4 WebMCP
- *(openspec)* Cerrar SEC-GEN-002 (rotación efectuada y verificada)
- *(openspec)* Proponer post-type-switcher y aparcar search-replace
- *(openspec)* Archivar post-type-switcher (verificado en producción)
- *(openspec)* Cerrar checklist de post-type-switcher

### Features

- *(mastodon)* Absorber el importador de respuestas de Mastodon en el plugin
- *(pocketid)* Endurecer la autenticación OIDC (passwordless real, vinculación por sub, nonce/id_token y secreto)
- *(forms)* Challenge renovable desde endpoint no cacheable
- *(mcp)* Filtrar por post_type y exponer metas públicas de CPT
- *(webmcp)* Registrar herramientas en el navegador sobre el MCP existente
- *(post-type-switcher)* Internalizar Post Type Switcher en atareao-functionality

### Miscellaneous Tasks

- *(wp-content)* Eliminar script de depuración debug-block.php
- *(openspec)* Marcar las tareas verificadas de pocketid-hardening
- *(openspec)* Corregir el baseline phpcs (752/429) y registrar las correcciones de la revisión
- *(openspec)* Marcar tareas verificadas y registrar evidencia de metaboxes-meta-hardening
- *(openspec)* Corregir la evidencia RED y registrar la revisión de metaboxes-meta-hardening
- *(openspec)* Registrar el E2E de producción verificado y las tareas pendientes de credenciales

### Refactor

- *(cache-purge)* Consolidar la normalización trim() del secreto
## [1.13.0] - 2026-10-03

### Bug Fixes

- *(ci)* Determine the release bump type deterministically
- *(ci)* Avoid SIGPIPE when the matching subject is first in a long list

### Documentation

- *(openspec)* Record the release-pipeline E2E verification
- *(openspec)* Archive release-bump-detection
- *(openspec)* Cerrar la verificación E2E de umami-analytics
- *(openspec)* Archivar umami-analytics
- *(openspec)* Proponer el hub de ajustes «Atareao»
- *(openspec)* Archivar settings-hub
- *(openspec)* Cerrar la E2E en producción de settings-hub

### Features

- *(analytics)* Integrar la analítica Umami en el plugin y retirar Integrate Umami
- *(settings)* Unificar la configuración del plugin en un hub con pestañas

### Miscellaneous Tasks

- Run the bump-type suite in CI and address review findings
- Release v1.13.0
## [1.12.0] - 2026-10-03

### Documentation

- *(openspec)* Write purpose for the pocketid-login capability
- *(openspec)* Archive ci-release-token-validation

### Miscellaneous Tasks

- Fail fast when GH_PAT is missing or invalid
- Release v1.12.0
## [1.11.0] - 2026-10-03

### Documentation

- *(openspec)* Archive pocketid-oidc-login and add pocketid-logout change
- *(openspec)* Add pocketid-logout-fixes change and update logout delta
- *(openspec)* Archive pocketid-logout and pocketid-logout-fixes
- *(openspec)* Archive pocketid-login-resilience

### Features

- *(pocketid)* Add PocketID OIDC login
- *(pocketid)* End provider session on logout (RP-initiated)
- *(pocketid)* Cache versioning, logout refresh and clean logged-out screen
- *(pocketid)* Login resilience (host-only state cookie, longer TTL, email policy)

### Miscellaneous Tasks

- Grant pull-requests write permission for release PRs
- Bump version to 1.10.5
- Release v1.11.0
## [1.10.0] - 2026-10-01

### Features

- Native Web Share API button, cache purge on update, nginx RSS exclusion

### Miscellaneous Tasks

- Sync development with merge instead of force push
- Release flow via PRs to support branch protection
- Release v1.10.0

### Styling

- Update Web Share API share icon in sprite
## [1.9.7] - 2026-10-01

### Bug Fixes

- Bump version 1.9.3 → 1.9.6

### Miscellaneous Tasks

- Release v1.9.7
## [1.9.3] - 2026-08-20

### Bug Fixes

- Php-fpm pool saturation, comment security params, contact form email validation
- Php-fpm pool saturation, comment security params, contact form email validation

### Miscellaneous Tasks

- Release v1.9.3
## [1.9.0] - 2026-08-15

### Features

- Bidirectional binding for timestamp/date inputs (#33)

### Miscellaneous Tasks

- Bump version to 1.7.2
- Bump version to 1.8.0 (#35)
- Release v1.9.0
## [1.8.0] - 2026-08-15

### Features

- Bidirectional binding for timestamp/date inputs (#33) (#34)

### Miscellaneous Tasks

- Release v1.8.0
## [1.7.1] - 2026-08-15

### Bug Fixes

- Timestamp converter timezone double-offset in dateToDatetimeLocal (#32)

### Miscellaneous Tasks

- Release v1.7.1
## [1.7.0] - 2026-08-15

### Features

- Add Gutenberg timestamp-helper block with standalone page (#30) (#31)

### Miscellaneous Tasks

- Release v1.7.0
## [1.6.14] - 2026-08-15

### Miscellaneous Tasks

- Fix release pipeline — use GH_PAT for git push and force-sync development (#29)
- Release v1.6.14
## [1.6.13] - 2026-08-15

### Miscellaneous Tasks

- Release v1.6.13
## [1.6.8] - 2026-07-30

### Miscellaneous Tasks

- Release v1.6.8

### Other

- V1.6.7

### Styling

- *(opengist-block)* Use data-filename on <pre> instead of separate header
- *(opengist-block)* Let theme's pre styles apply, filename right, editor text
- *(opengist-block)* Restore gradient bar in ::before, fix buttons z-index
- *(opengist-block)* Ubuntu dots, centered toggle, filename links in header and footer
- *(opengist-block)* Ubuntu dots, centered toggle, filename links in header and footer
## [1.6.4] - 2026-07-22

### Miscellaneous Tasks

- Release v1.6.4

### Styling

- *(opengist-block)* Restore gradient bar, fix buttons visibility (#24)
## [1.6.3] - 2026-07-22

### Miscellaneous Tasks

- Release v1.6.3

### Styling

- *(opengist-block)* Let theme's pre styles apply, filename right, editor text (#23)
## [1.6.2] - 2026-07-22

### Miscellaneous Tasks

- Release v1.6.2

### Styling

- *(opengist-block)* Use data-filename on <pre> instead of separate header (#22)
## [1.6.1] - 2026-07-22

### Bug Fixes

- *(opengist-block)* Render gist content via API with CSS fallback (#18)
- *(opengist-block)* Fetch raw content via embed script, bypass CSP (#19)

### Miscellaneous Tasks

- Release v1.6.1

### Styling

- *(opengist-block)* Terminal-style theme, collapsible at 100px (#20) (#21)
## [1.6.0] - 2026-07-22

### Features

- *(opengist-block)* Add OpenGist Gutenberg block with server config
- *(opengist-block)* Add OpenGist Gutenberg block with server config

### Miscellaneous Tasks

- Release v1.6.0

### Other

- V1.5.1
## [1.5.0] - 2026-07-20

### Features

- *(cache)* Add cache purge class and refactor contact form and matrix config
- *(cache)* Add cache purge class and refactor contact form and matrix config

### Miscellaneous Tasks

- Release v1.5.0

### Other

- V1.5.0
## [1.4.0] - 2026-07-06

### Bug Fixes

- *(comment-security)* Redirect on validation error instead of silent failure
- *(comment-security)* Redirect on validation error instead of silent failure

### Features

- *(seo)* Add SEO class, spam protection and accessible pagination

### Miscellaneous Tasks

- Release v1.4.0

### Other

- V1.3.3
## [1.3.0] - 2026-06-29

### Features

- Improve comment security, podcast post type and bump to v1.2.10
- Improve comment security, podcast post type and bump to v1.2.10

### Miscellaneous Tasks

- Release v1.3.0

### Other

- V1.2.10
## [1.2.6] - 2026-06-28

### Miscellaneous Tasks

- Release v1.2.6
## [1.2.2] - 2026-06-28

### Bug Fixes

- Always return views count in cached AJAX response and remove client-side cookie guard

### Other

- 1.2.2
## [1.2.1] - 2026-06-28

### Bug Fixes

- Remove is_singular check from metabox validation
- Update view counter in DOM after AJAX tracking (fixes cached page staleness)

### Miscellaneous Tasks

- Release v1.2.1

### Other

- 1.2.1
## [1.2.0] - 2026-06-22

### Features

- Add test message button to Matrix API config page
- Add test message button to Matrix API config page

### Miscellaneous Tasks

- Sync version after release v1.1.2
- Release v1.2.0

### Other

- 1.2.0
## [1.1.2] - 2026-06-20

### Bug Fixes

- Fix wp_add_inline_script typo and null guard for get_edit_post_link (#3)

### Miscellaneous Tasks

- Release v1.1.2
## [1.1.1] - 2026-06-20

### Miscellaneous Tasks

- *(ci)* Fix .vampus.yml format, manually bump to 1.1.0 and improve release-prepare
- Release v1.1.1
## [1.0.7] - 2026-06-20

### Features

- *(mcp)* Add MCP server implementation for AI tools interaction

### Miscellaneous Tasks

- *(gitflow)* Configure gitflow, changelog and CI workflows
- *(ci)* Make release-prepare version bump detection emoji-robust
- *(ci)* Fallback to GITHUB_TOKEN in release-prepare workflow
- Release v1.0.7

### Other

- Implementación de seguridad HMAC y una navegación de contenido gloriosamente optimizada para dejar de depender de sesiones obsoletas y metaboxes lentas.
- Limpiando el código basura para que parezca que alguien se molestó.
