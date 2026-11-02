# Tasks: La era del transporte MCP no puede decidirse por cabeceras

> Estado: **borrador de plan**, pendiente de aprobación explícita del titular.
> Ninguna casilla de 1 a 5 se marca sin evidencia CLI pegada literalmente.
> Alcance: corrección de la **selección de era** y de la respuesta a **notificaciones** en el endpoint MCP.
> No se tocan la tarjeta, el catálogo, las versiones soportadas ni las herramientas.
> El delta de `mcp-server` retira el requisito vigente y lo vuelve a declarar con cabecera nueva
> (`REMOVED` + `ADDED`): el CLI prohíbe que un `MODIFIED` pierda escenarios y el escenario de la regla
> retirada debe desaparecer (ver 0.8).

## 0. Diagnóstico ya medido (evidencia CLI)

- [x] 0.1 Endpoint sano: `POST /mcp` `initialize` → `200` `serverInfo: atareao-mcp v1.19.0`;
      `tools/list` → `200` con `get_latest_posts`, `get_post` y `search_posts`; `GET /mcp` → `405`;
      tarjeta `/mcp/server-card` → `200 application/mcp-server-card+json`;
      catálogo `/.well-known/ai-catalog.json` → `200`
- [x] 0.2 Reproducir el fallo con el cliente real: `npx -y mcp-remote@latest https://atareao.es/mcp` →
      `SdkHttpError: Error POSTing to endpoint: {"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params"},"id":null}`
      (`CLIENT_HTTP_NOT_IMPLEMENTED`, `status: 400`)
- [x] 0.3 Capturar la petición que falla (proxy local que registra y reenvía el tráfico):
      `Mcp-Method: notifications/initialized` + `mcp-protocol-version: 2025-06-18` +
      `{"jsonrpc":"2.0","method":"notifications/initialized"}` → `400 -32602`
- [x] 0.4 Prueba A/B contra producción (misma petición, solo cambia la cabecera):
      sin `Mcp-Method` → `200`; con `Mcp-Method` → `400 -32602`; con `Mcp-Method` + `_meta` completa → `202`
- [x] 0.5 Control de que no es el bloqueo de User-Agents: `/mcp` está exento del `map` `$bad_bot`
      (`~*(curl|wget|python-requests|Go-http|ruby|scrapy|masscan|zgrab)` → `444`) y responde a cualquier UA;
      el SDK oficial de Python conecta y lista las 3 herramientas
- [x] 0.6 Localizar la causa: `isModernRequest()` en `includes/class-mcp.php` decide la era por la
      presencia de la cabecera `Mcp-Method`; el requisito «Conformidad dual-era de Streamable HTTP» de
      `openspec/specs/mcp-server/spec.md` (línea 317) **ordena** esa regla → hace falta delta de spec
- [x] 0.7 Localizar los tests que hoy blindan la conducta a cambiar: `tests/test-valet.php`, suite
      «MCP Dual-Era Transport» (línea 4256), escenario «La cabecera `Mcp-Method` selecciona la era
      moderna», que asserta `-32602`
- [x] 0.8 **Forma del delta** (por qué no vale un `MODIFIED`): `openspec validate mcp-era-selection
      --strict` con el delta en forma de `MODIFIED` →
      `MODIFIED "Conformidad dual-era de Streamable HTTP" omits scenario(s) the current spec still has:
      "La cabecera `Mcp-Method` selecciona la era moderna" ... archive refuses to drop them`.
      Vías medidas: conservar el escenario antiguo dentro del `MODIFIED` → spec autocontradictoria (y un
      test que exigiría `400` para el caso que queremos que devuelva `200`); `REMOVED` + `ADDED` con el
      **mismo** nombre → `Requirement present in both ADDED and REMOVED`; `REMOVED` + `ADDED` con
      **cabecera nueva** → `Change 'mcp-era-selection' is valid`, y `archive` simulado sobre una copia de
      `openspec/` aplica `+ 1 added, - 1 removed` dejando el requisito en su misma posición (317)

## 1. RED — tests que reproducen el fallo

- [x] 1.1 Escribir el caso literal de `mcp-remote`: `POST` con `MCP-Protocol-Version: 2025-06-18` y
      `Mcp-Method: notifications/initialized`, cuerpo sin `params` → esperado `202` sin cuerpo
- [x] 1.2 Escribir el caso de continuación de sesión: `tools/list` con `Mcp-Method: tools/list` y sin
      `_meta` → esperado `200` con las tres herramientas (era legacy)
- [x] 1.3 Escribir el caso de notificación en la era legacy sin cabeceras → esperado `202` sin cuerpo,
      sin error JSON-RPC
