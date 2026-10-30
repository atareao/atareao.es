# Tasks: article-structured-data

## 0. Preparación

- [ ] 0.1 Confirmar el punto de extensión del grafo del plugin de SEO
      (`the_seo_framework_schema_graph_data`) y decidir si el markup vive en `class-seo.php` o en un
      módulo propio con el mismo contrato
- [ ] 0.2 Capturar el grafo **real** de producción (portada y una entrada) como referencia de partida:
      hoy contiene `WebSite`/`WebPage` (`Person` en la portada) y **ninguna** entidad de artículo
- [ ] 0.3 Determinar los `@id` que el framework usa para `WebPage`, `Person` y la entidad editorial, para
      poder referenciarlos sin duplicarlos
- [ ] 0.4 Confirmar en el arnés (`tests/wp-stubs.php`) los stubs necesarios para ejercitar el callback
      con un grafo de entrada y un contenido de prueba, **sin** el framework instalado
- [ ] 0.5 Fijar la lista de tipos de contenido editorial cubiertos y confirmar que `page` queda fuera

## 1. RED — los tests primero, y que fallan

- [ ] 1.1 El grafo de un contenido editorial singular incluye una entidad `Article`/`BlogPosting`
- [ ] 1.2 La salida sigue siendo **un único** bloque `ld+json` (no se añade un segundo script)
- [ ] 1.3 `headline` y las fechas (`datePublished`/`dateModified`) se declaran en ISO 8601
- [ ] 1.4 `mainEntityOfPage.@id` es idéntico al `@id` de la entidad `WebPage` del grafo
- [ ] 1.5 El autor y la entidad editorial se referencian por `@id` y no se duplican
- [ ] 1.6 La imagen destacada se describe como `ImageObject` con URL absoluta
- [ ] 1.7 Los datos ausentes (autor, imagen, fecha de modificación) **se omiten** y no se inventan
- [ ] 1.8 No se emite en archivos, portada, búsqueda ni error; ni para contenido no público
- [ ] 1.9 Los tipos y `@id` de las entidades preexistentes no cambian
- [ ] 1.10 El grafo resultante es JSON-LD válido
- [ ] 1.11 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

*(pendiente de la fase de implementación)*

## 2. GREEN — implementación mínima

- [ ] 2.1 Registrar el filtro del grafo desde el módulo que decida la tarea 0.1
- [ ] 2.2 Componer la entidad de artículo con `headline`, fechas, `mainEntityOfPage`, `author`,
      `publisher` e `image`, referenciando por `@id` lo que el grafo ya declara
- [ ] 2.3 Omitir las propiedades sin valor (sin defaults inventados)
- [ ] 2.4 Restringir la emisión a contenido singular publicado y público de los tipos cubiertos
- [ ] 2.5 `just test` → **100% verde**; `just php-lint` limpio

## 3. REFACTOR

- [ ] 3.1 Una sola función de composición; sin ramas duplicadas por tipo de contenido
- [ ] 3.2 Sin entidades duplicadas: todo lo que ya está en el grafo se referencia por `@id`
- [ ] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

## 4. Verificación

- [ ] 4.1 `openspec validate article-structured-data --strict` → valid
- [ ] 4.2 `just check-spec` → en verde
- [ ] 4.3 Auditoría: escapado de los valores que llegan del contenido (títulos, extractos, URLs) para
      que no puedan romper el JSON ni inyectar marcado
- [ ] 4.4 Comparar el grafo producido con el vocabulario schema.org y con las propiedades exigidas para
      el resultado enriquecido de artículo
- [ ] 4.5 Verificación E2E en producción tras el despliegue: `Article`/`BlogPosting` presente en una
      entrada publicada, un único bloque `ld+json`, y `mainEntityOfPage` enlazado con la `WebPage`

## 5. Entrega

- [ ] 5.1 PR `feature/article-structured-data → development` con **merge commit** (convención del proyecto)
- [ ] 5.2 Archivar el change fusionando el delta en `openspec/specs/structured-data/spec.md`

## Notas de implementación

- **Un solo grafo.** El requisito «un único bloque» es el que impide el atajo de emitir un segundo
  `<script>` a mano; cualquier implementación que lo haga incumple el change.
- **Referenciar, no duplicar.** Si el framework ya declara la persona autora o la entidad editorial, se
  referencian por `@id`. Duplicarlas crearía dos descripciones del mismo nodo y una posible
  contradicción.
- **Omitir es correcto.** Una propiedad ausente es mejor que una falsa: no se rellenan fechas o autores
  por conveniencia.
- **El podcast queda fuera.** `PodcastEpisode` y `PodcastSeries` necesitan su propio change; su
  vocabulario y sus dependencias no son los del artículo.
