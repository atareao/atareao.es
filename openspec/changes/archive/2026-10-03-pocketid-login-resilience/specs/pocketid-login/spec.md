# pocketid-login Specification (delta)

## ADDED Requirements

### Requirement: State cookie is scoped to the login host

La cookie de estado OIDC (`atareao_pocketid_oauth`) SHALL emitirse como **host-only**, es decir, **sin el atributo `Domain`** en la cabecera `Set-Cookie` (un `Domain` no vacío, aunque sea el host exacto, cubriría por RFC 6265 también sus subdominios). De este modo la cookie nunca se envía al subdominio del proveedor de identidad (`pocketid.<dominio>`). El resto de atributos de la cookie (`path`, `Secure`, `HttpOnly`, `SameSite=Lax`) SHALL conservarse.

#### Scenario: Host-only cookie
- **GIVEN** el plugin configurado y un flujo de autorización iniciado desde el host del sitio
- **WHEN** el plugin emite la cookie de estado OIDC
- **THEN** la cabecera `Set-Cookie` **no incluye el atributo `Domain`** y la cookie no se envía a `pocketid.<dominio>`

#### Scenario: Existing cookies unaffected
- **GIVEN** que existen cookies de estado de otros subdominios del dominio
- **WHEN** el plugin emite la cookie de estado OIDC del sitio
- **THEN** la nueva cookie es host-only y las cookies de otros subdominios no se ven alteradas

### Requirement: State lifetime tolerates realistic login durations

La cookie de estado OIDC y el state single-use del servidor (transient anti-replay) SHALL compartir un TTL único y suficiente para completar un prompt de passkey o un reintento (por ejemplo, 15 minutos). Ambos SHALL eliminarse al completar o fallar el flujo: la cookie en cada callback y el transient al consumirlo, con independencia del resultado.

#### Scenario: Slow passkey prompt
- **GIVEN** un login iniciado y un usuario que tarda hasta el TTL definido en completar el prompt de passkey
- **WHEN** el navegador vuelve al callback dentro del TTL
- **THEN** el callback valida el state correctamente y el flujo continúa

#### Scenario: Cleanup
- **GIVEN** un callback procesado, tanto con éxito como con error
- **WHEN** el plugin termina de procesarlo
- **THEN** la cookie de estado y el transient del state quedan eliminados

### Requirement: Callback failures are diagnosable

Ante un callback inválido, el plugin SHALL registrar en el log del servidor, con el prefijo `[atareao-pocketid]`, la causa concreta del fallo —cookie ausente, cookie ilegible, state ausente/expirado, replay o mismatch— sin exponer detalles al usuario, que SHALL ver la pantalla 403 genérica.

#### Scenario: Missing cookie
- **GIVEN** un callback OIDC sin la cookie de estado
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "cookie ausente" y el usuario ve la pantalla 403 genérica

#### Scenario: Replay
- **GIVEN** un state ya consumido (replay) que vuelve a llegar al callback
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "replay" y el usuario ve la pantalla 403 genérica

#### Scenario: Expired state
- **GIVEN** un state cuyo transient ha expirado
- **WHEN** el plugin procesa el callback
- **THEN** se registra la causa "expirado" y el usuario ve la pantalla 403 genérica

### Requirement: Configurable email verification policy

El sistema SHALL exponer un ajuste (`atareao_pocketid_require_verified_email`), por defecto activado, que determine si se exige `email_verified === true` en la respuesta de userinfo. El ajuste SHALL leerse **solo en servidor**. Con el ajuste activo, un `email_verified=false` SHALL rechazarse con la 403 genérica y SHALL registrarse el motivo. Con el ajuste inactivo, el login SHALL continuar y SHALL registrarse que el email no está verificado. El mensaje mostrado al usuario SHALL seguir siendo genérico y sin nombrar al proveedor de identidad.

#### Scenario: Default strict
- **GIVEN** el ajuste por defecto (activado)
- **WHEN** la respuesta de userinfo trae `email_verified=false`
- **THEN** se rechaza con la 403 genérica y se registra el motivo

#### Scenario: Lenient
- **GIVEN** el ajuste desactivado
- **WHEN** la respuesta de userinfo trae `email_verified=false`
- **THEN** el login continúa y se registra que el email no está verificado

#### Scenario: Claim absent
- **GIVEN** una respuesta de userinfo que no incluye el claim `email_verified`
- **WHEN** el plugin evalúa la verificación
- **THEN** no se bloquea el login (comportamiento actual preservado)
