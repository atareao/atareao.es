# legacy-redirects — Delta

## Purpose

El mapa de redirecciones `301` que resuelve las estructuras antiguas de URL del sitio que hoy
devuelven `404` (`/tutorial/opencode/*`, `/docker/`, `/pyldoras`, `/pyldora/*`, `/pildoras`,
`/podcat/*`, `/telegram`, `/software/social/4441/` y los `/tutorial/<capítulo>` movidos a su serie)
hacia su destino vigente, de modo que un `404` con equivalente conocido se sirva como redirección
permanente en lugar de como error. El mapa es deliberadamente **mínimo**: solo cubre los equivalentes
**confirmados**; el resto de `404` (externalidad, basura, estructuras sin equivalente) sigue siendo
`404`.

## ADDED Requirements

### Requirement: Las estructuras antiguas de URL redirigen con `301`

El sitio SHALL responder con HTTP `301` —y cabecera `Location` hacia el destino vigente— a las URLs
de las estructuras antiguas que hoy devuelven `404` y para las que el cruce con el sitemap vigente ha
confirmado un equivalente (`/tutorial/opencode/*`, `/docker/`, `/pyldoras`, `/pyldora/*`,
`/pildoras`, `/podcat/*`, `/telegram`, `/software/social/4441/` y los `/tutorial/<capítulo>` movidos
a su serie). Para cada URL o familia con equivalente conocido, la redirección SHALL apuntar a un
destino absoluto bajo `https://atareao.es`, con normalización de la barra final, y SHALL NOT
provocar bucles: un destino que coincida con una entrada del mapa (o con la propia URL solicitada)
SHALL NOT redirigirse a sí mismo.

El mapa SHALL ser **mínimo**: una entrada por familia o por URL con equivalente, y SHALL residir en
el plugin `atareao-functionality`, junto al resto de redirecciones del sitio, no en cadenas de
redirección dispersas por plantillas. Una URL **sin** equivalente conocido SHALL seguir respondiendo
`404`.

#### Scenario: Hub `/tutorial/opencode/`

- **WHEN** se solicita `/tutorial/opencode/` o `/tutorial/opencode` (el hub de la serie)
- **THEN** la respuesta es `301` con `Location` hacia `/tutorial/tutorial-de-opencode/`

#### Scenario: Familia de prefijo `/pyldora/*`

- **WHEN** se solicita cualquier subruta de `/pyldora/*` (p. ej. `/pyldora/14`)
- **THEN** la respuesta es `301` con `Location` hacia `/tutorial/pyldoras/`, descartando el sufijo
  (`/pyldora/14` → `/tutorial/pyldoras/`)

#### Scenario: Familia `/pildoras`

- **WHEN** se solicita `/pildoras` o cualquier subruta `/pildoras/*`
- **THEN** la respuesta es `301` con `Location` hacia `/tutorial/pyldoras/`

#### Scenario: Familia `/podcat/*`

- **WHEN** se solicita cualquier subruta de `/podcat/*` (p. ej. `/podcat/468`)
- **THEN** la respuesta es `301` con `Location` hacia `/podcast/`

#### Scenario: Equivalentes 1:1 representativos

- **WHEN** se solicita `/docker/`, `/pyldoras` (o `/pyldoras/`), `/telegram`,
  `/software/social/4441/`, o un `/tutorial/<capítulo>` movido a su serie (p. ej.
  `/tutorial/como-instalar-podman` → `/tutorial/podman/como-instalar-podman/`)
- **THEN** la respuesta es `301` con `Location` hacia su destino vigente y, cuando aplica, la
  variante con barra final resuelve al mismo destino

#### Scenario: Sin bucle de redirección

- **WHEN** el destino calculado coincide con una entrada del mapa (o con la URL solicitada)
- **THEN** no se emite redirección y no se produce un bucle

### Requirement: Un `404` con equivalente conocido se sirve como `301`

Cuando una petición que hoy resuelve en `404` corresponde a una URL de las estructuras antiguas con
equivalente conocido, el sitio SHALL servir `301` hacia ese equivalente en lugar de `404`. La
comprobación SHALL ejecutarse con `is_404()` en `template_redirect` —antes de que WordPress emita el
`404` de la plantilla— de modo que esa respuesta nunca llegue a ser `404`. Una URL sin equivalente
conocido SHALL seguir respondiendo `404`.

#### Scenario: Hueco de redirección cubierto

- **WHEN** se solicita una URL que hoy devuelve `404` y pertenece a una estructura con equivalente
- **THEN** la respuesta es `301`, no `404`

#### Scenario: `/instalar/*` sin equivalente permanece `404`

- **WHEN** se solicita una URL de `/instalar/*` (p. ej. `/instalar/vmm`) o de
  `/instalar-de-repositorio/*`
- **THEN** el mapa no devuelve destino y la respuesta sigue siendo `404`

#### Scenario: Un `404` sin equivalente sigue siendo `404`

- **WHEN** se solicita una URL que no pertenece a ninguna estructura con equivalente (p. ej.
  `/tag/foo`, barras residuales, basura externa o una URL truncada)
- **THEN** el mapa no devuelve destino y la respuesta sigue siendo `404`

#### Scenario: `/amp/` lo sirve el núcleo, no este mapa

- **WHEN** se solicita la versión `/amp/<slug-de-post-existente>/`
- **THEN** `/amp/` **ya** se sirve como `301` mediante el *redirect guess* del núcleo de WordPress
  (cabecera `x-redirect-by: WordPress`), y el mapa de este change **no** añade una regla propia para
  `/amp/`; una ruta `/amp/` **sin** post existente no tiene equivalente y sigue siendo `404`
