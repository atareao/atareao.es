# podcast-transcript-page Specification

## Purpose
La transcripción del episodio como **texto indexable en su página**: además del `<podcast:transcript>`
que ya declara el feed, el sitio SHALL renderizar el texto hablado en HTML para que un buscador pueda
indexarlo. Reutiliza el contrato existente —un adjunto `.srt` y el metadato `transcript-srt`— y no
introduce un productor de transcripciones nuevo.

## Requirements

### Requirement: Transcripción visible e indexable en la página del episodio

La página de un episodio con transcripción SHALL mostrar el **texto** de la transcripción como HTML
**indexable** —no solo un enlace al fichero `.srt`—, de modo que un buscador pueda leer el texto
hablado. El texto SHALL proceder del **mismo adjunto SRT** que el feed declara (el referenciado por el
metadato `transcript-srt`), que sigue siendo la única fuente de verdad. Cuando el episodio no tenga una
transcripción accesible, la página SHALL NOT mostrar la sección. La página SHALL NOT ejecutar descarga
de audio ni reconocimiento de voz: solo lee el adjunto ya existente.

La transcripción SHALL presentarse **plegada** por defecto dentro de un elemento desplegable nativo
(`<details>` con su `<summary>`), manteniendo el texto en el HTML servido (indexable), y NO SHALL
ocupar toda la ficha. El desplegable SHALL ser operable por teclado y accesible.

#### Scenario: La página muestra el texto de la transcripción

- **WHEN** un episodio publicado tiene una transcripción accesible y se visita su página
- **THEN** la página muestra el texto de la transcripción

#### Scenario: El texto es indexable

- **WHEN** se inspecciona el HTML servido de la página del episodio
- **THEN** el texto de la transcripción está presente en el HTML (no solo dentro de un fichero `.srt` enlazado)

#### Scenario: La transcripción se presenta plegada

- **WHEN** se visita la página de un episodio con transcripción
- **THEN** el texto se presenta dentro de un `<details>` plegado por defecto, con un `<summary>` visible

#### Scenario: Sin transcripción no hay sección

- **WHEN** un episodio no tiene transcripción accesible
- **THEN** su página no muestra la sección de transcripción

#### Scenario: El texto procede del SRT que declara el feed

- **WHEN** el feed declara `<podcast:transcript>` para un episodio
- **THEN** el texto mostrado en su página procede de ese mismo adjunto SRT

#### Scenario: La página no transcribe en tiempo de ejecución

- **WHEN** se sirve la página de un episodio
- **THEN** no se descarga audio ni se ejecuta reconocimiento de voz en la petición