- [x] 1.4 **Reescribir** el escenario de la suite MCP Dual-Era: donde hoy se asserta
      «La cabecera `Mcp-Method` selecciona la era moderna» (`-32602`), assertar el inverso —el escenario
      que introduce el delta—: con la cabecera `Mcp-Method` y sin metadatos de protocolo, `200` con el
      resultado del método (o `202` si es una notificación) y nunca `-32602`
- [x] 1.5 Mantener en verde las suites existentes: era moderna con `_meta` (`server/discover`, `tools/list`
      con `ttlMs`/`cacheScope`, `HeaderMismatch`, versión no soportada) y era legacy (`initialize`,
      negociación de versión)
- [x] 1.6 Ejecutar `just test` y **confirmar el RED**: los casos nuevos fallan (hoy responden `400`) y
      el escenario reescrito falla; el resto sigue verde
- [x] 1.7 Antes de tocar el plugin, comprobar que el delta es aplicable: `openspec validate
      mcp-era-selection --strict` en verde y `archive` simulado sobre una **copia** de `openspec/` (la spec
      canónica no se toca hasta 5.3)

## 2. GREEN — implementación mínima

- [x] 2.1 `isModernRequest()`: la era moderna se decide **solo** por
      `params._meta['io.modelcontextprotocol/protocolVersion']`; se elimina la rama de la cabecera
      `Mcp-Method`
- [x] 2.2 Responder `202 Accepted` sin cuerpo ante cualquier mensaje sin `id` (notificación) en **ambas**
      eras, aceptando `notifications/initialized`
- [x] 2.3 Mantener sin cambios la validación de cabeceras de la era moderna (`HeaderMismatch`), el
      `UnsupportedProtocolVersionError` y el resto de la era legacy
- [x] 2.4 `just test` en verde (todos los casos), `just php-lint` sin errores y `just phpcs` sin avisos

## 3. REFACTOR

- [x] 3.1 Dejar el comentario de bloque de `isModernRequest()` explicando **por qué** las cabeceras no
      seleccionan la era (el SDK oficial las envía en ambos dialectos)
- [x] 3.2 Comprobar que no queda ninguna referencia a la regla retirada en código, tests ni documentación
      del plugin
- [x] 3.3 `just test`, `just php-lint`, `just phpcs`, `openspec validate mcp-era-selection --strict` y
      `openspec validate --all --strict`

### Evidencia RED → GREEN (2026-11-02)

**RED** — `just test` con los tests nuevos y el plugin sin tocar (exit 1):

```
  FAIL: (legacy) la cabecera Mcp-Method sin metadatos de protocolo no abre la era moderna (Expected: 200, got: 400)
  FAIL: (legacy) tools/list con Mcp-Method devuelve el listado de herramientas
  FAIL: (legacy) la cabecera Mcp-Method no provoca -32602
  FAIL: (sdk) notifications/initialized con Mcp-Method responde 202 (Expected: 202, got: 400)
  FAIL: (sdk) notifications/initialized no lleva cuerpo (Expected: NULL, got: array (... -32602 ...))
  FAIL: (sdk) la sesion continua y tools/list responde 200 (Expected: 200, got: 400)
  FAIL: (sdk) tools/list devuelve las herramientas
  FAIL: (legacy) una notificacion responde 202 (Expected: 202, got: 200)
  FAIL: (legacy) una notificacion no lleva cuerpo ni error JSON-RPC (Expected: NULL, got: array (... -32601 ...))
Total passed: 1069, Total failed: 9
```

Línea base medida antes de escribir nada: `Total passed: 1068, Total failed: 0`. Los 9 fallos son
exactamente los assertos de los tres escenarios nuevos; ninguna suite ajena se rompe.

**GREEN** — con el arreglo en `includes/class-mcp.php`:

```
Total passed: 1078, Total failed: 0
All tests passed successfully!
```

**Resto del ciclo:**

- `just php-lint` → sin errores (`No syntax errors detected in ...`).
- `just phpcs` (PSR12): el fichero tocado queda en **0 errores** y con los **mismos 5 warnings
  preexistentes** que en `HEAD` (comparado en el mismo contenedor con
  `git show HEAD:...class-mcp.php`: mismas advertencias de longitud de línea, desplazadas +10 líneas por el
  docblock nuevo). La receta global ya fallaba antes del change por hallazgos ajenos en 79 ficheros
  (JS de bloques y theme): no se ha introducido ningún hallazgo nuevo.
