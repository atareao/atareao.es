# Atareao Functionality Plugin

Plugin de funcionalidades personalizadas para WordPress que proporciona Custom Post Types, Taxonomías y Metaboxes para el sitio Atareao.

## Características

### Custom Post Types

1. **Tutoriales**
   - Slug: `tutorial`
   - Icono: Libro
   - Taxonomías: Categorías, Etiquetas, Dificultad
   - Campos personalizados opcionales

2. **Capítulos**
   - Slug: `chapter`
   - Icono: Documento
   - Relacionado con Tutoriales (relación padre-hijo)
   - Taxonomías: Categorías, Etiquetas (compartidas con Tutoriales)
   - Ordenable por orden de menú

3. **Mis Aplicaciones**
   - Slug: `application`
   - Icono: Smartphone
   - Taxonomías: Categorías, Plataformas
   - Campos: URL de descarga, Repositorio, Versión

4. **Podcast**
   - Slug: `podcast`
   - Icono: Micrófono
   - Taxonomías: Categorías
   - Campos: URL de audio, Duración

5. **Software**
   - Slug: `software`
   - Icono: Escritorio
   - Taxonomías: Categorías, Plataformas, Dificultad
   - Campos: URL de descarga, Repositorio, Versión

### Taxonomías

#### Específicas por Post Type

- **Categorías de Tutoriales** (`tutorial_category`)
  - Para Tutoriales y Capítulos
  - Jerárquica

- **Etiquetas de Tutoriales** (`tutorial_tag`)
  - Para Tutoriales y Capítulos
  - No jerárquica

- **Categorías de Aplicaciones** (`application_category`)
  - Para Aplicaciones
  - Jerárquica

- **Categorías de Podcasts** (`podcast_category`)
  - Para Podcasts
  - Jerárquica

- **Categorías de Software** (`software_category`)
  - Para Software
  - Jerárquica

#### Taxonomías Compartidas

- **Dificultad** (`difficulty`)
  - Para Tutoriales y Software
  - No jerárquica
  - Términos predeterminados: Principiante, Intermedio, Avanzado, Experto

- **Plataforma** (`platform`)
  - Para Aplicaciones y Software
  - No jerárquica
  - Términos predeterminados: Linux, Windows, macOS, Android, iOS, Web

### Metaboxes

#### Para Aplicaciones y Software

- URL de Descarga
- URL de Repositorio (GitHub, GitLab, etc.)
- Versión

#### Para Podcasts

- URL del archivo de audio
- Duración (formato HH:MM:SS)
- Reproductor de audio en el editor

#### Para Capítulos

- Selector de Tutorial padre (para vincular capítulos con tutoriales)

### Bloques de Gutenberg

#### Bloque: Reproductor de Podcast

Un bloque personalizado para el editor de Gutenberg que permite insertar un reproductor de audio para podcasts.

**Características:**

- Selector de podcasts existentes
- Campo de URL personalizada para archivos externos
- Título y descripción editables
- Reproductor HTML5 nativo
- Responsive (móvil, tablet, desktop)
- Enlace automático a la página del podcast
- Soporte para alineaciones (normal, wide, full)
- Personalización de colores y espaciado

**Uso:**

1. En el editor de Gutenberg, haz clic en [+]
2. Busca "Reproductor de Podcast"
3. Selecciona un podcast de la lista o introduce una URL manual
4. Personaliza título y descripción si lo deseas
5. El reproductor se mostrará automáticamente en el frontend

**Documentación completa:** Ver [BLOQUE-PODCAST.md](BLOQUE-PODCAST.md)

## Requisitos

- WordPress 6.0 o superior
- PHP 7.4 o superior

## Instalación

1. Descarga el plugin
2. Sube la carpeta `atareao-functionality` a `/wp-content/plugins/`
3. Activa el plugin desde el panel de WordPress
4. Los Custom Post Types aparecerán automáticamente en el menú del admin

O bien:

1. Ve a WordPress Admin > Plugins > Añadir nuevo
2. Haz clic en "Subir plugin"
3. Selecciona el archivo ZIP del plugin
4. Haz clic en "Instalar ahora"
5. Activa el plugin

## Uso

### Crear un Tutorial con Capítulos

