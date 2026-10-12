# image-handling Delta

## ADDED Requirements

### Requirement: Media size options are written once, on theme activation

Las opciones de tamaño de imagen de WordPress (`thumbnail_size_w`, `thumbnail_size_h`, `thumbnail_crop`, `medium_size_w`, `medium_size_h`, `medium_crop`, `medium_large_size_w`, `medium_large_size_h`, `large_size_w`, `large_size_h` y `large_crop`) SHALL fijarse **una sola vez**, al activarse el tema, y NO SHALL reescribirse en cada petición. El tema NO SHALL revertir los cambios que un administrador haga en Ajustes → Medios.

Los valores fijados SHALL ser los mismos que el tema imponía hasta ahora, de modo que el estado efectivo no cambie.

#### Scenario: Activación del tema

- **WHEN** se activa el tema
- **THEN** las once opciones de tamaño quedan fijadas en los valores del tema

#### Scenario: Petición normal

- **WHEN** se sirve cualquier petición con el tema ya activo
- **THEN** no se escribe ninguna opción de tamaño de imagen

#### Scenario: Cambio desde Ajustes → Medios

- **WHEN** un administrador cambia un tamaño de imagen en Ajustes → Medios
- **THEN** el cambio persiste y el tema no lo revierte en la siguiente petición

#### Scenario: Sin pérdida de tamaños

- **WHEN** el tema se activa en una instalación existente que ya tenía esos valores
- **THEN** los valores siguen siendo los mismos y no se regeneran miniaturas ni cambia ningún `sizes` del sitio
