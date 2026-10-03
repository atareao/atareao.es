# Design: Pocket ID Logout Fixes

## Context

Ver `proposal.md` — Why. El módulo `\Atareao\PocketIDLogin` (`wp-content/plugins/atareao-functionality/includes/class-pocketid-login.php`) resuelve el discovery en `getOIDCConfig()`/`fetchDiscoveryConfig()`, cachea el resultado 12 h en el transient `atareao_pocketid_oidc_config` y lo consume en `handleLogoutRedirect()` para el RP-initiated logout introducido por `pocketid-logout`. Restricciones: PHP ≥ 7.4, clase estática, opciones `atareao_pocketid_*`, log `[atareao-pocketid]`, sin tests automatizados (verificación por `just php-lint` + `just phpcs` y prueba manual).

Estado actual relevante:

- `getOIDCConfig()` acepta la caché si tiene `authorization_endpoint`, `token_endpoint` y `userinfo_endpoint`; **no comprueba ninguna versión de esquema** (líneas ~164-172). Por eso una caché escrita antes de `pocketid-logout` se acepta y nunca incluye `end_session_endpoint`.
- `fetchDiscoveryConfig()` ya valida y conserva `end_session_endpoint` de forma opcional (líneas ~258-280); el problema es solo que la caché antigua no se refresca.
- `handleLogoutRedirect()` hace fail-safe a logout local cuando `empty($config['end_session_endpoint'])` (líneas ~601-605), sin intentar refrescar.
- `renderLoginFooter()` (líneas ~732-752), en modo exigir, imprime solo "Se requiere PocketID para acceder." y retorna sin dibujar el botón; el core ya ha pintado el formulario y el aviso "Has cerrado la sesión".

## Goals / Non-Goals

**Goals:**

- Garantizar que una caché de descubrimiento antigua (sin `end_session_endpoint`) se invalide y refresque, sin exigir que `end_session_endpoint` esté presente (es opcional en el discovery).
- Refrescar puntualmente el discovery en el logout si falta `end_session_endpoint`, para cerrar de verdad la sesión del proveedor.
- Ofrecer una pantalla post-logout limpia y accionable en modo exigir, sin tocar la pantalla normal.

**Non-Goals:**

- Cambiar el flujo de login, PKCE, callback, el bloqueo de contraseña ni la persistencia del `id_token`.
- Exigir `end_session_endpoint` como requisito de validez de la caché o del fallback.
- Introducir back-channel logout ni revocación de tokens.
- Reemplazar el mecanismo de caché por otra tecnología.

## Decisions

### D1. Versionar el esquema de la caché en lugar de exigir `end_session_endpoint`

Se añade una constante `const CONFIG_SCHEMA = 2;` y la caché pasa a incluir `'config_schema' => self::CONFIG_SCHEMA`. `getOIDCConfig()` solo acepta la caché si, además de los tres endpoints obligatorios, `(int) $cached['config_schema'] === self::CONFIG_SCHEMA`.

- **Por qué versionar y no exigir `end_session_endpoint`**: `end_session_endpoint` es **opcional** en OIDC. Exigirlo invalidaría la caché en cualquier proveedor que no lo publique y forzaría una descarga de discovery en cada petición; además, un proveedor legítimamente sin `end_session_endpoint` nunca podría tener una caché "válida". Versionar el esquema invalida solo lo que realmente puede estar obsoleto (cachés escritas por versiones anteriores) y es extensible a futuros campos.
- **Compatibilidad**: una caché sin `config_schema` (versión 1 implícita, escrita por `6525474` o anteriores) se considera inválida por comparación `=== 2` y se refresca sola. No requiere limpieza manual.
- **Alternativa descartada**: borrar el transient en cada upgrade comprobando una opción de versión de plugin. Se descarta por frágil (requiere engancharse a un hook de upgrade y mantener otra opción); la versión en la propia entrada mantiene la decisión autocontenida en `getOIDCConfig()`.

Array devuelto por `fetchDiscoveryConfig()` (ya existente, se le añade la versión):

```php
return array(
    'config_schema'            => self::CONFIG_SCHEMA,
    'source'                   => 'discovery',
    'authorization_endpoint'   => $endpoint_keys['authorization_endpoint'],
    'token_endpoint'           => $endpoint_keys['token_endpoint'],
    'userinfo_endpoint'        => $endpoint_keys['userinfo_endpoint'],
    'end_session_endpoint'     => $end_session_endpoint,
);
```

Fallback (proveedor caído o discovery inválido), también con versión de esquema para que no se refresque en bucle:

```php
return array(
    'config_schema'          => self::CONFIG_SCHEMA,
    'source'                 => 'fallback',
    'authorization_endpoint' => $base . '/authorize',
    'token_endpoint'         => $base . '/api/oidc/token',
    'userinfo_endpoint'      => $base . '/api/oidc/userinfo',
    'end_session_endpoint'   => '',
);
```

### D2. Refresco puntual del discovery en el logout

En `handleLogoutRedirect()`, antes del fail-safe, si `empty($config['end_session_endpoint'])` se llama **una sola vez** a `self::getOIDCConfig(true)` (que ya ignora caché y descarga). Si el nuevo resultado trae `end_session_endpoint`, se continúa con la redirección al proveedor; si no, se registra `error_log('[atareao-pocketid] …')` y se devuelve el `$redirect_to` local.

- **Por qué no delegarlo solo a D1**: D1 corrige la caché en el siguiente acceso que consulte el discovery, pero el usuario podría cerrar sesión antes de que ocurra. Refrescar en el logout hace el arreglo efectivo de inmediato para el usuario que ya tiene caché antigua.
- **Por qué una sola vez**: `getOIDCConfig(true)` ya hace internamente un reintento de descarga; no se añade bucle. Si tras el refresco el proveedor no publica el endpoint, se cae a local (fail-safe), tal como exige el requirement.
- **Coste**: como máximo una descarga extra en el logout cuando la caché es antigua/incompleta. Una vez refrescada, las siguientes consultas reutilizan la caché versionada.

