# Spec Delta

## MODIFIED Requirements

### Requirement: Panel de configuración y migración

El sistema SHALL exponer la configuración de la analítica en la pestaña «Umami» del hub de ajustes «Atareao» (`options-general.php?page=atareao-settings&tab=umami`), accesible solo a usuarios con capacidad `manage_options`, que permita configurar el script, las exclusiones, el tracking de comentarios y la integridad. El guardado SHALL procesarse por POST, verificarse con un nonce y volver a comprobar `manage_options`, saneando cada campo según su tipo: URLs con `esc_url_raw`, identificadores y banderas con saneado equivalente a `sanitize_text_field` y normalización a `0`/`1`. Los valores no escalares SHALL normalizarse a `''` o `0` sin emitir avisos de conversión. La pestaña SHALL ofrecer una acción de importación que lea la configuración del plugin legado —o, si este ya la borró, la copia propia que el sistema mantiene—, vuelque sus valores en los ajustes nuevos, no borre la configuración legada e informe del número de ajustes importados o de que no encontró nada.

#### Scenario: Guardado con saneado de los ajustes

- **WHEN** un administrador guarda el formulario con una URL y valores de banderas válidos o inválidos
- **THEN** los ajustes se almacenan saneados por tipo y el guardado se rechaza si el nonce no es válido

#### Scenario: Usuario sin permisos

- **WHEN** un usuario sin capacidad `manage_options` intenta acceder a la página o enviar el formulario de guardado o de importación
- **THEN** no puede ver la página ni modificar los ajustes

#### Scenario: Importación con configuración legada presente

- **WHEN** existe la configuración del plugin legado y un administrador pulsa importar
- **THEN** los valores se vuelcan en los ajustes nuevos, el ajuste legado permanece intacto y se informa del número de ajustes importados

#### Scenario: Importación sin configuración legada

- **WHEN** no existe la configuración del plugin legado y un administrador pulsa importar
- **THEN** no se modifica ningún ajuste y se informa de que no se encontró nada que importar

#### Scenario: Importación después de desactivar el plugin legado

- **WHEN** el plugin legado ya no está cargado y su opción fue borrada, pero existe la copia propia `atareao_umami_legacy_snapshot`
- **THEN** la importación recupera los ajustes desde la copia, informa del número importado y el aviso de apagón desaparece

#### Scenario: Aviso de analítica desactivada con configuración legada presente

- **WHEN** la analítica propia está deshabilitada, el plugin legado ya no está cargado (`class_exists('\Ancozockt\Umami\Manager')` es falso) y existe la configuración legada `integrate_umami_options`
- **THEN** el panel de ajustes muestra un aviso indicando que la analítica está desactivada y que importe o active los ajustes, de modo que no se pierda el registro de visitas en silencio
