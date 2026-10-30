# Tasks: podcast-feed-namespace

## 0. Preparación

- [ ] 0.1 Confirmar cómo se sirve hoy `/podcast/feed/` (feed del archivo del CPT `podcast`) y qué
      puntos de extensión ofrece el núcleo para declarar namespaces, canal y episodios
- [ ] 0.2 Inventariar los metadatos del episodio (`mp3-url`, `number`, `season`) y decidir el metadato
      de la transcripción, con su única fuente de verdad
- [ ] 0.3 Confirmar en el arnés (`tests/wp-stubs.php`) los stubs necesarios para componer y parsear el
      feed (`home_url`, `get_option`, `get_post_meta`, `esc_url`, `esc_html`, serialización XML)
- [ ] 0.4 Decidir y documentar el valor de `podcast:guid` (derivación determinista, estable para siempre)
      y los ajustes de portada, autor, categoría y propietario con sus valores por defecto
- [ ] 0.5 **Decisión cerrada:** el SRT admite **las dos vías** (elegir/subir el adjunto desde la ficha
      del episodio, y automatizarlo desde el pipeline) sobre un **único contrato**: adjunto + metadato
      del episodio. Confirmar que el feed lee **solo** el metadato, no una ruta ni una convención

## 1. RED — los tests primero, y que fallan

- [ ] 1.1 El feed del podcast se sirve como `application/rss+xml` y su cuerpo es XML bien formado
- [ ] 1.2 El elemento raíz declara los namespaces `podcast` e `itunes`
- [ ] 1.3 El canal declara los metadatos que exigen los directorios (título, descripción, idioma, autor,
      tipo, categoría, propietario, portada absoluta, enlace propio)
- [ ] 1.4 `podcast:locked` vale `yes` y lleva el correo del propietario
- [ ] 1.5 `podcast:guid` es idéntico en dos composiciones del mismo programa
- [ ] 1.6 Un episodio publicado con `mp3-url` declara un único `<enclosure>` con URL, tipo y tamaño
- [ ] 1.7 Sin tamaño determinable → `length` `0` y el episodio sigue suscribible
- [ ] 1.8 Un episodio con `number` y `season` declara la numeración (`itunes:` y `podcast:`)
- [ ] 1.9 Un podcast sin `mp3-url` no declara `<enclosure>` ni elementos de episodio, y el XML sigue válido
- [ ] 1.10 Un episodio con SRT declara `<podcast:transcript>` con URL absoluta, tipo `application/x-subrip` e idioma
- [ ] 1.11 Sin transcripción no se declara `<podcast:transcript>`; una transcripción a un fichero
      inexistente tampoco se declara
- [ ] 1.12 El feed excluye borradores, privados y protegidos por contraseña; no expone datos internos
- [ ] 1.13 El feed principal `/feed/` no incorpora vocabulario de podcasting
- [ ] 1.14 `nginx/pro/nginx.conf`: la ruta del feed está exenta del bloqueo por User-Agent (línea anclada, no comentada)
- [ ] 1.15 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

*(pendiente de la fase de implementación)*

## 2. GREEN — implementación mínima

- [ ] 2.1 Namespaces del feed y metadatos del canal, con sus ajustes y valores por defecto
- [ ] 2.2 `podcast:guid` con derivación determinista y estable
- [ ] 2.3 `<enclosure>` y numeración del episodio desde `mp3-url`, `number` y `season`
- [ ] 2.4 Metadato de transcripción + `<podcast:transcript>` cuando el SRT existe y es accesible
- [ ] 2.5 Caja del SRT en la ficha del podcast (`class-metaboxes.php`): elegir un adjunto existente o
      subir uno nuevo, con saneado en el guardado (vía **manual**)
- [ ] 2.6 `nginx/pro/nginx.conf`: exención de la ruta del feed del podcast
- [ ] 2.7 Vía **automatizada**: receta del pipeline que sube el SRT como adjunto y escribe el metadato
      del episodio, sin duplicar la lógica de asociación de la caja
- [ ] 2.8 Documentar en el `README.md` del plugin las **dos vías** de carga del SRT y el contrato del feed
- [ ] 2.9 `just test` → **100% verde**; `just php-lint` limpio

## 3. REFACTOR

- [ ] 3.1 Una sola fuente de verdad por dato (URL del feed, guid, portada, idioma, metadato del SRT)
- [ ] 3.2 Los elementos de podcast se emiten en un único punto; el feed principal queda intacto
- [ ] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

## 4. Verificación

- [ ] 4.1 `openspec validate podcast-feed-namespace --strict` → valid
- [ ] 4.2 `just check-spec` → en verde
- [ ] 4.3 Auditoría de seguridad: escapado de todas las URLs y de los metadatos en el XML (sin inyección
      ni entidades sin escapar), sin exponer contenido no público ni datos internos
- [ ] 4.4 Validación del feed contra un validador de podcast (XML bien formado y elementos exigidos)
- [ ] 4.5 Verificación E2E en producción tras el despliegue: el feed responde, declara los namespaces,
      los episodios llevan su `<enclosure>` y la transcripción, y es alcanzable **con User-Agent `curl`**

## 5. Entrega

- [ ] 5.1 PR `feature/podcast-feed-namespace → development` con **merge commit** (convención del proyecto)
- [ ] 5.2 Archivar el change fusionando el delta en `openspec/specs/podcast-feed/spec.md`

## Notas de implementación

- **El feed principal no es terreno de este change.** Toda la emisión de vocabulario de podcasting se
  acota al feed del archivo de `podcast`; un escenario lo fija para que nadie lo «unifique» después.
- **`podcast:guid` no se recalcula.** Es la dirección permanente del programa ante los directorios;
  cambiarlo es un cambio de identidad, no una mejora.
- **`length` `0` es la rendición honesta.** Se emite `0` solo cuando el tamaño no se puede determinar;
  inventarse un valor es peor que declarar que no se conoce.
