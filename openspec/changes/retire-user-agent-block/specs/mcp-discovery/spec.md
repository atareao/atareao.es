# MCP Discovery Delta

## MODIFIED Requirements

### Requirement: Canales auxiliares de descubrimiento ARD

Además del well-known URI, el sistema SHALL anunciar el origen de entradas ARD por los canales auxiliares que define el estándar:

- **`robots.txt`**: SHALL incluir la directiva `Agentmap:` apuntando a la URL **absoluta** del manifiesto ARD. El `robots.txt` del sitio es **virtual** —lo genera WordPress y lo filtra The SEO Framework; no existe ningún fichero `robots.txt` en el webroot— de modo que la directiva SHALL añadirse mediante el filtro `robots_txt` y SHALL NOT depender de un fichero no versionado.
- **`<head>`**: SHALL incluir `<link rel="ard" href="…">` con la URL absoluta del manifiesto, en toda petición que ejecute `wp_head`.
- **Proxy inverso**: las rutas de descubrimiento SHALL ser alcanzables por clientes HTTP. El proxy de producción SHALL NOT aplicar un filtro por User-Agent que cierre la conexión (`444`); ese filtro se retiró el 2026-11-13 porque un User-Agent se falsea trivialmente y solo ocultaba el contenido publicado a crawlers y validadores legítimos. Las rutas SHALL servirse con su código real (`200` si existe, `404` si no existe) y nunca un `502` de puerta de enlace.
- **DNS**: el binding de servicio (`_entries._agents.…` / `_search._agents.…`) queda **fuera de alcance**: reside en la zona DNS, que no vive en este repositorio.

Los canales SHALL ser redundantes entre sí —si uno no se consulta, los otros siguen apuntando al mismo manifiesto— y SHALL NOT exponer credenciales ni datos internos.

#### Scenario: `robots.txt` declara el Agentmap

- **WHEN** un cliente solicita `GET /robots.txt`
- **THEN** la respuesta incluye una línea `Agentmap:` con la URL absoluta de `/.well-known/ard.json`

#### Scenario: El `<head>` declara la relación `ard`

- **WHEN** se inspecciona el `<head>` de una página pública
- **THEN** contiene `<link rel="ard">` apuntando a la URL absoluta del manifiesto

#### Scenario: Las rutas de descubrimiento no sufren el bloqueo por User-Agent

- **WHEN** un cliente HTTP solicita una ruta de descubrimiento
- **THEN** el proxy inverso la sirve con su código real y no responde `444`

#### Scenario: Los canales apuntan al mismo manifiesto

- **WHEN** se comparan las URLs declaradas por el `Agentmap`, por el `<link rel="ard">` y la ruta well-known
- **THEN** las tres son la misma URL absoluta

#### Scenario: El binding DNS no es requisito

- **WHEN** se evalúa la conformidad del descubrimiento
- **THEN** la ausencia de registros DNS `_agents` no incumple ningún requisito de esta capability

#### Scenario: El prefijo well-known de MCP no sufre el bloqueo

- **WHEN** un cliente HTTP solicita `/.well-known/mcp` o `/.well-known/mcp/server-card`
- **THEN** el proxy inverso devuelve el código real de la ruta (`200` si existe, `404` si no existe) y nunca un `502` de puerta de enlace
