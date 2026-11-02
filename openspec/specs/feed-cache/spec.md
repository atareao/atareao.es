# feed-cache Specification

## Purpose
El contrato de la caché de feeds en el front (nginx): los feeds RSS son idénticos para todos los
lectores, así que SHALL servirse desde caché por defecto en lugar de golpear PHP en cada petición,
con una política explícita de caducidad, tolerancia a fallos del upstream e invalidación al publicar,
sin servir contenido caducado de forma indefinida.

## Requirements

### Requirement: Los feeds se cachean en el front

El proxy inverso SHALL cachear las respuestas de los feeds RSS del sitio (`/feed/` y los feeds de
archivo de tipo de contenido, como `/podcast/feed/`) mediante la caché de FastCGI. La ruta de feed
SHALL NOT estar excluida de la caché. La respuesta cacheada SHALL preservar las cabeceras de origen,
en particular el `Content-Type` del feed (`application/rss+xml; charset=UTF-8`).

La entrada de caché de un feed SHALL distinguirse por su URI y SHALL servirse, con `HIT`, a los
lectores anónimos. Las respuestas SHALL cachearse con una **caducidad corta** acotada (p. ej. minutos),
de modo que el contenido se refresque aun sin purga explícita. Las peticiones con sesión de WordPress
(`wordpress_logged_in`) SHALL seguir **evitando** la caché (bypass), de forma que un autor vea el feed
fresco.

**File:** `nginx/default.conf`, `nginx/pro/wp.conf`

#### Scenario: Lectura anónima servida desde caché

- **WHEN** dos lectores anónimos solicitan el mismo feed en el intervalo de la caducidad
- **THEN** la primera petición puebla la caché y la segunda se sirve con `X-Cache-Status: HIT` sin llegar a PHP

#### Scenario: La caché distingue feeds por URI

- **WHEN** se solicitan `/feed/` y `/podcast/feed/`
- **THEN** cada feed tiene su propia entrada de caché y no se sirve uno por el otro

#### Scenario: El feed cacheado conserva el Content-Type

- **WHEN** un feed se sirve desde caché
- **THEN** la respuesta mantiene `Content-Type: application/rss+xml; charset=UTF-8`

#### Scenario: Sesión iniciada evita la caché

- **WHEN** una petición de feed lleva la cookie `wordpress_logged_in`
- **THEN** la respuesta se genera en PHP (bypass de caché) y no se sirve desde la copia cacheada

### Requirement: Tolerancia a un upstream lento o caído

Cuando el upstream (PHP-FPM) falle o agote el tiempo al componer un feed, el proxy inverso SHALL
servir la copia **obsoleta** disponible (`stale`) antes que devolver un error o cortar la conexión, y
SHALL refrescar la entrada en segundo plano. Un fallo del upstream SHALL NOT producir un timeout ni un
`5xx` para el lector si existe una copia cacheada.

**File:** `nginx/pro/wp.conf`

#### Scenario: Upstream lento sirve la copia obsoleta

- **WHEN** PHP-FPM tarda más allá del tiempo de espera al componer un feed que ya tiene copia cacheada
- **THEN** el proxy sirve la copia cacheada obsoleta en lugar de devolver `504` y no corta la conexión

#### Scenario: La entrada caducada se refresca sin bloquear al lector

- **WHEN** la caché de un feed está caducada y llega una petición
- **THEN** el proxy sirve la copia obsoleta y lanza el refresco en segundo plano, bloqueando la regeneración con `cache_lock`

### Requirement: Invalidación del feed cacheado

Publicar o actualizar contenido SHALL invalidar las entradas de caché de los feeds afectados (el feed
principal y el feed del tipo de contenido), de modo que el contenido nuevo aparezca sin esperar a la
caducidad. La invalidación SHALL usar el mismo mecanismo autenticado de purga del sitio.

**File:** `wp-content/plugins/atareao-functionality/includes/class-cache-purge.php`, `nginx/pro/wp.conf`

#### Scenario: Publicar invalida el feed

- **WHEN** se publica un podcast y la purga del feed del tipo de contenido tiene éxito
- **THEN** la siguiente petición a `/podcast/feed/` no se sirve desde la copia cacheada anterior

#### Scenario: Sin purga, la caducidad acota el contenido obsoleto

- **WHEN** una entrada de caché de feed no recibe purga
- **THEN** deja de servirse dentro de su caducidad acotada, de modo que el feed nunca queda obsoleto de forma indefinida
