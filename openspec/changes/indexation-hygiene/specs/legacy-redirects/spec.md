# legacy-redirects — Delta

## Purpose

El mapa de redirecciones `301` que resuelve las estructuras antiguas de URL del sitio
(`/tutorial/opencode/*`, `/instalar/*`, `/amp/`) hacia su destino vigente, de modo que un `404` con
equivalente conocido se sirva como redirección permanente en lugar de como error.

## ADDED Requirements

### Requirement: Las estructuras antiguas de URL redirigen con `301`

El sitio SHALL responder con HTTP `301` —y cabecera `Location` hacia el destino vigente— a las URLs
de las familias antiguas `/tutorial/opencode/*`, `/instalar/*` y `/amp/` que hoy devuelven `404`.
La redirección SHALL preservar el sufijo de ruta y la query string cuando el destino los admita, y
SHALL NOT provocar bucles: una petición cuyo destino coincida con la propia URL SHALL NOT
redirigirse a sí misma.

El mapa SHALL ser **mínimo**: una entrada por familia o por URL con equivalente conocido, y SHALL
residir en el plugin `atareao-functionality`, junto al resto de redirecciones del sitio, no en
cadenas de redirección dispersas por plantillas.

#### Scenario: Familia `/tutorial/opencode/*`

- **WHEN** se solicita una URL de `/tutorial/opencode/*` con equivalente vigente
- **THEN** la respuesta es `301` con `Location` hacia el equivalente

#### Scenario: Familia `/instalar/*`

- **WHEN** se solicita una URL de `/instalar/*` con equivalente vigente
- **THEN** la respuesta es `301` con `Location` hacia el equivalente

#### Scenario: Familia `/amp/`

- **WHEN** se solicita la versión `/amp/` de un post existente
- **THEN** la respuesta es `301` hacia la URL canónica del post

#### Scenario: Sin bucle de redirección

- **WHEN** el destino calculado coincide con la URL solicitada
- **THEN** no se emite redirección y no se produce un bucle

### Requirement: Un `404` con equivalente conocido se sirve como `301`

Cuando una petición que hoy resuelve en `404` corresponde a una URL de una familia antigua con
equivalente conocido, el sitio SHALL servir `301` hacia ese equivalente en lugar de `404`. La
comprobación SHALL ejecutarse antes de que WordPress emita el `404` de la plantilla, de modo que esa
respuesta nunca llegue a ser `404`. Una URL sin equivalente conocido SHALL seguir respondiendo `404`.

#### Scenario: Hueco de redirección cubierto

- **WHEN** se solicita una URL que hoy devuelve `404` y pertenece a una familia con equivalente
- **THEN** la respuesta es `301`, no `404`

#### Scenario: Un `404` sin equivalente sigue siendo `404`

- **WHEN** se solicita una URL que no pertenece a ninguna familia con equivalente (p. ej. basura externa)
- **THEN** la respuesta sigue siendo `404`
