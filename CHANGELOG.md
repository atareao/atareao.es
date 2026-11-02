# Changelog
## [1.20.0] - 2026-11-02

### Bug Fixes

- *(nginx)* Quitar el ancla rota de la regla xml/txt del mapa $skip_cache
- *(feed)* Tolerar la convención de llamada del núcleo y no duplicar el charset
- *(podcast-transcripts)* Endurecimiento tras la auditoría (GREEN)
- *(audio-sync)* Corregir el gancho de publicación y el respeto al contrato de reparación
- *(audio-sync)* El tope del cuerpo asfixiaba el feed real (nunca se construía el índice)
- *(transcripts)* Motivo utilizable cuando falla la transcripción
- *(mcp)* Elegir la era solo por metadatos de protocolo y aceptar notificaciones
- *(mcp)* La era del transporte no se decide por cabeceras (arregla la conexion de los agentes MCP)
- *(mcp)* Aceptar clientCapabilities ausente y eximir /.well-known/mcp
- *(mcp)* Elegir la era solo por metadatos de protocolo y aceptar notificaciones
- *(mcp)* Aceptar clientCapabilities ausente y eximir /.well-known/mcp

### Documentation

- *(openspec)* Proponer el change podcast-feed-namespace (namespaces, audio y transcripción SRT)
- *(openspec)* Cerrar la preparación de podcast-feed-namespace
- *(openspec)* Proponer el change mcp-registry-auth (prueba de dominio y recurso protegido)
- *(openspec)* Marcar 0.x/1.x y registrar la evidencia RED de mcp-registry-auth
- *(openspec)* Registrar la evidencia GREEN y REFACTOR de mcp-registry-auth
- *(openspec)* Registrar la verificación 4.1-4.3 de mcp-registry-auth
- *(openspec)* Proponer el change article-structured-data (entidad de artículo en el grafo JSON-LD)
- *(openspec)* Marcar la fase RED y fijar los contratos del grafo de artículo
- *(openspec)* Registrar las fases GREEN y REFACTOR del grafo de artículo
- *(openspec)* Registrar la verificación (escapado, cotejo schema.org y E2E local)
- *(openspec)* Marcar 1.x y registrar la evidencia RED de podcast-feed-namespace
- *(openspec)* Ampliar la tarea 1.14 a la exención de las transcripciones SRT
- *(openspec)* Registrar la evidencia GREEN y REFACTOR de podcast-feed-namespace
- *(openspec)* Registrar la verificación de podcast-feed-namespace y sincronizar el proposal
- *(spec)* Fijar la tolerancia de los callbacks y el media type del feed
- *(spec)* Anotar la evidencia de la corrección de los defectos del feed
- *(spec)* Cerrar la verificación E2E de los datos estructurados
- *(spec)* Resolver SEC-BE-002 y documentar su efecto colateral medido
- *(spec)* Cerrar el E2E de A y C sobre el código desplegado
- *(agents)* Poda del AGENTS.md (−70,0 %) y sección Git Flow
- *(spec)* Proponer el change podcast-audio-length
- *(spec)* Cerrar el E2E en producción y documentar el despliegue
- *(spec)* Cerrar 4.4 y 4.5 del registro MCP con la prueba servida
- *(spec)* Cerrar 4.6 del registro MCP (publicación de es.atareao/blog@1.19.0)
- *(podcast-transcripts)* Delta ampliado tras la auditoría y evidencia del ensayo real
- *(podcast-transcripts)* Marcar la limitación del Content-Type servido (4.3)
- *(podcast-transcripts)* Mapear cada subtarea RED a su método de test y cerrar 5.1
- *(podcast-transcripts)* Checkpoint PLAN.md, deuda aceptada y decisión del titular
- *(podcast-transcripts)* PR #141 abierto y lote completo en curso
- *(podcast-transcripts)* Corregir el arranque del lote al dato real del kernel
- *(podcast-transcripts)* Punto de escritura real y reanudación del lote (4.1)
- *(podcast-transcripts)* Lote detenido por el titular (23/829) y hallazgo de validación
- *(podcast-transcripts)* Reanudar el lote reutiliza el checkpoint (4.1)
- *(podcast-transcripts)* El rechazo de SRT es determinista y la tasa real es ~1,1 %
- *(podcast-transcripts)* Evidencia E2E del lote completo — 829 de 838 episodios con transcripción en el feed
- *(openspec)* Archivar podcast-transcripts y publicar su spec canónica
- *(podcast-audio-sync)* Reencauzar el change al audio y cerrar la decisión del titular
- *(podcast-audio-sync)* Partir la sección Why para no exceder el límite de openspec
- *(podcast-audio-sync)* Reescribir Why para no exceder el límite de openspec
- *(audio-sync)* Evidencia E2E en producción y defecto B3
- *(audio-sync)* Evidencia E2E de la escritura real (4.2–4.5)
- *(podcast-transcribe-diagnostics)* Spec del diagnóstico de un fallo de transcripción
- *(transcripts)* Evidencia RED/GREEN/REFACTOR/E2E del diagnóstico utilizable
- *(transcripts)* Marcar 5.2 (push + PR #144 a development)
- *(transcripts)* Marcar 5.3 (archive autorizado) y la deuda aceptada 6.x
- *(transcripts)* Archivar podcast-transcribe-diagnostics (autorizado)
- *(plan)* Cerrar el frente A y registrar los dos changes en borrador
- *(plan)* Frente B implementado y auditado (140/0) — E2E pendiente de despliegue
- *(plan)* Frente C implementado, auditado y con E2E hecha (37/0 + 1068/0)
- *(openspec)* Archivar podcast-audio-sync y publicar su spec canónica
- *(audio-sync)* Cerrar la tarea 4.6 con el lote real de transcripción
- *(mcp)* Delta del change mcp-era-selection (la era no se decide por cabeceras)
- *(mcp)* Evidencia RED/GREEN del change y checkpoint del frente D en PLAN.md
- *(mcp)* El delta fija que la validacion precede a la aceptacion de una notificacion
- *(mcp)* Evidencia de la auditoria y del endurecimiento en tasks.md
- *(mcp)* Evidencia del E2E en produccion (desplegado y verificado) en tasks y PLAN
- *(mcp)* Archivar mcp-era-selection tras el E2E verificado y anotar las lecciones aprendidas
- *(plan)* Cerrar el frente D (PR #148 fusionado y change archivado)
- *(podcast-srt-upload)* Propuesta y plan del frente D
- *(podcast-srt-upload)* Evidencia RED, GREEN y REFACTOR
- *(podcast-srt-upload)* E2E verificada en producción
- *(podcast-srt-upload)* PR #154 abierto

### Features

- *(reg)* Prueba de dominio y recurso protegido para el registro de MCP
- *(sd)* Añadir la entidad Article al grafo JSON-LD de The SEO Framework
- *(feed)* Vocabulario de podcasting, audio y transcripción en el feed del podcast
- *(feed)* Resolver el tamaño real del audio remoto del enclosure
- *(podcast-transcripts)* Pipeline batch de transcripciones (GREEN)
- *(audio-sync)* Módulo PodcastAudio — autorrelleno de mp3-url desde el RSS del origen
- *(srt-upload)* Aceptar .srt en la biblioteca y en el selector

### Miscellaneous Tasks

- *(git)* Ignorar las claves privadas del flujo del registro MCP

### Refactor

- *(podcast-transcripts)* Observabilidad y guarda de descarga vacía
- *(mcp)* Quitar la rama muerta y blindar la era moderna con tests de caracterizacion
- *(mcp)* Quitar la rama muerta y blindar la era moderna con tests de caracterizacion

### Testing

- *(reg)* Fase RED de la prueba de dominio y el recurso protegido MCP
- *(sd)* Escenarios en rojo para la entidad de artículo en el grafo JSON-LD
- *(feed)* Fase RED de los namespaces, el audio y la transcripción del feed
- *(feed)* Exigir que las transcripciones SRT sean alcanzables por máquinas
- *(reg)* Fijar la exclusión de caché de las rutas del registro MCP
- *(reg)* Fijar que ninguna asignación sombrea el mapa $skip_cache
- *(reg)* Fijar que la regla xml/txt casa con la clave real del mapa
- *(feed)* Medir la ruta real del hook y la cabecera compuesta
- *(feed)* Arnés del tamaño remoto del audio en el enclosure
- *(podcast-transcripts)* Pruebas RED del pipeline de transcripciones
- *(podcast-transcripts)* RED de la remediación de la auditoría
- *(audio-sync)* Arnés RED con los 27 escenarios del delta
- *(transcripts)* Describir el diagnóstico utilizable de un fallo de transcripción
- *(mcp)* La cabecera Mcp-Method no selecciona la era y la notificacion se acepta con 202
- *(podcast-srt-upload)* RED del registro del tipo y del selector
## [1.19.0] - 2026-10-30

### Bug Fixes

- *(ard)* Declarar displayName en la entrada y cerrar cuatro tests huecos

### Documentation

- *(openspec)* Archivar el change mcp-interop-fixes en las specs canónicas
- *(plan)* Refrescar el checkpoint PLAN.md al estado final del change mcp-interop-fixes
- *(openspec)* Proponer el change ai-catalog-enrichment (migración a la ruta canónica ARD)
- *(openspec)* Corregir la entry del manifiesto ARD (displayName es obligatorio)

### Features

- *(ard)* Servir el manifiesto ARD canónico y anunciarlo por sus canales

### Miscellaneous Tasks

- Release v1.19.0

### Testing

- *(ard)* Escenarios en rojo para la ruta canónica ARD y sus canales de descubrimiento
## [1.18.0] - 2026-10-29

### Bug Fixes

- *(mcp)* Seleccionar la era por los metadatos de protocolo y completar Allow en el preflight

### Documentation

- *(openspec)* Propuesta del change mcp-discovery
- *(openspec)* Archivar el change mcp-discovery
- *(openspec)* Marcar verificada la tarea 6.5 del change mcp-discovery

### Features

- *(mcp)* Descubrimiento estándar, endpoint limpio /mcp y transporte dual-era

### Miscellaneous Tasks

- *(nginx)* Versionar la configuración de nginx de producción
- Release v1.18.0

### Testing

- *(mcp)* Cobertura del descubrimiento, dual-era y conformidad del protocolo
## [1.17.0] - 2026-10-24

### Bug Fixes

- *(valet)* Resolver hallazgos de auditoría (test no falsable, sufijo, recorte byte-safe)
- *(valet)* Reparar por identidad el cierre de la colección de comentarios y caracterizar el borrado reversible
- *(valet)* Parea la ruta concreta del core en la guarda (5.3)
- *(valet)* Acota también la desaprobación en la guarda (hold)

### Documentation

- *(openspec)* Archivar theme-image-alignment (18/20, con residual trazado)
- *(openspec)* Spec de valet-notifications y hub de seis pestañas
- *(valet)* Documentar la pestaña Valet en el README del plugin
- *(valet)* Documentar el endurecimiento y verificar por mutación los cuatro controles
- *(valet)* Documentar el canal inverso de respuestas en el README del plugin
- *(openspec)* Proponer valet-comment-deletion con sus deltas de spec
- *(valet)* Documentar el contrato de borrado con papelera y ventana de 30 días
- *(valet)* Documenta la garantía del sitio en el contrato de borrado
- *(openspec)* Cierra los artefactos del change valet-server-side-control
- *(openspec)* Archiva el change valet-server-side-control en las specs canónicas
- *(openspec)* Rellena el Purpose de las specs seo, image-handling y main-feed

### Features

- *(valet)* Integrar notificaciones con Valet
- *(valet)* Canal inverso direccionable con sellado de procedencia y cota de escritura
- *(valet)* Escribe la marca de procedencia al notificar (5.1-5.2)
- *(valet)* Guarda de procedencia y degradación de force (5.3-5.5)

### Miscellaneous Tasks

- *(tooling)* Receta just test, check-spec con validación openspec y bump de build justificado
- *(valet)* Línea base del harness y correcciones A1–A3
- *(openspec)* Archivar valet-notifications-hardening tras la verificación final
- *(openspec)* Archivar valet-comment-replies tras la verificación final
- *(openspec)* Archivar valet-comment-deletion (23/24, prueba de punta a punta bloqueada)
- Release v1.17.0

### Testing

- *(valet)* Harness procedural con ejecución real del formulario
- *(valet)* Cubrir huecos de cobertura B1–B10
- *(valet)* Añade el escenario de reescritura de force del arnés
- *(valet)* 🔴 añade escenarios en rojo de la degradación de force (2.3-2.5)
- *(valet)* 🔴 añade escenarios de la guarda de procedencia (2.6-2.9)
- *(valet)* 🔴 añade escenarios de la segunda puerta de retirada (3.1-3.5)
- *(valet)* 🔴 añade escenarios de la marca de procedencia (4.1-4.5)
- *(valet)* 🔴 modela la ruta concreta del core en el arnés (fidelidad)
- *(valet)* 🔴 acota la desaprobación del usuario de servicio (3.6-3.7)
- *(valet)* 🔴 mide la cobertura canónica de la retirada (3.9-3.10)
## [1.16.1] - 2026-10-12

### Bug Fixes

- *(theme)* Fijar los tamaños de imagen al activar el tema, no en cada petición

### Documentation

- *(openspec)* Archivar pocketid-security-hardening y cerrar search-replace-block-editor
- *(openspec)* Evidencias y correcciones de theme-plugin-boundary
- *(openspec)* Archivar theme-plugin-boundary (verificado en producción)
- *(openspec)* Proponer theme-image-alignment y retractar una observación falsa
- *(images)* Partir la documentación de imágenes por la frontera tema/plugin

### Miscellaneous Tasks

- *(openspec)* Proponer theme-plugin-boundary
- Release v1.16.1

### Refactor

- *(boundary)* Mover al plugin la política de imágenes, la descripción SEO y el feed
## [1.16.0] - 2026-10-12

### Documentation

- *(pocketid)* Documentar la vinculación por sub y el pre-provisioning
- *(pocketid)* Documentar la Logout Callback URL como prerrequisito

### Features

- *(pocketid)* Endurecer el login OIDC con Pocket ID

### Miscellaneous Tasks

- *(openspec)* Añadir el change pocketid-security-hardening
- Release v1.16.0
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
- Release v1.15.0

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
