# Tasks: mcp-registry-auth

## 0. Preparación

- [x] 0.1 Confirmado: el patrón de servido es `\Atareao\MCPDiscovery::serve` sobre
      `template_redirect` (prioridad por defecto 10). Los dos documentos nuevos se sirven desde un
      **módulo propio** (`\Atareao\MCPRegistry`) con el mismo contrato —plan `status/headers/body`— y se
      registran a prioridad **0**, para resolverse antes que cualquier otra cosa que mire la ruta
- [x] 0.2 Confirmado: la fuente de verdad del `issuer` es la opción `atareao_pocketid_url`
      (`\Atareao\PocketIDLogin::OPTION_URL`). `PocketIDLogin::isConfigured()` ya la lee con
      `get_option(self::OPTION_URL, '')`; sin ella, el recurso protegido no se publica
- [x] 0.3 Confirmado en el arnés: `home_url`, `get_option`, `wp_json_encode` y `has_filter` ya existían.
      **No** hay dobles de `header`/`status_header`/`has_action`/`register_setting`, así que la suite
      mide por la API **pura** (`responseFor()`, `proofLine()`…) y por `$GLOBALS['mock_actions']`, igual
      que las suites hermanas. Se añadieron dos dobles que faltaban: `is_ssl()` y `wp_parse_url()`.
      Toda llamada al módulo va guardada con `class_exists`/`method_exists`, de modo que su ausencia da
      un `FAIL:` legible y nunca un fatal
- [x] 0.4 Hecho y documentado: `openssl genpkey -algorithm ED25519` + `openssl pkey -pubin -outform DER`;
      la clave **cruda** son los últimos 32 bytes del SPKI (`tail -c 32`), `p` = Base64 de esos 32 bytes.
      Par de prueba generado en `/tmp/opencode/mcp-keys` y **borrado** tras extraer la línea. Verificado:
      la variable de entorno del sistema es OpenSSL 3.6.5. **La clave privada no ha entrado al repo ni a
      `/tmp` al terminar**; el repo solo contiene un `p` **sintético** de 32 bytes (`AAECAwQF…Hh8=`) como
      fixture de los tests, que no corresponde a ninguna clave real

## 1. RED — los tests primero, y que fallan

- [x] 1.1 `GET /.well-known/mcp-registry-auth` → `200`, `text/plain; charset=utf-8`, cuerpo = línea de prueba
- [x] 1.2 La línea de prueba empieza por `v=MCPv1; k=`, declara algoritmo admitido y clave pública Base64
- [x] 1.3 Sin prueba configurada → `404` (y sin cuerpo de prueba)
- [x] 1.4 La respuesta no contiene ninguna clave privada; el cuerpo solo lleva la clave pública
- [x] 1.5 La constante de prueba tiene precedencia sobre la opción
- [x] 1.6 `GET /.well-known/oauth-protected-resource` → `200`, `application/json`
- [x] 1.7 `resource` es el URI canónico del endpoint (minúsculas, sin barra final)
- [x] 1.8 `authorization_servers` contiene el emisor configurado; sin proveedor → `404`
- [x] 1.9 `bearer_methods_supported` incluye `header`
- [x] 1.10 Ninguno de los dos documentos expone secretos ni enumera herramientas, recursos ni prompts
- [x] 1.11 El documento de recurso **no** cambia la política de acceso (herramientas anónimas siguen `200`)
- [x] 1.12 `server.json` es JSON válido; `name` = `es.atareao/blog`; remoto `streamable-http` a `/mcp`;
      nombre/versión/URL alineados con la tarjeta de servidor
- [x] 1.13 `nginx/pro/nginx.conf`: el mapa de exención cubre las dos rutas nuevas (línea anclada, no comentada)
- [x] 1.14 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

Suite nueva `=== Running MCP Registry Auth Test Suite ===` en `tests/test-valet.php` (líneas 5455-5899,
prefijo de aserciones `(reg)`), más dos dobles en `tests/wp-stubs.php` (`is_ssl()` → `true`;
`wp_parse_url()` → delegación a `parse_url()`). **Ningún fichero de producción tocado.**

```
$ just test
...
Total passed: 796, Total failed: 58        # EXIT=1
```

- Los **58** fallos llevan todos el prefijo `(reg)`; **0** fallos ajenos a la suite nueva.
- **11** aserciones nuevas ya pasan en ROJO (guardas y contrapruebas negativas):
  `proofDocument()` → `null` sin prueba, `init()` no registra `rest_authentication_errors`,
  `isRegistryPath()` rechaza `/mcp` y `/mcp/server-card`, `/.well-known/ard.json` y
  `/.well-known/ai-catalog.json`, `mediaTypeForPath()` → `null` para rutas ajenas, `POST`/`DELETE`
  no obtienen plan, `server.json` sin secretos (vacuo: el fichero aún no existe) y la constante de
  prueba queda definida para el último escenario.