### D3. Ocultar el formulario y renderizar el botón "Iniciar sesión" en la pantalla post-logout

En `renderLoginFooter()`, para el caso `'1' === OPTION_ENFORCE` **y** `!empty($_GET['loggedout'])`:

1. Inyectar un `<style>` que oculte el formulario inerte (`#loginform`, `#nav`, `#backtoblog` y el enlace de recuperación), acotado a esa pantalla.
2. No retornar: imprimir el aviso nativo de sesión cerrada y el botón "Iniciar sesión" (construido con `add_query_arg('action', 'pocketid', wp_login_url())`).

Además, en modo exigir se **elimina** el aviso "Se requiere PocketID para acceder." que hoy se imprimía. El botón "Iniciar sesión" se renderiza **solo** en el caso `enforce` **y** `loggedout`; en el resto de páginas de `wp-login.php` (por ejemplo acciones nativas como `lostpassword`) no se añade aviso ni botón y la página nativa se deja tal cual. El botón de login de las pantallas públicas se etiqueta únicamente "Iniciar sesión": **ningún texto de la UI pública** (etiqueta, subtítulos, avisos ni mensajes de error) SHALL nombrar al proveedor de identidad. El nombre del proveedor ("Pocket ID") queda reservado a la página de Ajustes (solo administradores), que **queda excluida** de esta restricción.

Cuando el modo exigir está inactivo no se inyecta CSS: se sigue mostrando el botón "Iniciar sesión" y el formulario normal.

- **Por qué CSS inyectado condicionalmente y no `login_form_*`/unhook del core**: el formulario lo pinta WordPress core antes de `login_footer`; desengancharlo exigiría reconstruir la pantalla o depender de filtros internos frágiles. Ocultarlo por CSS es local, reversible y no afecta a ninguna otra pantalla. El formulario permanece en el DOM pero es inerte (el bloqueo real de `blockPasswordLogin()` ya impide autenticarse con contraseña).
- **Por qué renderizar en `login_footer`**: es el hook ya registrado (`add_action('login_footer', …)`), corre después del formulario y del aviso del core, por lo que el botón queda visible al final sin reescribir el core. El `<style>` funciona aunque se emita en el footer porque el navegador aplica las reglas al parsear el documento.
- **Por qué no nombrar al proveedor**: coherencia de producto y menor fuga de detalles de infraestructura; la página de Ajustes (solo admin) conserva el nombre para tareas de configuración.
- **Seguridad**: el `action=pocketid` se sirve por `esc_url()`; el ocultado no concede ninguna capacidad nueva. El POST con `log`+`pwd` sigue bloqueado por `blockPasswordLogin()` (requirement `Password submission still blocked`).
- **Alternativa descartada**: mostrar el botón mediante `login_message`/`login_header` reescribiendo el aviso del core. Se descarta por mayor acoplamiento al markup de WordPress y porque no garantiza ocultar el formulario.

### D4. Logging y fail-safe

Todo refresco fallido o ausencia de `end_session_endpoint` se registra con `error_log('[atareao-pocketid] …')` sin exponer detalles al usuario. Ningún fallo del proveedor impide completar el logout local.

## Risks / Trade-offs

- [La caché versionada invalida también cachés válidas al cambiar `CONFIG_SCHEMA` en el futuro] → Es el comportamiento deseado: un cambio de esquema implica campos nuevos que poblar; una descarga extra en el siguiente acceso es aceptable.
- [Ocultar por CSS depende del markup de `wp-login.php` (`#loginform`, `#nav`, `#backtoblog`)] → Son IDs estables de WordPress desde hace muchas versiones; el ocultado es cosmético y, si cambiaran, el formulario inerte seguiría sin permitir login (el bloqueo es de servidor).
- [Una descarga extra de discovery en cada logout mientras el proveedor no publique `end_session_endpoint`] → `getOIDCConfig(true)` reintenta y, al no haber endpoint, no se cachea nada nuevo; el coste se acota a un logout puntual. Se puede evitar consultando primero la caché versionada (que ya se descargó recientemente).
- [Dependencia de `pocketid-logout`] → Si `pocketid-logout` no se archiva antes, las bases (`handleLogoutRedirect`, respeto de `loggedout`) no existen. Anotado en el proposal; prerrequisito de archivado.
- [Registrar el Post Logout Redirect URI en PocketID] → Necesario para que el proveedor acepte la vuelta; se documenta en ajustes y README. Si no está registrado, el proveedor puede mostrar error, no el sitio.

## Migration Plan

1. (Prerrequisito) Archivar `pocketid-logout` para que sus requirements formen parte de `openspec/specs/pocketid-login/spec.md`.
2. Implementar D1 → D2 → D3 con `just php-lint` y `just phpcs` (PSR12) en verde.
3. En producción: desplegar; la caché antigua se invalida sola. Opcionalmente borrar el transient `atareao_pocketid_oidc_config` para forzar el refresco inmediato, y registrar el Post Logout Redirect URI en PocketID.
4. Prueba E2E manual: login OIDC → logout → pantalla limpia (sin formulario, con botón) y passkey/sesión del proveedor cerrada.
5. Rollback: revertir el archivo PHP; el cambio es aditivo (una constante y ramas nuevas) y no toca datos persistidos más allá del transient efímero de caché.

## Open Questions

- Ninguna que bloquee la implementación: la versión de esquema (`CONFIG_SCHEMA = 2`) y los selectores CSS quedan decididos aquí y no alteran los specs.
