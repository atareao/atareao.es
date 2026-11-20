# podcast-feed — Delta

## Purpose

El valor de `itunes:explicit` que el feed del podcast publica, conforme a lo que exige Apple, de modo
que el feed completo pase el validador de feeds de W3C.

## MODIFIED Requirements

### Requirement: Namespace y metadatos del feed del podcast

El sistema SHALL servir el feed del podcast en `GET /podcast/feed/` con `Content-Type:
application/rss+xml; charset=UTF-8` y un cuerpo XML **bien formado**. El elemento raíz `<rss>` SHALL
declarar el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace
`itunes` con el valor `http://www.itunes.com/dtds/podcast-1.0.dtd`.

El `<channel>` SHALL declarar `<title>`, `<link>`, `<description>`, `<language>`, `atom:link` con
`rel="self"` y `type="application/rss+xml"`, `itunes:author`, `itunes:explicit` con un valor booleano
conforme a Apple (**`true`** o **`false`**), `itunes:type` (con `serial` como valor por defecto y
configurable), al menos un `itunes:category`, `itunes:owner` con nombre y correo, `itunes:image` con
la URL **absoluta** de la portada del programa, `podcast:locked` con valor `yes` y el correo del
propietario, y un **`podcast:guid`** estable.

El valor de `itunes:explicit` SHALL derivarse del ajuste `atareao_podcast_explicit`, cuyo valor por
defecto SHALL ser `false`. La emisión SHALL normalizar los valores heredados para no romper ajustes ya
guardados: `yes` SHALL emitirse como `true`; `no` y `clean` SHALL emitirse como `false`; cualquier
otro valor SHALL emitirse como `false`. El elemento SHALL NOT contener nunca `no`, `yes`, `clean` ni
una cadena vacía.

El `podcast:guid` SHALL derivarse de forma **determinista** de la identidad del programa y SHALL NOT
cambiar entre reconstrucciones del feed ni entre versiones del plugin: un directorio que ya registró
el programa dejaría de reconocerlo si su guid cambiara. Los valores configurables SHALL proceder de
ajustes del sitio con un valor por defecto razonable.

#### Scenario: El feed declara los namespaces de podcasting

- **WHEN** se parsea el elemento raíz del feed del podcast
- **THEN** declara el namespace `podcast` con el valor `https://podcastindex.org/namespace/1.0` y el namespace `itunes`

#### Scenario: El canal declara los metadatos que exigen los directorios

- **WHEN** se inspecciona el `<channel>`
- **THEN** declara título, descripción, idioma, autor, tipo, al menos una categoría, propietario con nombre y correo, la portada y el enlace propio del feed

#### Scenario: La portada es una URL absoluta

- **WHEN** se inspecciona `itunes:image`
- **THEN** su URL es absoluta y usa `https`

#### Scenario: El feed está bloqueado con propietario

- **WHEN** se inspecciona `podcast:locked`
- **THEN** su valor es `yes` y declara el correo del propietario

#### Scenario: El guid del programa es estable

- **WHEN** se compone el feed dos veces con la misma identidad de programa
- **THEN** el valor de `podcast:guid` es idéntico en ambas composiciones

#### Scenario: El feed es XML bien formado

- **WHEN** se valida el cuerpo del feed como XML
- **THEN** es bien formado y sus elementos con prefijo usan namespaces declarados

#### Scenario: El media type declara el conjunto de caracteres una sola vez

- **WHEN** se inspecciona la cabecera `Content-Type` con la que se sirve el feed
- **THEN** declara `application/rss+xml` y **una sola** aparición de `charset`, porque el núcleo ya añade el suyo

#### Scenario: El valor por defecto de itunes:explicit es válido para Apple

- **WHEN** el ajuste `atareao_podcast_explicit` no está definido y se inspecciona `itunes:explicit`
- **THEN** su valor es `false`

#### Scenario: Los valores heredados se normalizan

- **WHEN** el ajuste vale `yes`, `no`, `clean` o cualquier otro valor
- **THEN** `itunes:explicit` se emite como `true` para `yes` y como `false` para el resto

#### Scenario: El feed completo pasa el validador

- **WHEN** se valida el feed del podcast con el W3C Feed Validator
- **THEN** no aparece el error «itunes:explicit must be "true" or "false"»

#### Scenario: El feed principal no lleva el tag

- **WHEN** se inspecciona el feed principal `/feed/`
- **THEN** no contiene ningún elemento `itunes:explicit`