1. Ve a **Tutoriales > Añadir nuevo**
2. Crea tu tutorial con título, contenido e imagen destacada
3. Asigna categorías, etiquetas y nivel de dificultad
4. Publica el tutorial
5. Ve a **Capítulos > Añadir nuevo**
6. Crea cada capítulo del tutorial
7. En el metabox "Tutorial" de la derecha, selecciona el tutorial padre
8. Utiliza el campo "Orden" en "Atributos de página" para ordenar los capítulos

### Añadir una Aplicación

1. Ve a **Mis Aplicaciones > Añadir nueva**
2. Añade título, descripción e imagen destacada
3. Completa los campos:
   - URL de Descarga
   - URL de Repositorio
   - Versión
4. Selecciona las plataformas compatibles
5. Publica

### Publicar un Podcast

1. Ve a **Podcasts > Añadir nuevo**
2. Añade título, descripción e imagen destacada
3. En el metabox "Audio del Podcast":
   - Introduce la URL del archivo MP3
   - Verifica con el reproductor que funciona
4. Añade la duración en formato HH:MM:SS
5. Asigna categorías
6. Publica

### Añadir Software

1. Ve a **Software > Añadir nuevo**
2. Similar a Aplicaciones, pero incluye:
   - Nivel de dificultad
   - Plataformas compatibles
   - URLs de descarga y repositorio
   - Versión

## Autenticación con PocketID (OIDC)

Runbook operativo de la integración de login *passwordless* con PocketID. Recoge lo aprendido en una puesta en marcha real, incluidos los problemas de configuración de nginx y de email verificado.

### Qué es

- Módulo `\Atareao\PocketIDLogin` del plugin. Permite login passwordless con PocketID vía OIDC **Authorization Code + PKCE (S256)**.
- Archivo: `includes/class-pocketid-login.php`.
- Registrado en `atareao-functionality.php` (`require_once` + `\Atareao\PocketIDLogin::init()` dentro de `atareao_functionality_init()`).
- **No crea usuarios automáticamente**: solo permite entrar a usuarios de WordPress ya existentes, emparejados **por email exacto**.
- Página de ajustes: **Ajustes → PocketID Login** (`/wp-admin/options-general.php?page=pocketid-login`).

### Requisitos previos

- Instancia PocketID accesible por HTTPS con discovery OIDC (`/.well-known/openid-configuration`).
- Cliente OIDC en PocketID con **Callback/Redirect URL = `wp_login_url()`** del sitio, p. ej. `https://<sitio>/wp-login.php` (debe coincidir carácter a carácter; PocketID admite comodines).
- Scopes `openid profile email`. PKCE soportado.
- **El email debe estar verificado en PocketID** (ver el apartado "Email verificado en PocketID").
- Un usuario de WordPress cuyo email coincida con el de la cuenta de PocketID.

### Activación paso a paso

#### A) En PocketID

1. Crear cliente OIDC (nombre, p. ej. `atareao.es`).
2. Callback URL = `https://<sitio>/wp-login.php`.
3. Copiar **Client ID** (UUID) y **Client Secret**.

#### B) En WordPress (Ajustes → PocketID Login)

1. **URL de Pocket ID**: base con `https://` (p. ej. `https://id.<dominio>`), sin barra final.
2. **Client ID** y **Client Secret** (si ya hay uno guardado, dejar el campo en blanco para conservarlo).
3. Guardar.
4. Botón **"Probar conexión"**: debe responder "Conexión correcta" y listar Autorización / Token / Userinfo. Si falla, comprobar desde el servidor:

   ```bash
   curl -s <POCKETID_URL>/.well-known/openid-configuration
   ```

5. Toggle **"Exigir PocketID"**:
   - Desmarcado → login por contraseña operativo + botón "Iniciar sesión".
   - Marcado → `wp-login.php` redirige a PocketID y bloquea el formulario de contraseña. NO afecta a application passwords, XML-RPC ni REST. `logout`, `lostpassword`, `rp`/`resetpass`, `postpass`, `register` siguen nativos (vía de escape).

#### C) Orden recomendado para no quedarse fuera

1. Configurar con "Exigir" DESMARCADO.
2. Probar el login en ventana de incógnito.
3. Registrar **≥2 passkeys** en PocketID.
4. Solo entonces, marcar "Exigir PocketID" y guardar.
5. Break-glass: `wp-login.php?action=lostpassword` (nativo) y WP-CLI.