- `php -l tests/test-valet.php` y `php -l tests/wp-stubs.php` → **No syntax errors detected**.
- `git diff --stat` → solo `tests/test-valet.php` (+457) y `tests/wp-stubs.php` (+36).

**Orden obligatorio de los escenarios (documentado en la suite):** primero el `404` en estado limpio
(sin constante ni opción), después el estado con la **opción**, y la **constante** en el ÚLTIMO
escenario —`define()` es irreversible dentro de un proceso—, con la opción puesta con **otro** valor
distinto para probar la precedencia.

**Aviso para la fase GREEN:** la suite preexistente del ARD fija
`assert_equals(4, $ard_machine_directive_count, '(proxy) … exactamente 4 entradas activas "1;"')`.
Al añadir las dos rutas nuevas al mapa `$mcp_machine_path`, el contador sube a **6** y esa aserción
heredada debe actualizarse a `6` (es un guardián de directivas comentadas, no una regresión).

## 2. GREEN — implementación mínima

- [ ] 2.1 Servir `/.well-known/mcp-registry-auth` (ruta, composición, `404` si no hay prueba)
- [ ] 2.2 Servir `/.well-known/oauth-protected-resource` desde la configuración del proveedor
- [ ] 2.3 Ajuste de la línea de prueba en el hub de ajustes (`class-theme-options.php`), con precedencia de constante
- [ ] 2.4 `server.json` en la raíz del repositorio, con la fuente única del nombre/versión/URL de la tarjeta
- [ ] 2.5 `nginx/pro/nginx.conf`: exención de las dos rutas en el mapa de rutas de máquina
- [ ] 2.6 `README.md` del plugin: documentar el flujo de publicación manual (generar claves, servir la prueba,
      `mcp-publisher login http`, `mcp-publisher publish`) sin publicar nunca la clave privada
- [ ] 2.7 `just test` → **100% verde**; `just php-lint` limpio

## 3. REFACTOR

- [ ] 3.1 Una sola fuente de verdad por artefacto (URL canónica, línea de prueba, emisor, nombre/versión/URL del manifiesto)
- [ ] 3.2 Revisar que las rutas nuevas no quedan hardcodeadas en dos sitios sin relación (constante, mapa nginx, tests)
- [ ] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

## 4. Verificación

- [ ] 4.1 `openspec validate mcp-registry-auth --strict` → valid
- [ ] 4.2 `just check-spec` → en verde
- [ ] 4.3 Auditoría de seguridad: que la clave privada no pueda filtrarse por ningún camino, que el
      documento no habilite un flujo OAuth engañoso y que ninguna ruta nueva exponga datos internos
- [ ] 4.4 Verificación E2E en producción tras el despliegue: las dos rutas responden y son alcanzables
      **con User-Agent `curl`** (sin cierre de conexión); `404` correcto cuando la configuración falta
- [ ] 4.5 **Paso manual del titular:** verificar la propiedad del dominio
      (`mcp-publisher login http --domain atareao.es`) contra la prueba servida
- [ ] 4.6 **Paso manual del titular:** publicar (`mcp-publisher publish`) y verificar la entrada con la
      API del registro (`GET /v0.1/servers?search=es.atareao/blog`)

## 5. Entrega

- [ ] 5.1 PR `feature/mcp-registry-auth → development` con **merge commit** (convención del proyecto)
- [ ] 5.2 Archivar el change fusionando el delta en `openspec/specs/mcp-registry/spec.md`

## Notas de implementación

- **La clave privada no entra en el sistema.** El flujo del registro firma en la máquina del titular;
  el sitio solo publica la clave pública. Cualquier diseño que necesite la privada en el servidor es
  incorrecto por construcción.
- **`404` como respuesta por defecto.** Un artefacto de verificación sin sustancia se omite, no se
  maquilla: los escenarios «sin prueba configurada» y «sin proveedor configurado» lo fijan.
- **Riesgo de alcance aceptado:** el documento RFC 9728 puede inducir a un cliente a intentar un flujo
  OAuth contra el proveedor de identidad. El escenario «no cambia la política de acceso» acota la
  expectativa, pero la decisión de anunciarlo mientras ninguna herramienta exige token es
  **deliberada y reversible**: recortar esta pieza no afecta al registro.
