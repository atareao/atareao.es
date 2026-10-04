# rest-access-policy Delta

## Purpose

Esta capability define la **puerta de autenticación REST anónima** del sitio: el filtro `rest_authentication_errors` que impide que visitantes sin sesión invoquen métodos REST con efectos, y que exime de esa puerta a las rutas públicas de solo lectura. Su objetivo es que la exención sea **precisa y no ambigua** —la **ruta efectiva** exacta, no cualquier URI que contenga el texto— y que la puerta evalúe el **método efectivo** igual que WordPress, de modo que ni el *query string*, ni el *method override*, ni un `rest_route` en el cuerpo puedan esquivarla. Conserva el `401`, el mensaje neutro y el funcionamiento de las Application Passwords. No introduce rutas ni cambia el sitio público.

## ADDED Requirements

### Requirement: Puerta de autenticación REST anónima con exención por ruta exacta y método efectivo

El sistema SHALL rechazar con **HTTP 401** las peticiones REST anónimas cuyo **método efectivo** no sea de lectura (`GET`, `HEAD`, `OPTIONS`), usando `rest_authentication_errors`, con un **mensaje neutro** que no revele si la ruta existe ni detalles internos. El **método efectivo** SHALL determinarse con la misma semántica que WordPress: aplicando el *method override* (`$_GET['_method']` o la cabecera `X-HTTP-Method-Override`, en mayúsculas) **antes** de decidir si la petición es de lectura. Para permitir servicios públicos de solo lectura, el sistema SHALL eximir de esta puerta **únicamente** las rutas declaradas como públicas, y la exención SHALL resolverse comparando la **ruta REST efectiva normalizada por igualdad exacta** con la ruta pública (`/atareao/v1/mcp`). La **ruta efectiva** SHALL determinarse con la **misma precedencia que WordPress** —`rest_route` del **cuerpo POST** > `rest_route` de la **query** > reescritura del *path* (`/wp-json/<ruta>`)—, y SHALL NOT basarse en la mera presencia de la cadena en cualquier parte del URI ni del *query string*; ambos lados SHALL normalizar la barra final. Las peticiones con **sesión iniciada** —incluidas las autenticadas por **Application Password**— SHALL NOT ser rechazadas por esta puerta, y las peticiones anónimas de lectura SHALL seguir permitiéndose sin cambios.

#### Scenario: Exención exacta de la ruta pública

- **WHEN** se envía un POST anónimo a `/wp-json/atareao/v1/mcp`
- **THEN** la puerta no rechaza la petición por falta de sesión y esta llega al registro de rutas

#### Scenario: Exención en forma de parámetro

- **WHEN** se envía un POST anónimo a `/?rest_route=/atareao/v1/mcp`
- **THEN** la puerta no rechaza la petición por falta de sesión

#### Scenario: La subcadena en el *query string* NO exime

- **WHEN** se envía un POST anónimo a `/wp-json/wp/v2/posts?ref=/atareao/v1/mcp`
- **THEN** el sistema responde 401 y no trata la ruta como pública

#### Scenario: Una ruta con prefijo común NO exime

- **WHEN** se envía un POST anónimo a una ruta cuyo inicio coincide (`/wp-json/atareao/v1/mcp-extra` o `/wp-json/foo/atareao/v1/mcp`)
- **THEN** el sistema responde 401 y no la exime

#### Scenario: Barra final normalizada

- **WHEN** se envía un POST anónimo a `/wp-json/atareao/v1/mcp/`
- **THEN** el sistema reconoce la ruta pública y no la rechaza

#### Scenario: La ruta efectiva respeta la precedencia del cuerpo sobre el *path*

- **WHEN** se envía un POST anónimo a `/wp-json/atareao/v1/mcp` cuyo cuerpo declara `rest_route=/wp/v2/posts`
- **THEN** el sistema considera ruta efectiva `/wp/v2/posts`, responde 401 y no la trata como pública

#### Scenario: Ruta pública efectiva declarada en la query o en el cuerpo

- **WHEN** se envía un POST anónimo cuya ruta efectiva (por `rest_route` en la query o en el cuerpo) es `/atareao/v1/mcp`
- **THEN** la puerta no la rechaza por falta de sesión

#### Scenario: El *method override* no permite escritura anónima

- **WHEN** un visitante sin sesión envía un `GET`, `HEAD` u `OPTIONS` con `_method` o la cabecera `X-HTTP-Method-Override` fijados a un método con efectos (`POST`, `PUT`, `PATCH`, `DELETE`)
- **THEN** el sistema evalúa el método efectivo y responde 401

#### Scenario: Método de lectura anónimo permitido

- **WHEN** un visitante sin sesión realiza un `GET`, `HEAD` u `OPTIONS` a cualquier ruta REST sin *override*
- **THEN** la puerta no lo rechaza por falta de sesión

#### Scenario: Sesión iniciada no se ve afectada

- **WHEN** una petición REST la realiza un usuario con sesión iniciada, sea cual sea el método y la ruta
- **THEN** la puerta no la rechaza

#### Scenario: Application Password sigue funcionando

- **WHEN** una petición se autentica mediante Application Password válida
- **THEN** la puerta no la rechaza y el flujo de publicación sigue operativo

#### Scenario: Mensaje neutro

- **WHEN** la puerta rechaza una petición anónima con efectos
- **THEN** la respuesta es 401 con un mensaje genérico que no revela rutas, recursos ni detalles internos