### Cierre de sesión (logout)

El plugin separa dos cosas que WordPress no distingue por defecto: el cierre de la **sesión local** y el cierre de la **sesión en el proveedor** (RP-initiated logout).

- Al solicitar `wp-login.php?action=logout`, WordPress destruye la sesión local y, si el discovery de PocketID publica `end_session_endpoint`, el navegador se redirige al proveedor con `id_token_hint` (el `id_token` obtenido en el login), `client_id` y `post_logout_redirect_uri`, de forma que la sesión SSO de PocketID también termina. Esto es imprescindible con **"Exigir PocketID"** activo: sin cerrar la sesión del proveedor, la petición posterior volvería a autenticar en silencio.
- **Caché versionada del discovery:** la configuración cacheada incluye una versión de esquema (`CONFIG_SCHEMA = 2`). Una caché escrita por una versión anterior del plugin (sin esa versión) se considera obsoleta y se refresca sola; así se repuebla `end_session_endpoint`, que puede faltar en cachés antiguas aunque el proveedor sí lo publique. `end_session_endpoint` sigue siendo **opcional**: no condiciona la validez de la caché, pero la versión de esquema sí. No hace falta limpiar el transient a mano.
- **Refresco puntual en el logout:** si en el momento del logout la configuración disponible no trae `end_session_endpoint`, el plugin refresca el discovery **una única vez** (`getOIDCConfig(true)`, que ya reintenta internamente y no genera bucles) antes de recurrir al logout local. Tras el refresco, si el proveedor lo publica se redirige a él; si sigue sin publicarlo, el logout local se completa sin error.
- La petición resultante (`wp-login.php?loggedout=true`) respeta el estado post-logout y muestra la pantalla nativa "Has cerrado la sesión" en lugar de reiniciar el flujo OIDC.
- **Pantalla post-logout limpia con enforce:** en `GET wp-login.php?loggedout=true` con "Exigir PocketID" activo, el plugin oculta por CSS el formulario de contraseña inerte (y la navegación/recuperación de contraseña nativas) y muestra, bajo el aviso nativo de sesión cerrada, un botón **"Iniciar sesión"** que reinicia el flujo OIDC. El bloqueo del POST con `log`+`pwd` sigue siendo de servidor, no depende del CSS. En el resto de pantallas en modo exigir (p. ej. `lostpassword`) no se añade nada y la página nativa queda tal cual.
- El `id_token` se guarda **server-side** en un transient ligado al usuario (`atareao_pocketid_idtoken_<user_id>`) y se elimina al cerrar sesión; nunca se expone en cookies legibles por el navegador ni en la interfaz.
- **Fail-safe:** si PocketID no publica `end_session_endpoint` (ni tras el refresco), si no hay `id_token` o si la redirección no es posible, el logout local se completa igualmente y se usa el destino nativo de WordPress. El detalle queda en el log (`[atareao-pocketid]`).

> **Regla de UI pública:** ningún texto de la interfaz pública (botón, avisos ni mensajes de error) nombra al proveedor de identidad. El botón que inicia el flujo OIDC dice únicamente **"Iniciar sesión"** y el mensaje de bloqueo de contraseña invita a usar ese botón. El nombre del proveedor ("Pocket ID") aparece solo en la página de **Ajustes** (solo administradores).

> **Registro obligatorio:** en el cliente OIDC de PocketID hay que registrar como **Post Logout Redirect URI** el valor que muestra la página de ajustes (Ajustes → PocketID Login), que es `wp_login_url()` + `?loggedout=true` (p. ej. `https://<sitio>/wp-login.php?loggedout=true`). Este campo informativo es de solo lectura/copia y se muestra en la sección **Post Logout Redirect URI** de la página de ajustes. Si no coincide carácter a carácter, el proveedor rechazará el cierre de sesión (el usuario vería el error del proveedor, no del sitio).

### Problemas encontrados y soluciones

