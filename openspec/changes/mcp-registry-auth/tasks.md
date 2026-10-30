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

- [x] 2.1 Servir `/.well-known/mcp-registry-auth` (ruta, composición, `404` si no hay prueba)
- [x] 2.2 Servir `/.well-known/oauth-protected-resource` desde la configuración del proveedor
- [x] 2.3 Ajuste de la línea de prueba en el hub de ajustes (`class-theme-options.php`), con precedencia de constante
- [x] 2.4 `server.json` en la raíz del repositorio, con la fuente única del nombre/versión/URL de la tarjeta
- [x] 2.5 `nginx/pro/nginx.conf`: exención de las dos rutas en el mapa de rutas de máquina
- [x] 2.6 `README.md` del plugin: documentar el flujo de publicación manual (generar claves, servir la prueba,
      `mcp-publisher login http`, `mcp-publisher publish`) sin publicar nunca la clave privada
- [x] 2.7 `just test` → **100% verde**; `just php-lint` limpio

### Evidencia — fase GREEN

**Ficheros:** nuevo `includes/class-mcp-registry.php` (304 líneas, espejo estructural de
`class-mcp-discovery.php`); `atareao-functionality.php` (require + `init()`); nuevo `server.json`
(raíz); `nginx/pro/nginx.conf` (2 entradas); `class-theme-options.php` (opción + campo);
`README.md` (flujo manual); `tests/test-valet.php` (**único** cambio: el contador heredado del ARD
`4 → 6`, obligado por las dos entradas nuevas del mapa).

Reutilización verificada: `responseFor()` delega en `MCPDiscovery::headersFor()`,
`MCPDiscovery::etagFor()` y `MCPDiscovery::CACHE_CONTROL`; `init()` **solo** registra
`template_redirect` a prioridad 0 y no toca `rest_authentication_errors`.

```
$ just test
Total passed: 854, Total failed: 0
All tests passed successfully!        # EXIT=0

$ just php-lint
64 ficheros, ningún error de sintaxis.   # EXIT=0

$ just phpcs <ficheros tocados>
0 ERRORS en los tres ficheros PHP.
```

- Las 69 aserciones `(reg)` pasan; la línea base era 854 (796 + 58), sin desviación.
- `server.json` alineado con las constantes: `name` = `MCPDiscovery::SERVER_NAME`,
  `version` = `ATAREAO_PLUGIN_VERSION`, `url` del remoto = `MCPDiscovery::endpointUrl()`.
  `$schema` = `https://static.modelcontextprotocol.io/schemas/2025-12-11/server.schema.json`
  (literal vigente confirmado en vivo: HTTP 200 y la doc oficial lo cita; el esquema exige
  `name`/`description`/`version` y `type`+`url` por remoto).
- `resource_documentation` = la raíz del sitio: es un `SHOULD` y el sitio no tiene aún página de docs
  dedicada del endpoint; se evita inventar una URL.
- Ninguna clave privada en ningún fichero de producción (el repo solo lleva un `p` **sintético** en el
  fixture del test).

## 3. REFACTOR

- [x] 3.1 Una sola fuente de verdad por artefacto (URL canónica, línea de prueba, emisor, nombre/versión/URL del manifiesto)
- [x] 3.2 Revisar que las rutas nuevas no quedan hardcodeadas en dos sitios sin relación (constante, mapa nginx, tests)
- [x] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

### Evidencia — fase REFACTOR

```
$ just phpcs wp-content/plugins/atareao-functionality/includes/class-theme-options.php
FOUND 0 ERRORS AND 12 WARNINGS AFFECTING 12 LINES   # = línea base, sin violaciones nuevas

$ just php-lint-changed
No syntax errors detected in tests/test-valet.php
No syntax errors detected in .../atareao-functionality.php
No syntax errors detected in .../class-mcp-registry.php
No syntax errors detected in .../class-theme-options.php   # EXIT=0

$ just test
Total passed: 854, Total failed: 0
```

- El `<input>` del campo nuevo pasa a atributos multilínea y la descripción se reformatea (el fichero
  vuelve a sus **12** warnings preexistentes: la línea de 244 caracteres era la más larga del fichero).
  El mensaje conserva el sentido: solo la clave **pública**, nunca la privada.
- En el mapa nginx, el `1;` de las **dos** entradas nuevas queda alineado entre sí; las cuatro entradas
  preexistentes y el texto de los regex no se tocan (diff mínimo).
