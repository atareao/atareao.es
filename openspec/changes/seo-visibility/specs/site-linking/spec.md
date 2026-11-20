# site-linking — Delta

## Purpose

Los enlaces canónicos que el sitio publica hacia sus plataformas externas y hacia el vídeo de cada
episodio: el canal de YouTube correcto, el vídeo propio del episodio y unos enlaces de suscripción
que llevan al destino real en lugar de a un slug o a un canal obsoletos.

## ADDED Requirements

### Requirement: El canal de YouTube se enlaza por handle o identificador

El sistema SHALL enlazar al canal de YouTube del proyecto por su handle o por su identificador de
canal (`UCP8dTCaLNJFxfklb_GNqe6A`) y SHALL NOT publicar en ninguna página el enlace heredado
`youtube.com/c/atareao`.

#### Scenario: No queda el canal antiguo

- **WHEN** se busca `youtube.com/c/atareao` en el HTML servido por el sitio
- **THEN** no hay ninguna coincidencia

#### Scenario: El enlace apunta al canal real

- **WHEN** se inspecciona un enlace a YouTube servido por el sitio
- **THEN** apunta al handle o al identificador de canal del proyecto

### Requirement: Cada episodio enlaza su propio vídeo

Cuando una página de episodio embebe un vídeo, el sistema SHALL enlazar a **ese** vídeo concreto
(no solo al canal) mediante una URL canónica del vídeo, además del enlace al canal. Si el episodio
no embebe ningún vídeo, el sistema SHALL NOT emitir un enlace a un vídeo.

#### Scenario: El episodio enlaza su vídeo

- **WHEN** la página de un episodio que embebe un vídeo se sirve
- **THEN** contiene un enlace a la URL de ese vídeo concreto

#### Scenario: El episodio sin vídeo no inventa un enlace

- **WHEN** la página de un episodio no embebe ningún vídeo
- **THEN** no emite ningún enlace a un vídeo

### Requirement: Enlaces de suscripción corregidos

El sistema SHALL publicar enlaces de suscripción canónicos: Apple Podcasts SHALL apuntar a la URL
canónica del programa en Apple (no a una redirección no canónica), iVoox SHALL usar el identificador
real del programa (no el slug incoherente `atareao-version-podcast`) y el enlace de RSS SHALL
apuntar al feed propio del sitio, no a una etiqueta genérica «RSS».

#### Scenario: Apple apunta a la ficha canónica

- **WHEN** se inspecciona el enlace de Apple Podcasts
- **THEN** apunta a la URL canónica del programa en `podcasts.apple.com`

#### Scenario: iVoox usa el identificador real

- **WHEN** se inspecciona el enlace de iVoox
- **THEN** no contiene el slug `atareao-version-podcast` y apunta al programa real

#### Scenario: El RSS es el del sitio

- **WHEN** se inspecciona el enlace de RSS de suscripción
- **THEN** apunta al feed propio del sitio y su texto identifica ese feed
