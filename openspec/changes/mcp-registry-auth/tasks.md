# Tasks: mcp-registry-auth

## 0. Preparación

- [ ] 0.1 Confirmar el punto de servido de los artefactos fuera del ciclo REST
      (`\Atareao\MCPDiscovery::serve` sobre `template_redirect`) y decidir si los dos documentos nuevos
      se sirven desde ahí o desde un módulo propio con el mismo contrato
- [ ] 0.2 Localizar la opción del proveedor de identidad (`atareao_pocketid_url`) y el `issuer` del
      discovery, para la fuente de verdad de `authorization_servers`
- [ ] 0.3 Confirmar en el arnés (`tests/wp-stubs.php`) los stubs necesarios (`home_url`, `get_option`,
      `header`, `status_header`, `wp_json_encode`) y que `class_exists`/`method_exists` dejan un `FAIL:`
      legible si el seam no existe todavía
- [ ] 0.4 Generar el par de claves Ed25519 y obtener la **línea de prueba**; documentar el procedimiento.
      La **clave privada NO se versiona, NO se guarda en `wp_options` y NO se sube a ningún sitio**

## 1. RED — los tests primero, y que fallan

- [ ] 1.1 `GET /.well-known/mcp-registry-auth` → `200`, `text/plain; charset=utf-8`, cuerpo = línea de prueba
- [ ] 1.2 La línea de prueba empieza por `v=MCPv1; k=`, declara algoritmo admitido y clave pública Base64
- [ ] 1.3 Sin prueba configurada → `404` (y sin cuerpo de prueba)
- [ ] 1.4 La respuesta no contiene ninguna clave privada; el cuerpo solo lleva la clave pública
- [ ] 1.5 La constante de prueba tiene precedencia sobre la opción
- [ ] 1.6 `GET /.well-known/oauth-protected-resource` → `200`, `application/json`
- [ ] 1.7 `resource` es el URI canónico del endpoint (minúsculas, sin barra final)
- [ ] 1.8 `authorization_servers` contiene el emisor configurado; sin proveedor → `404`
- [ ] 1.9 `bearer_methods_supported` incluye `header`
- [ ] 1.10 Ninguno de los dos documentos expone secretos ni enumera herramientas, recursos ni prompts
- [ ] 1.11 El documento de recurso **no** cambia la política de acceso (herramientas anónimas siguen `200`)
- [ ] 1.12 `server.json` es JSON válido; `name` = `es.atareao/blog`; remoto `streamable-http` a `/mcp`;
      nombre/versión/URL alineados con la tarjeta de servidor
- [ ] 1.13 `nginx/pro/nginx.conf`: el mapa de exención cubre las dos rutas nuevas (línea anclada, no comentada)
- [ ] 1.14 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

*(pendiente de la fase de implementación)*

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
