# structured-data Delta

> `openspec/specs/structured-data/spec.md` no declara hoy nada sobre `sameAs` en `Person`: la
> publicación de perfiles externos quedó fuera de la spec fusionada (el change
> `2026-11-20-seo-visibility` se archivó con `--skip-specs`). Por eso este requisito se declara como
> **ADDED** y no reescribe el requisito de artículo.

## ADDED Requirements

### Requirement: Perfiles externos en la entidad `Person` del grafo

El sistema SHALL publicar los perfiles externos del sitio (`sameAs`) en **toda** entidad `Person` que
el grafo describa —con independencia de que sea de primer nivel o esté anidada, por ejemplo como
`publisher` del `WebSite` o como `author` de una página—, **sin** alterar su `@type` ni su `@id` y sin
duplicar perfiles ya presentes. Las entidades `Person` que solo declaren `@id` (referencias puras, sin
propiedades propias) SHALL permanecer **intactas**. Los perfiles SHALL leerse de las opciones
`atareao_social_*` y el enlace de YouTube SHALL normalizarse al canal canónico del proyecto, igual que
en la entidad `Organization`. El número de entidades del grafo y el número de bloques
`<script type="application/ld+json">` de la página SHALL NOT cambiar por este comportamiento.

#### Scenario: La página de autor declara los perfiles del autor

- **WHEN** se inspecciona la entidad `Person` del grafo de `/author/atareao/`
- **THEN** declara `sameAs` con los perfiles externos del sitio (GitHub, YouTube y plataformas de podcast)

#### Scenario: Una `Person` anidada se enriquece

- **WHEN** el grafo declara una `Person` anidada (por ejemplo el `publisher` del `WebSite`) con `name` o `url` y sin `sameAs`
- **THEN** esa entidad pasa a declarar `sameAs` con los perfiles del sitio

#### Scenario: Una referencia pura no se modifica

- **WHEN** el grafo declara una `Person` que solo tiene `@id` (sin `name` ni `url`)
- **THEN** esa entidad permanece exactamente igual, sin `sameAs`

#### Scenario: No se altera el tipo ni el identificador

- **WHEN** se comparan las entidades del grafo antes y después del enriquecimiento
- **THEN** conservan el mismo `@type` y el mismo `@id`, y el grafo sigue siendo una lista con el mismo número de entidades

#### Scenario: Los perfiles existentes no se duplican

- **WHEN** una `Person` ya declara `sameAs` con uno de los perfiles del sitio
- **THEN** ese perfil aparece una sola vez en el resultado

#### Scenario: Sin perfiles configurados no se añade nada

- **WHEN** ninguna opción `atareao_social_*` está configurada
- **THEN** ninguna entidad `Person` recibe `sameAs` y el grafo queda igual