- `openspec validate mcp-era-selection --strict` → `Change 'mcp-era-selection' is valid`.
- `openspec validate --all --strict` → `Totals: 35 passed, 0 failed (35 items)`.
- Delta aplicable: `archive` simulado sobre una **copia** de `openspec/` → `+ 1 added, - 1 removed`, con el
  requisito en su misma posición (línea 317) y sin tocar ningún otro requisito.

### Auditoría independiente y endurecimiento (2026-11-02)

Auditoría externa del change completo (`git diff development..HEAD`): **APROBADO CON REPAROS**, 0
hallazgos bloqueantes. Reparos atendidos:

- **F3 (rama muerta)** — `handleModernRequest()` comprobaba también `META_PROTOCOL_VERSION`, que
  `isModernRequest()` ya garantiza. Se elimina la mitad inalcanzable y se deja comentada la invariante.
- **F4/F5 (cobertura)** — cuatro asertos de **caracterización** sobre conducta preexistente (no se espera
  RED): `tools/call` sin `Mcp-Name` y con `Mcp-Name` discordante → `400` `HeaderMismatch` (`-32020`); una
  notificación legacy distinta de `notifications/initialized` → `202` sin cuerpo (sin error JSON-RPC); y la
  decisión de F2 (abajo).
- **F2 (ambigüedad de spec, decidida por el titular)** — una notificación de la era moderna cuyos metadatos
  o cabeceras incumplen las obligaciones de forma recibe el error correspondiente (`-32602`/`-32020`) y
  **no** `202`: la validación precede a la aceptación. Queda explícito en el delta con una frase en el
  requisito y un escenario propio («Notificación moderna con metadatos incompletos»), con su aserto.
- **F1 y F7** — registrados como deuda en §6 (no se cambia comportamiento).
- **F6** — la spec canónica aún ordena la regla retirada; es lo esperado hasta el `archive` (5.3).

Verificación tras el endurecimiento:

```
just php-lint                               → sin errores
just test                                   → Total passed: 1082, Total failed: 0
                                              All tests passed successfully!
phpcs --standard=PSR12 (ficheros tocados)   → 0 errores nuevos: los dos de tests/test-valet.php
                                              (multi-line function declarations, línea 6336) ya existen
                                              en HEAD (línea 6265)
openspec validate mcp-era-selection --strict → Change 'mcp-era-selection' is valid
openspec validate --all --strict             → Totals: 35 passed, 0 failed (35 items)
```

## 4. E2E con clientes reales (contra producción) — HECHO (2026-11-02)

- [x] 4.1 **Despliegue quirúrgico**: `docker cp` del módulo al volumen de producción con copia de
      seguridad previa y hashes verificados (el plugin vive en el volumen `wordpress_wordpress`, no en un
      bind-mount)
- [x] 4.2 Cliente real que hoy falla: `npx -y mcp-remote@latest https://atareao.es/mcp` conecta y lista
      `get_latest_posts`, `get_post` y `search_posts`
- [x] 4.3 Regresión de clientes que ya iban: el SDK oficial de Python conecta y lista las tres
      herramientas
- [x] 4.4 Era moderna intacta: con `_meta` de protocolo (`2026-07-28`), `server/discover` y `tools/list`
      siguen respondiendo con `resultType`, `serverInfo`, `ttlMs` y `cacheScope`
- [x] 4.5 Repetir la prueba A/B del escenario 0.4 y comprobar que las tres variantes se comportan como
      ordena el delta
- [x] 4.6 Registrar las evidencias literales en este fichero

### Evidencia E2E en producción (2026-11-02)

Host: `ssh atareao` (contenedor WordPress `atareao`, `wordpress:fpm-alpine`; el acceso va por el puerto
8392 del alias `atareao`, no por `atareao.es`). Despliegue quirúrgico con copia previa dentro del
contenedor y en el host:

```
antes     a3e2bf0fcc244ebc178bd185e2225584  (idéntico a development)
backup    a3e2bf0fcc244ebc178bd185e2225584  /tmp/class-mcp.php.bak-a3e2bf0f  + copia en el host
local     f7c2dfa6f705ff0ffc729df13f75aca8
despues   f7c2dfa6f705ff0ffc729df13f75aca8  permisos restaurados 82:82 644
```

Pruebas HTTP contra `https://atareao.es/mcp`:

