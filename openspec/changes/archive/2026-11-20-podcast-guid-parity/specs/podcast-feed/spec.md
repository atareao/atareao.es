# podcast-feed Delta

> Depende de `podcast-feed-soberania` (todavía no archivado): de él heredan el `<guid isPermaLink="false">`
> mapeado desde Anchor y el metadato `anchor-guid`. Este delta **no cambia** ese mapeo; cambia **cómo se
> emite** el valor ya resuelto. Se declara como ADDED porque el requisito de emisión del guid del episodio
> aún no existe en `openspec/specs/podcast-feed/spec.md`.

## ADDED Requirements

### Requirement: Emisión literal del guid del episodio

Dentro del feed del podcast, el `<guid>` de cada `<item>` SHALL ser el **valor persistido** del episodio
(el metadato `anchor-guid`, o el guid estable propio cuando no haya par) **verbatim** y escapado
únicamente para XML: un UUID desnudo SHALL emitirse como UUID desnudo —p. ej.
`773cd246-f771-4edb-9760-33dd3c775836`— y SHALL NOT convertirse en una URL ni recibir un esquema
antepuesto. El sistema SHALL NOT aplicar al guid el escape propio de las URL: el núcleo registra
`add_filter( 'the_guid', 'esc_url' )`, y `esc_url()` antepone `http://` a cualquier cadena sin esquema,
de modo que el guid resuelto SHALL volver a fijarse **después** de ese escape. Fuera del feed del
podcast, el valor de entrada del filtro SHALL conservarse intacto.

El contrato de integración SHALL ser:

- Hook: `add_filter( 'the_guid', <callback>, <prioridad>, 2 )` con `<prioridad>` **estrictamente mayor
  que 10** (posterior a `esc_url`), de forma que el callback se ejecute **después** del escape del
  núcleo y pueda deshacer su efecto.
- Callback: `callback( string $guid, int $postId ): string` — recibe el guid ya escapado por el núcleo
  y el identificador del post; devuelve `string`.
- Condición de aplicación: el callback SHALL actuar **solo** cuando la petición sea el feed del podcast
  (`is_feed() && is_post_type_archive('podcast')`); en cualquier otro contexto SHALL devolver `$guid`
  sin modificarlo.
- Escape de salida: `esc_html` (escape para XML), **nunca** `esc_url`.

Un guid que ya sea una URL (`https://anchor.fm/…`) SHALL emitirse igualmente **verbatim**, sin
re-escaparse ni normalizarse, para no alterar los guids que hoy ya coinciden con Anchor.

#### Scenario: El guid desnudo se emite sin esquema

- **WHEN** un episodio del feed tiene el metadato `anchor-guid` con el UUID desnudo `773cd246-f771-4edb-9760-33dd3c775836`
- **THEN** el `<guid>` del item vale exactamente `773cd246-f771-4edb-9760-33dd3c775836`, sin `http://` ni `https://` antepuesto

#### Scenario: El guid ya-URL se emite verbatim

- **WHEN** un episodio del feed tiene el metadato `anchor-guid` con una URL `https://anchor.fm/…`
- **THEN** el `<guid>` del item es esa URL exacta, sin re-escaparse ni normalizarse

#### Scenario: El guid emitido coincide con el de Anchor

- **WHEN** se compara el `<guid>` de un item del feed de WordPress con el `<guid>` de su item homólogo en Anchor
- **THEN** ambos valores son idénticos carácter a carácter, con o sin esquema en el origen

#### Scenario: La corrección es posterior al escape del núcleo

- **WHEN** el núcleo aplica `esc_url` al guid y lo convierte en `http://<uuid>`
- **THEN** el filtro del feed vuelve a fijar el guid persistido, de modo que el `<guid>` final no lleva el prefijo `http://`

#### Scenario: Fuera del feed del podcast no hay efecto

- **WHEN** se solicita el feed principal `/feed/` o cualquier otra superficie que pase por `the_guid`
- **THEN** el valor de entrada del filtro se devuelve sin modificar y ningún guid ajeno al podcast cambia

#### Scenario: El guid sigue siendo un identificador estable

- **WHEN** se compone el feed del podcast dos veces con el mismo metadato `anchor-guid`
- **THEN** el `<guid>` de cada episodio es idéntico en ambas composiciones