| Síntoma | Causa | Solución |
| --- | --- | --- |
| **403: "La solicitud de inicio de sesión no es válida o ha expirado."** | El `state`/cookie/código son de **un solo uso** (TTL 15 min) o se reutilizó la URL del callback; también pasa si el login empezó en un host distinto (www vs sin-www). | Lanzar el flujo limpio desde `wp-login.php` y no reusar la URL de callback. |
| **403: "Acceso denegado: el usuario no está registrado en este sitio."** | No existe usuario de WordPress con el email devuelto por PocketID (no hay auto-provisión). | Ajustar el email del usuario WP o el de la cuenta PocketID para que coincidan. Comprobar con `wp user list --fields=user_login,user_email`. |
| Log `[atareao-pocketid] Email no verificado para: <email>` | PocketID devuelve el claim `email_verified: false`. | En PocketID: **Administración → Configuración de la aplicación → General → activar "Correos electrónicos verificados de forma predeterminada"** (equivale a la env var `EMAILS_VERIFIED=true`) y/o **Usuarios → seleccionar usuario → "Marcar como verificado"**. Si PocketID usa `UI_CONFIG_DISABLED=true`, usar `EMAILS_VERIFIED=true`. |
| **502 `upstream sent too big header while reading response header from upstream`** en el callback | Al completar el login, WordPress emite varias cabeceras `Set-Cookie` (cookies de sesión) que superan el buffer de cabeceras FastCGI por defecto de nginx. La causa concreta: existe un `location = /wp-login.php` (coincidencia exacta, prioridad máxima) que NO tenía los buffers, mientras los `fastcgi_buffer_size 128k` estaban en `location ~ \.php$`, que nunca se alcanza para `/wp-login.php`. | Ver la sección de nginx. |
| El callback devuelve 200 en vez de 302 | El plugin no procesó el callback porque ya había sesión (`is_user_logged_in()`) o la configuración estaba incompleta. | Un callback correcto devuelve **302 → /wp-admin/**. |

### Email verificado en PocketID

Por seguridad, el plugin rechaza por defecto los logins cuyo claim `email_verified` sea falso. Solo acepta los valores `false`, `0`, `"false"`, `"0"` como no verificado; cualquier otro valor se considera verificado. Si el claim **no está presente** en la respuesta de userinfo, el login no se bloquea.

La exigencia es configurable con el ajuste **`atareao_pocketid_require_verified_email`** (por defecto `1` = estricto):

- **Activo (`1`, por defecto):** un `email_verified=false` se rechaza con la 403 genérica y se registra el motivo (`[atareao-pocketid] Email no verificado (política estricta): <email>`).
- **Inactivo (`0`):** el login continúa y se registra que el email no está verificado (`[atareao-pocketid] Email no verificado aceptado (política estricta desactivada): <email>`), para que quede traza.

En la página de ajustes (Ajustes → PocketID Login) el checkbox **"Correo verificado"** (etiqueta "Exigir correo verificado") controla este ajuste. Para relajarlo sin entrar en la UI:

```bash
just wp -- option update atareao_pocketid_require_verified_email 0   # permitir correos sin verificar
just wp -- option update atareao_pocketid_require_verified_email 1   # volver al modo estricto (default)
just wp -- option get atareao_pocketid_require_verified_email
```

Rutas de la UI en español para marcar el email como verificado (recomendado frente a relajar el ajuste):

- **Global:** Administración → Configuración de la aplicación → pestaña General → **"Correos electrónicos verificados de forma predeterminada"**.
- **Por usuario:** Usuarios → seleccionar usuario → **"Marcar como verificado"**.
- **Variable de entorno:** `EMAILS_VERIFIED=true` (requiere `UI_CONFIG_DISABLED=true`).

### Cookie de estado del flujo OIDC

- La cookie `atareao_pocketid_oauth` guarda el `state` y el `code_verifier` del flujo. Es **host-only**: se emite **sin el atributo `Domain`** (`'domain' => ''`), por lo que el navegador no la envía a los subdominios del sitio ni al subdominio del proveedor de identidad (`pocketid.<dominio>`), con independencia de `COOKIE_DOMAIN`. Nota: pasar el host exacto (`atareao.es`) como `Domain` **no** sería host-only, porque por RFC 6265 un `Domain` no vacío cubre también los subdominios. El resto de atributos se conserva: `path` = `COOKIEPATH`, `Secure`, `HttpOnly` y `SameSite=Lax`.
- El `state` del servidor (transient anti-replay) y la cookie comparten un **TTL único** de **15 minutos** (`STATE_TTL`), de modo que un prompt de passkey lento o un reintento no caigan en la 403. El `state` sigue siendo de un solo uso: el transient se consume en el callback y la cookie se borra al inicio de cada callback, tanto en éxito como en error.
- El `code_verifier` nunca sale del host que inicia el flujo; no se registra en el log.

### Diagnóstico de callbacks

Cada rechazo del callback registra en el log `[atareao-pocketid]` la causa concreta, sin exponerla al usuario (que siempre ve la 403 genérica) y **sin volcar el valor del `state`, del `code` ni del `code_verifier`**:

| Log | Causa |
| --- | --- |
| `Callback rechazado: cookie de estado ausente.` | No llegó la cookie `atareao_pocketid_oauth` (o llegó vacía). Típico en una recarga/reintento de la URL de callback. |
| `Callback rechazado: cookie de estado ilegible (JSON inválido o sin state/code_verifier).` | La cookie llegó pero no se pudo decodificar o le faltan campos. |
| `Callback rechazado: state ausente o expirado (transient no encontrado).` | El transient del state no existe: nunca se creó, ya se consumió o expiró. |
| `Callback rechazado: replay de un state ya consumido (verifier no coincide).` | El transient existía pero el `code_verifier` no coincide: reutilización del state. |
| `Callback rechazado: state mismatch (no coincide con la query).` | El `state` de la query no coincide con el de la cookie. |
| `Callback rechazado: falta el parámetro code.` | El callback llegó sin `code`. |

### Configuración de nginx

**Gotcha de precedencia de locations:** la coincidencia exacta `location = /wp-login.php` tiene prioridad sobre la regex `location ~ \.php$`. Por eso los buffers del bloque genérico NO aplican al login.

Bloque ANTES (causa el 502):

```nginx
location = /wp-login.php {
    limit_req zone=login burst=5 delay=3;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass wordpress:9000;
}
```

Bloque DESPUÉS (con el fix):

```nginx
location = /wp-login.php {
    limit_req zone=login burst=5 delay=3;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass wordpress:9000;

    # Fix: la respuesta del login lleva varias Set-Cookie → buffer grande
    fastcgi_buffering on;
    fastcgi_buffer_size 32k;
    fastcgi_buffers 8 32k;
    fastcgi_busy_buffers_size 64k;
}
```

Comandos de validación y recarga (docker compose, servicio `nginx`):

```bash
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
# diagnóstico: ver la config efectiva
docker compose exec nginx nginx -T 2>&1 | grep -n 'fastcgi_buffer_size'
```

Notas:

- Esta config de **producción vive fuera del repositorio** (p. ej. `~/docker/wordpress/nginx/wp.conf`). La de desarrollo del repo (`nginx/default.conf`) no tiene este problema (no define un `location = /wp-login.php`).
- Detalle opcional de limpieza: en `location ~ \.php$` puede haber `fastcgi_cache_bypass` duplicado; nginx los acumula (los evalúa como OR), así que la primera línea es redundante (`fastcgi_cache_bypass $skip_cache;` seguida de `fastcgi_cache_bypass $skip_cache$purge_active;`). Eliminarla no cambia el comportamiento.
- En la config hay un `map` de purga con un token; se documenta con `<PURGE_TOKEN>`.

### Seguridad y 2FA

- Las passkeys (WebAuthn) ya son multifactor: **posesión** (dispositivo/clave) + **verificación del usuario** (biometría/PIN).
- PocketID **no pasa por el filtro `authenticate`**: crea la sesión directamente (`wp_set_current_user` + `wp_set_auth_cookie` + `do_action('wp_login')`). Por eso, un plugin de 2FA (TOTP) que enganche en `authenticate` **queda inerte** para los logins de PocketID.
- Con "Exigir PocketID" activo, un plugin de 2FA TOTP es **redundante** para el login interactivo.
- Lo que **ninguno** cubre: **application passwords**, **XML-RPC**, **REST**, y la **recuperación de emergencia por email** (`lostpassword`/`rp`, que es nativa por diseño).
- Recomendación de retirada gradual: no quitar el 2FA hasta tener PocketID forzado y probado; desactivarlo (no borrarlo) y observar unos días.
- Aviso: un plugin de 2FA que enganche en `wp_login` (no solo `authenticate`) SÍ se dispara tras un login de PocketID y podría causar fricción.

### Opciones y comandos de gestión (WP-CLI)

Opciones:

- `atareao_pocketid_url`
- `atareao_pocketid_client_id`
- `atareao_pocketid_client_secret`
- `atareao_pocketid_enforce`
- `atareao_pocketid_require_verified_email` (por defecto `1` = exigir `email_verified`; `0` lo relaja)

Transient de caché del discovery: `atareao_pocketid_oidc_config` (12 h).

Comandos (wrapper del repo):

```bash
just wp -- option get atareao_pocketid_url
just wp -- option get atareao_pocketid_enforce
just wp -- option get atareao_pocketid_require_verified_email
just wp -- option update atareao_pocketid_enforce 0     # dejar de forzar
just wp -- option update atareao_pocketid_require_verified_email 0   # permitir email sin verificar
just wp -- option update atareao_pocketid_require_verified_email 1   # volver al modo estricto
just wp -- option update atareao_pocketid_url ''        # desactivar del todo (isConfigured()=false)
just wp -- option delete atareao_pocketid_enforce
just wp -- option delete atareao_pocketid_require_verified_email
just wp -- transient delete atareao_pocketid_oidc_config
```

`isConfigured()` exige URL + client_id + client_secret no vacíos; vaciar cualquiera de los tres desactiva por completo el comportamiento del plugin.

### Logs

Prefijo `[atareao-pocketid]` vía `error_log()`. Cómo verlos:

```bash
journalctl --user -u atareao-wordpress --since "15 min ago" | grep atareao-pocketid
```

### Desactivación

- **Dejar de forzar:** desmarcar "Exigir PocketID" (o `option update atareao_pocketid_enforce 0`).
- **Desactivar del todo sin tocar el plugin:** vaciar una credencial.
- **Desactivar el plugin:** `just wp -- plugin deactivate atareao-functionality`.
- **Borrado definitivo:** borrar las 5 opciones y el transient.

## Estructura de Archivos

```
atareao-functionality/
├── atareao-functionality.php  # Archivo principal del plugin
├── includes/
│   ├── class-post-types.php   # Registro de CPTs
│   ├── class-taxonomies.php   # Registro de taxonomías
│   ├── class-metaboxes.php    # Metaboxes personalizados
│   ├── class-pocketid-login.php # Login OIDC con PocketID (passkeys/WebAuthn)
│   └── class-podcast-block.php # Bloque de reproductor de podcast
├── assets/
│   └── blocks/
│       └── podcast-player/    # Bloque de Gutenberg
│           ├── block.json     # Configuración del bloque
│           ├── index.js       # JavaScript del editor
│           ├── style.css      # Estilos del frontend
│           ├── editor.css     # Estilos del editor
│           └── README.md      # Documentación del bloque
├── languages/                  # Archivos de traducción
├── README.md                   # Este archivo
└── BLOQUE-PODCAST.md          # Guía rápida del bloque
```

## Desarrollo

### Hooks Disponibles

El plugin ejecuta las siguientes acciones que puedes utilizar:

- `init` - Registro de post types y taxonomías
- `add_meta_boxes` - Añadir metaboxes personalizados
- `save_post` - Guardar datos de metaboxes

### Funciones de Utilidad

Puedes obtener capítulos de un tutorial con:

```php
$chapters = new WP_Query(array(
    'post_type' => 'chapter',
    'meta_key' => '_tutorial_id',
    'meta_value' => $tutorial_id,
    'orderby' => 'menu_order',
    'order' => 'ASC'
));
```

## Changelog

### Versión 1.0.0

- Lanzamiento inicial
- 5 Custom Post Types
- 7 Taxonomías personalizadas
- Metaboxes para campos adicionales
- Relación Tutorial-Capítulo
- **NUEVO**: Bloque de Gutenberg "Reproductor de Podcast"
  - Selector de podcasts existentes
  - URL personalizada para archivos externos
  - Reproductor HTML5 responsive
  - Integración con REST API de WordPress

## Créditos

- Desarrollado por: Atareao
- URL: https://atareao.es
- Licencia: GPL v2 o posterior

## Soporte

Para soporte y dudas, visita [atareao.es](https://atareao.es)

## Licencia

Este plugin es software libre; puedes redistribuirlo y/o modificarlo bajo los términos de la Licencia Pública General de GNU según lo publicado por la Free Software Foundation; ya sea la versión 2 de la Licencia, o (a tu elección) cualquier versión posterior.
