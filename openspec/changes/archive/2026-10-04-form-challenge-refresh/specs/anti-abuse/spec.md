# anti-abuse Delta

## Purpose

Este delta añade a la capability `anti-abuse` un requisito de **challenge de formulario renovable**: el material antiabuso (instante, operandos del captcha, firma HMAC y nonce) SHALL poder emitirse desde un endpoint no cacheable y refrescarse en el cliente, de forma que un documento servido desde caché durante más tiempo que la cota superior de `form_time` siga siendo enviable. No cambia el resto de requisitos (rate limiting, dedupe, firma con el tiempo, cotas, limitación documentada del captcha).

## ADDED Requirements

### Requirement: Challenge de formulario renovable sin recargar (compatible con caché HTML)

El sistema SHALL ofrecer un endpoint público **no cacheable** que emita un challenge de formulario fresco —el instante (`form_time`), los operandos `a`/`b`, la firma HMAC y el nonce correspondiente— para el formulario de contacto y para el de comentarios. El material del challenge SHALL poder renovarse en el cliente al cargar la página, de modo que un documento servido desde caché durante más tiempo que la cota superior vigente de `form_time` (3600 s) SHALL seguir pudiendo enviarse con éxito. El endpoint SHALL NOT servirse desde la caché de HTML y SHALL enviar cabeceras de no-caché; su respuesta SHALL conservar la misma forma de challenge que el render en servidor. Sin JavaScript, SHALL conservarse el challenge renderizado en servidor como *fallback*, con la limitación documentada de que en páginas muy cacheadas puede caducar. Los nombres de campos, nonces, hooks, la firma HMAC y la validación server-side SHALL conservarse.

#### Scenario: Página servida desde caché con challenge caducado

- **GIVEN** una página de contacto o de comentarios servida desde caché con una antigüedad superior a 3600 s
- **WHEN** el visitante la abre con JavaScript habilitado
- **THEN** el cliente obtiene un challenge fresco del endpoint y el formulario se puede enviar con éxito

#### Scenario: El endpoint no se cachea

- **WHEN** se solicita el challenge del formulario
- **THEN** la respuesta procede del servidor (no de la caché de HTML), refleja el instante actual y va con cabeceras de no-caché

#### Scenario: El challenge emitido es válido para la validación vigente

- **WHEN** el cliente envía el formulario con el `form_time`, `a`, `b`, `sig` y nonce devueltos por el endpoint
- **THEN** la validación server-side acepta la firma y la ventana temporal, sin cambiar el contrato existente

#### Scenario: Fallback sin JavaScript

- **GIVEN** un visitante sin JavaScript
- **WHEN** abre un formulario servido desde caché con más de 3600 s
- **THEN** se usa el challenge renderizado en servidor y el comportamiento es el documentado (puede rechazarse por caducidad)

#### Scenario: No cambia el contrato de validación

- **WHEN** se comparan campos, nonces, hooks y validación antes y después
- **THEN** los nombres de campo, los nonces, los hooks, la firma HMAC y las cotas no cambian
