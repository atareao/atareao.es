# Podcast Block Delta

## Purpose

Esta capability fija el contrato de salida del bloque Gutenberg `atareao/podcast`: la URL de audio que se emite en el atributo `src` del elemento `<audio>` se escapa con `esc_url()` en el punto de emisión, con independencia de que proceda de un atributo del bloque o del metadato `mp3-url` del podcast. Preserva el marcado de los bloques legítimos y el placeholder cuando no hay URL, como defensa en profundidad aunque el metadato ya se saneó al guardar.

## ADDED Requirements

### Requirement: Escape de la URL de audio en la salida del bloque

El bloque `atareao/podcast` SHALL escapar con `esc_url()` la URL de audio en el momento de emitirla en el atributo `src` del elemento `<audio>`, tanto si proviene del atributo del bloque (`audioUrl`) como si proviene del post meta `mp3-url` del podcast. El valor almacenado o recibido SHALL NOT imprimirse crudo en el atributo. Cuando no haya URL (ni atributo ni meta) el bloque SHALL mostrar el placeholder existente y SHALL NOT emitir un elemento `<audio>` con un `src` vacío o inválido. El marcado de un bloque con una URL legítima SHALL conservarse.

#### Scenario: URL procedente del meta escapada en la salida

- **WHEN** el bloque toma la URL del meta `mp3-url` de un podcast y la emite en el `src` del `<audio>`
- **THEN** el valor se escapa con `esc_url()` en el punto de salida y el HTML resultante es válido

#### Scenario: URL procedente del atributo escapada en la salida

- **WHEN** el bloque usa la URL del atributo `audioUrl`
- **THEN** el valor se escapa con `esc_url()` al emitirse en el `src`

#### Scenario: Sin URL no se emite audio

- **WHEN** el bloque no recibe `audioUrl` y el podcast no tiene el meta `mp3-url`
- **THEN** el sistema muestra el placeholder y no emite un `<audio>` con `src` vacío

#### Scenario: Bloque legítimo sin cambios

- **WHEN** un bloque ya publicado usa una URL de audio válida
- **THEN** el HTML resultante mantiene su estructura y el reproductor sigue funcionando