- Fuente única por artefacto: la prueba sale de una sola resolución con precedencia
  constante → opción; el emisor, de la opción del proveedor de identidad; el URI del recurso se deriva de
  `MCPDiscovery::endpointUrl()`; el manifiesto se compara contra las constantes de la tarjeta.
  Las rutas nuevas aparecen en su constante de clase, en el mapa nginx y en el test, siempre por nombre.

## 4. Verificación

- [x] 4.1 `openspec validate mcp-registry-auth --strict` → valid
- [x] 4.2 `just check-spec` → en verde
- [x] 4.3 Auditoría de seguridad: que la clave privada no pueda filtrarse por ningún camino, que el
      documento no habilite un flujo OAuth engañoso y que ninguna ruta nueva exponga datos internos
- [ ] 4.4 Verificación E2E en producción tras el despliegue: las dos rutas responden y son alcanzables
      **con User-Agent `curl`** (sin cierre de conexión); `404` correcto cuando la configuración falta
- [ ] 4.5 **Paso manual del titular:** verificar la propiedad del dominio
      (`mcp-publisher login http --domain atareao.es`) contra la prueba servida
- [ ] 4.6 **Paso manual del titular:** publicar (`mcp-publisher publish`) y verificar la entrada con la
      API del registro (`GET /v0.1/servers?search=es.atareao/blog`)

### Evidencia — verificación (4.1-4.3)

```
$ openspec validate mcp-registry-auth --strict
Change 'mcp-registry-auth' is valid

$ just check-spec
✅ Change proposal/s activo/s: mcp-registry-auth,ai-catalog-enrichment   # EXIT=0
```

**4.3 — auditoría de seguridad: 0 hallazgos explotables** (0 CRITICAL / 0 HIGH / 0 MEDIUM).
Contrapruebas: `grep`/`git grep` del árbol y del historial en busca de material PEM (cero, salvo
literales de aserciones negativas); `init()` **solo** registra `template_redirect`; las herramientas
públicas siguen sin exigir token; el webroot de los contenedores no monta la raíz del repositorio, así
que `server.json` y `openspec/` no se sirven; la inyección por la opción exige `manage_options` y la
respuesta es `text/plain` con `nosniff`; rutas disjuntas de `MCPDiscovery` (sin shadowing).

Dos desviaciones **informativas** (no explotables), ambas por control de un administrador:

- **SEC-BE-001 — reparada.** `authorization_servers` publicaba el valor de la opción solo con `trim()`.
  RFC 9728 exige que el identificador del servidor de autorización sea un *issuer identifier*
  (RFC 8414): absoluto, `https` y **sin barra final**. `PocketIDLogin` ya obliga al prefijo `https://`,
  pero `esc_url_raw()` conserva la barra, de modo que el documento podía anunciar un emisor que no
  empareja con el issuer real y el cliente descartaría el documento de metadatos.
  Reparado con TDD: **RED** `854 / 2` (los 2 fallos nuevos con prefijo `(reg)`), **GREEN**
  `856 / 0`, con `rtrim(trim(...), '/')` en `identityProvider()`. El RED se verificó además de forma
  independiente revirtiendo el módulo a HEAD (854/2) y restaurándolo (856/0).
- **SEC-BE-002 — diferida al despliegue.** La caché FastCGI de `nginx/pro/wp.conf` no excluye estas dos
  rutas (`fastcgi_cache_valid 200 … 6h`), así que tras una **rotación** de la prueba el registro podría
  leer la copia antigua hasta 6 h. **No es una violación del spec** del change (el plugin responde el
  `304` correctamente; la caché del proxy no está en su alcance) y solo se puede validar tras
  desplegar. Acción para el despliegue: añadir ambas rutas a la exclusión de caché para que el
  `ETag`/`304` del plugin gobierne la revalidación.

Endurecimiento adoptado a raíz de la auditoría: `.gitignore` ignora `*.pem`. El flujo documentado
genera un `key.pem` con `openssl genpkey`; sin la regla, generarlo dentro del repositorio podría
versionar la clave privada en un `git add -A` accidental — justo la invariante central del change.
Verificado que no hay ningún `.pem`/`.key` versionado ni en el árbol de trabajo, así que la regla no
oculta nada legítimo, y `git check-ignore -v key.pem` confirma que queda ignorado.

Observaciones no bloqueantes, documentadas y no aplicadas: `bearer_methods_supported: ['header']` es un
valor **exigido por el spec** del change y declarativo (el endpoint público no valida tokens), por lo
que se mantiene; y no se valida la *forma* de la línea de prueba en tiempo de servido (una línea
malformada hace fallar la verificación del registro, no abre un vector).

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
