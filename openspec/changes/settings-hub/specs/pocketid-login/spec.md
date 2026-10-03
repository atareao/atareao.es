# Spec Delta

## MODIFIED Requirements

### Requirement: Settings page with connectivity test

El sistema SHALL exponer la configuración de Pocket ID en la pestaña «PocketID» del hub de ajustes «Atareao» (`options-general.php?page=atareao-settings&tab=pocketid`), con los campos URL de Pocket ID, Client ID, Client Secret (patrón "dejar en blanco para conservar el actual"), el toggle "Exigir PocketID" y un botón "Probar conexión" que descarga el discovery, valida la respuesta y muestra los tres endpoints detectados. El guardado SHALL protegerse con nonce (`check_admin_referer`) y la URL SHALL validarse como `https://`. La pestaña SHALL mostrar el Redirect URI (`wp_login_url()`) que debe registrarse en Pocket ID.

#### Scenario: Connectivity test succeeds

- **WHEN** la URL configurada responde con un discovery válido
- **THEN** se muestran los endpoints de autorización, token y userinfo detectados

#### Scenario: Connectivity test fails

- **WHEN** la URL es inalcanzable o el discovery es inválido
- **THEN** se muestra un mensaje de error genérico y se registra el detalle en el log

#### Scenario: Secret field left blank

- **WHEN** se guardan los ajustes con el campo Client Secret vacío
- **THEN** se conserva el secret almacenado previamente
