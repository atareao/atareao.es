# REST Access Policy Delta

## Purpose

Este delta amplía la capability `rest-access-policy` para que la **puerta de autenticación REST anónima** reconozca como pública la **ruta efectiva del alias limpio `/mcp`**, que el plugin normaliza hacia la ruta REST `/atareao/v1/mcp`. La exención sigue siendo **por ruta exacta** y no por la mera presencia de la cadena: el alias exacto es público; cualquier ruta que lo contenga como prefijo común o subcadena sigue rechazada. No se afloja ninguna otra comprobación.

## ADDED Requirements

### Requirement: Exención anónima de la ruta efectiva del alias `/mcp`

El sistema SHALL resolver, entre las fuentes de la ruta efectiva, la del **alias limpio `/mcp`** normalizado por el plugin, y SHALL reconocerla como la ruta pública `/atareao/v1/mcp`, de modo que un `POST` anónimo al alias no sea rechazado por falta de sesión. La exención SHALL seguir resolviéndose por **igualdad exacta** de la ruta efectiva con la ruta pública: el alias `/mcp` exacto es público; una ruta con prefijo común (`/mcp-extra`) o con el alias como subcadena (`/foo/mcp`) SHALL NOT eximirse. Ninguna otra ruta SHALL eximirse por este mecanismo, y las peticiones con sesión iniciada —incluidas las autenticadas por Application Password— SHALL seguir sin ser rechazadas.

#### Scenario: `POST` anónimo al alias limpio

- **WHEN** se envía un `POST` anónimo a `/mcp`
- **THEN** la puerta no rechaza la petición por falta de sesión y esta llega al transporte MCP

#### Scenario: Una ruta con prefijo común NO exime

- **WHEN** se envía un `POST` anónimo a `/mcp-extra`
- **THEN** el sistema responde 401 y no la trata como pública

#### Scenario: El alias como subcadena NO exime

- **WHEN** se envía un `POST` anónimo a una ruta que contiene `/mcp` como subcadena (`/foo/mcp`)
- **THEN** el sistema responde 401 y no la exime

#### Scenario: La exención no afloja el resto de la puerta

- **WHEN** un visitante sin sesión envía un `POST` a cualquier otra ruta no pública
- **THEN** el sistema responde 401 como antes del cambio

#### Scenario: Sesión iniciada no se ve afectada

- **WHEN** una petición al alias la realiza un usuario con sesión iniciada o con Application Password válida
- **THEN** la puerta no la rechaza
