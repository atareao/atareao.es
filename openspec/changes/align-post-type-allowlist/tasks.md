# Tasks: Alinear el contrato de `post_type` con la lista permitida

> Sin framework de tests ni build tools. Verificación por arnés externo + estáticos. La implementación arranca **solo tras la aprobación**.

## 1. Línea base
- [x] 1.1 Baseline `just php-lint` y `just phpcs --standard=PSR12 --report=summary`.
- [x] 1.2 Evidencia con el arnés RED: `post_type=page` es aceptado hoy (debe pasar a `-32602`).

## 2. RED — arnés externo (falla con el código actual)
- [x] 2.1 Extender el arnés PHP del MCP (stubs) con un check: `post_type=page` → `-32602`. Debe fallar en RED.
- [x] 2.2 Mantener verdes los checks ya existentes (podcast válido, `application` → `-32602`, sin argumento = todos).

## 3. GREEN — implementación mínima
- [x] 3.1 `class-mcp.php`: constante `ALLOWED_POST_TYPES` (seis tipos) y `postTypeArgument()` valida contra ella; el valor por defecto sigue usando `publicPostTypes()`.
- [x] 3.2 Arnés PHP en **VERDE** para todos los escenarios de la spec.

## 4. REFACTOR
- [x] 4.1 Reutilizar la constante en un único punto; documentar la asimetría en el PHPDoc de `postTypeArgument()`.
- [x] 4.2 `just php-lint` 0; `phpcs` sin nuevos errores.

## 5. Verificación
- [x] 5.1 `openspec validate align-post-type-allowlist` → valid.
- [x] 5.2 Revisión: el `enum` de `webmcp.js` y la lista del servidor coinciden (seis tipos).
- [x] 5.3 AUD-BE-003: el `enum` de `post_type` anunciado por el servidor (`tools/list`) usa `ALLOWED_POST_TYPES` (sin `page`), alineado con la validación. Evidencia: arnés externo **9/9 checks en VERDE** (incluye el escenario «El esquema anunciado coincide con la lista permitida»); RED aislado 8/9 (solo fallaba el check del enum) y RED contra prístino 6/9.

## 6. E2E en producción (tras despliegue, los hace el usuario)
- [ ] 6.1 `search_posts {post_type:page}` → `-32602`.
- [ ] 6.2 `search_posts {post_type:podcast}` → solo podcasts (sin regresión).
- [ ] 6.3 `get_latest_posts` sin `post_type` → varios tipos públicos (sin regresión).

## 7. Entrega
- [x] 7.1 Sincronizar este `tasks.md`.
- [ ] 7.2 PR por gitflow a `development` (commits convencionales con gitmoji).
- [ ] 7.3 `openspec archive align-post-type-allowlist`.
