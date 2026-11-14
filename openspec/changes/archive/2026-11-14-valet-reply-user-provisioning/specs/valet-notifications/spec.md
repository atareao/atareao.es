# Valet Notifications Delta

## ADDED Requirements

### Requirement: Aprovisionamiento del usuario de servicio desde Ajustes

El sistema SHALL permitir aprovisionar el **usuario de servicio** de Valet desde la página de Ajustes → Valet (pestaña `valet`, `ValetConfig::renderConfigPage()`), sin depender de WP-CLI. La página SHALL presentar un control de selección (`<select>`) poblado con los usuarios del sitio (`get_users`) más una opción «— Ninguno —» con valor `0`. Al guardar, el valor SHALL validarse con `absint` y SHALL persistirse en la opción `atareao_valet_reply_user`; un valor que no corresponda a un usuario existente SHALL degradarse a `0`. El guardado SHALL reutilizar el nonce `atareao_valet_config`, el campo `atareao_valet_save` y la capacidad `manage_options` ya existentes. Cuando `atareao_valet_url` y `atareao_valet_token` estén configurados y `atareao_valet_reply_user` sea `0`, la página SHALL mostrar un aviso visible (`notice notice-warning`) indicando que la vía de respuesta está inerte. El sistema SHALL seguir **fallando a cerrado**: sin usuario de servicio (opción `0`), la colección `/wp/v2/comments` SHALL permanecer eliminada para todos los peticionarios.

#### Scenario: Aprovisionamiento desde la UI

- **WHEN** un administrador selecciona un usuario en la pestaña «Valet» y guarda
- **THEN** la opción `atareao_valet_reply_user` queda con el ID de ese usuario y `isServiceRequest()` es verdadero para esa identidad

#### Scenario: Ningún usuario seleccionado

- **WHEN** un administrador guarda la pestaña «Valet» con la opción «— Ninguno —»
- **THEN** la opción `atareao_valet_reply_user` queda a `0` y la colección `/wp/v2/comments` permanece eliminada para todos los peticionarios

#### Scenario: ID inexistente

- **WHEN** el POST envía un ID que no corresponde a ningún usuario existente
- **THEN** la opción se degrada a `0` (no se acepta basura) y la colección permanece eliminada

#### Scenario: Aviso de vía inerte

- **WHEN** `atareao_valet_url` y `atareao_valet_token` están configurados y el usuario de servicio es `0`
- **THEN** la página muestra un aviso `notice notice-warning` indicando que la vía de respuesta está inerte

#### Scenario: Sin aviso si está aprovisionado

- **WHEN** el usuario de servicio configurado es válido (`>0`)
- **THEN** la página no muestra el aviso de vía inerte
