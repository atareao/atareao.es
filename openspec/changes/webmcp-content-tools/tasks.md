# Tasks: Capa WebMCP de consulta estructurada

> Sin framework de tests ni build tools. Verificación por arnés externo + estáticos + E2E por red. La implementación arranca **solo tras la aprobación** (aprobada 2026-10-04).

## 1. Línea base
- [x] 1.1 Baseline `just php-lint` (0 errores) y `just phpcs --standard=PSR12 --report=summary`: global **758 errores / 421 warnings**. Verificado.
- [x] 1.2 Evidencia del MCP actual: el arnés RED demuestra que `search_posts` no filtra por `post_type` (devuelve los 6 tipos), `post_type=application` no da `-32602` y `formatPost` no incluye metas de CPT.
- [x] 1.3 API WebMCP vigente verificada: `document.modelContext` (movida desde `navigator.modelContext` el 21-jul-2026), `registerTool`/`unregisterTool` (sin `provideContext`). Fuente: spec W3C CG Draft + notas de migración.

## 2. RED — arnés externo (falla con el código actual)
- [x] 2.1 Arnés JS `/tmp/opencode/webmcp-harness/js/check-tools.mjs` (node:vm, stub de `document.modelContext`, modo FIXTURE no-vacuo) → **0/10** (fichero `webmcp.js` ausente).
- [x] 2.2 Arnés PHP `/tmp/opencode/webmcp-harness/php/run.sh` (stubs de WordPress) → **2/5** (no filtra `post_type`, `application` no da `-32602`, sin metas de CPT).
- [x] 2.3 `just php-lint` en 0 y arneses previos verdes durante el RED.

## 3. GREEN — implementación mínima
- [x] 3.1 `class-mcp.php`: `post_type` opcional validado contra `publicPostTypes()` en `search_posts`/`get_latest_posts`; inválido → `-32602` sin consultar; ausente = todos. Arnés PHP check 2 y 3 en verde.
- [x] 3.2 `class-mcp.php`: `formatPost` añade `meta` (lista blanca `PUBLIC_METAS` + `post_views_count`) y `taxonomies` (solo públicas); nunca claves `_`. Arnés PHP checks 4 y 5 en verde.
- [x] 3.3 `includes/class-webmcp.php` (nuevo): `init()` engancha `wp_enqueue_scripts` (nunca admin), encola el JS y localiza `AtareaoWebMCP.endpoint`; sin nonce.
- [x] 3.4 `assets/js/webmcp.js` (nuevo): wrapper `document.modelContext` → `navigator.modelContext` → no-op; 3 tools como datos con `inputSchema` + `annotations` (`readOnlyHint`, `untrustedContentHint`); `execute` = `fetch` JSON-RPC `tools/call` con errores estructurados.
- [x] 3.5 `atareao-functionality.php`: `require_once` + `\Atareao\WebMCP::init()`; `ATAREAO_PLUGIN_VERSION` 1.13.0 → **1.14.0**.
- [x] 3.6 Arneses en **VERDE**: JS **10/10**, PHP **5/5**; `just php-lint` 0; `node --check` OK.

## 4. REFACTOR
- [x] 4.1 Wrapper único de API y tools como datos; la lógica de búsqueda no se duplica en JS.
- [x] 4.2 `just php-lint` 0; `phpcs` global **758 errores / 422 warnings** → errores **+0** (el +1 warning es `PSR1.Files.SideEffects` del guard `ABSPATH`, patrón de todos los includes del plugin); `node --check` OK.
- [x] 4.3 `README.md` del plugin: sección «WebMCP (herramientas en el navegador)».

## 5. Verificación
- [x] 5.1 `openspec validate webmcp-content-tools` → **valid**.
- [x] 5.2 Revisión de seguridad: `auditor-backend` → **0 vulnerabilidades** (2 INFO); `auditor-frontend` → **0 vulnerabilidades** (3 INFO, **aplicadas**).

## 6. E2E en producción (tras despliegue)
- [ ] 6.1 Por red: `search_posts` con `post_type=podcast` devuelve solo podcasts; `post_type=application` → `-32602`.
- [ ] 6.2 Por red: un `podcast` devuelve `mp3-url`/`number`/`season`; un `capitulo`, `numero-capitulo`; sin claves `_`.
- [ ] 6.3 JS servido `200` y encolado en front-end (no en admin).
- [ ] 6.4 (Pendiente si no hay navegador) Registro real de tools en Chrome con WebMCP; si no hay navegador, se documenta como **no verificado**.

## 7. Entrega
- [ ] 7.1 Sincronizar este `tasks.md`.
- [ ] 7.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 7.3 `openspec archive webmcp-content-tools`.

## 8. Evidencia observada (2026-10-04)

- **Arnés JS** (`node /tmp/opencode/webmcp-harness/js/check-tools.mjs`, MODE=REPO): `Summary: 10/10 checks passed` (3 tools, schemas, enum `post_type`, sobre JSON-RPC, errores estructurados, sin `provideContext`/`clearContext`, fallback `navigator`, no-op sin API).
- **Arnés PHP** (`bash /tmp/opencode/webmcp-harness/php/run.sh`): `Summary: 5/5 checks passed` (filtro `post_type=podcast`; `application` → `-32602`; metas de podcast presentes; sin fuga de `_download_url`).
- **`just php-lint`** → 0 errores; **`node --check assets/js/webmcp.js`** → OK.
- **`phpcs` PSR12**: global 758/422 (baseline 758/421) → errores **+0**.
- **`openspec validate webmcp-content-tools`** → valid.
- **Revisiones**: backend 0 vuln (SEC-BE-001 preexistente fuera de alcance; SEC-BE-002 coherencia enum, sin fuga); frontend 0 vuln (3 NOTAs aplicadas: guard de `fetch`, `id` incremental, devolver `data.result`).

## 9. Fuera de alcance / follow-ups registrados
- **SEC-BE-001** (preexistente, no tocado): exención de auth por `strpos` de subcadena en `atareao_functionality_rest_auth_errors`. Candidato a change propio (comparar ruta parseada exacta).
- **SEC-BE-002**: el `post_type` del servidor acepta todo tipo público (`page` incluido) mientras el `enum` del JS lista los seis CPT del dominio. Sin impacto de seguridad; candidato a alinear lista explícita + enum.
- **SEC-GEN-002** (preexistente): rotación de secretos pendiente.