| # | Caso | HTTP | Cuerpo |
|---|---|---|---|
| T1 | `tools/list` con `Mcp-Method` y **sin** `_meta` (el fallo del titular) | `200` | 3 herramientas, sin `error` |
| T2 | notificacion `notifications/initialized` con `Mcp-Method` y `MCP-Protocol-Version`, sin `params` | `202` | **0 bytes** |
| T3 | era moderna `tools/list` (`_meta` `2026-07-28`) | `200` | `resultType`, `ttlMs`, `cacheScope`, `_meta.serverInfo` |
| T4 | era moderna `server/discover` | `200` | `supportedVersions: ["2026-07-28","2025-06-18"]` |
| T5 | control: `tools/list` sin cabeceras | `200` | 3 herramientas |
| T6 | `initialize` (`2025-06-18`) | `200` | `serverInfo: atareao-mcp 1.19.0` |
| T7 | notificacion legacy sin cabeceras ni `id` | `202` | **0 bytes** |
| T8 | era moderna con `Mcp-Name` discordante, `Mcp-Method` discordante o `Mcp-Name` inexistente | `400` | `HeaderMismatch` `-32020` (la validacion no se relaja) |

Clientes reales:

```
TS oficial (@modelcontextprotocol/sdk 1.32.2) -> SERVIDOR: {"name":"atareao-mcp","version":"1.19.0"}
                                                 HERRAMIENTAS: ["get_latest_posts","get_post","search_posts"]   EXIT=0
mcp-remote 0.16.0                             -> Connected to remote server using StreamableHTTPClientTransport
                                                 Proxy established successfully ...   EXIT=0
                                                 0 apariciones de -32602 / CLIENT_HTTP_NOT_IMPLEMENTED / 400
Python oficial (mcp 2.3.0)                    -> SERVIDOR: atareao-mcp 1.19.0
                                                 HERRAMIENTAS: ['get_latest_posts','get_post','search_posts']   EXIT=0
```

El arreglo **queda desplegado**: md5 `f7c2dfa6f705ff0ffc729df13f75aca8`, permisos `82:82 644`. No hizo
falta recargar PHP-FPM (T1 paso a la primera) ni hubo rollback. Evidencias crudas en `/tmp/opencode/e2e-mcp/`.

## 5. Entrega

- [x] 5.1 Rama `feature/mcp-era-selection` desde `development`; commits con gitmoji
- [x] 5.2 Push y PR a `development` (sin fusionar sin autorización explícita): PR **#148**, CI `lint` en
      verde; **pendiente de autorización** para fusionar
- [ ] 5.3 `openspec archive mcp-era-selection` **solo tras autorización**, y revisar el diff de
      `openspec/specs/mcp-server/spec.md`: el requisito retirado se sustituye por el nuevo en su misma
      posición, sin tocar ningún otro requisito
- [ ] 5.4 Nota con lo aprendido: por qué una cabecera de era no puede ser un marcador fiable, qué clientes
      reales dependen de ello, y por qué el CLI obliga a declarar el requisito nuevo (`REMOVED` + `ADDED`)
      en lugar de editar el vigente

## 6. Deuda aceptada (no bloqueante, se documenta)

- [ ] 6.1 El bloqueo de User-Agents del nginx de producción devuelve `444` (Traefik lo convierte en `502`)
      a `curl`, `wget`, `python-requests`, `Go-http`, `ruby`, `scrapy`, `masscan` y `zgrab` fuera de las
      rutas exentas: merece revisión propia, no este change
- [ ] 6.2 `/.well-known/mcp` no existe (la tarjeta se publica en `/mcp/server-card`): algunos registros
      sondean esa ruta; evaluar publicarla o documentarlo
- [ ] 6.3 `GET /mcp` responde `405` (correcto para Streamable HTTP, pero hay clientes que abren el flujo
      SSE con `GET`): documentar la limitación

- [x] 6.4 **F1 (deuda de precisión)**: un `id: null` explícito no se trata como notificación
      (`array_key_exists`), de forma coherente en las dos eras. JSON-RPC define la notificación como el
      mensaje *sin* el miembro `id`, así que se documenta y no se cambia
- [x] 6.5 **F7 (cerrada con el E2E)**: la emisión HTTP real del `202` quedó verificada en producción: T2 y
      T7 devolvieron `HTTP/2 202` con cuerpo de **0 bytes** (el harness lo observaba como `get_data() === null`)
- [x] 6.6 **Traslado a change propio** (autorizado por el titular el 2026-11-02): el bloqueo por
      User-Agent del nginx (6.1), la ausencia de `/.well-known/mcp` (6.2) y el `405` del `GET /mcp` (6.3)
      se abordan fuera de este change

## Verificación

| Qué | Comando |
|---|---|
| Suites del repositorio | `just test` |
| Sintaxis PHP | `just php-lint` |
| Estilo PSR12 | `just phpcs` |
| Artefactos del change | `openspec validate mcp-era-selection --strict` |
| Todas las specs y changes | `openspec validate --all --strict` |
| Cliente real | `npx -y mcp-remote@latest https://atareao.es/mcp` |
