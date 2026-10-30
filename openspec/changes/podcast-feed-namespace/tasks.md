# Tasks: podcast-feed-namespace

## 0. Preparación

- [x] 0.1 Confirmado el punto de servido y los puntos de extensión
- [x] 0.2 Inventariados los metadatos del episodio y decidido el de la transcripción
- [x] 0.3 Confirmados los dobles del arnés y los que faltan
- [x] 0.4 Decididos y documentados el `podcast:guid` y los valores por defecto de los ajustes
- [x] 0.5 Confirmado el contrato único del SRT y las dos vías de carga

### Evidencia — preparación

**0.1 — Cómo se sirve hoy y dónde se engancha.** `/podcast/feed/` es el **feed del archivo del CPT
`podcast`**: `register_post_type('podcast', …)` en `class-post-types.php` lo declara con
`'has_archive' => true` y `'rewrite' => array('slug' => 'podcast')`, de modo que el núcleo sirve
`get_post_type_archive_feed_link('podcast')` = `/podcast/feed/`. El pie del tema
(`wp-content/themes/atareao-theme/footer.php:95-110`) construye esa misma URL: usa la opción
`atareao_podcast_feed` si está puesta y, si no, `trailingslashit(get_post_type_archive_link('podcast')) . 'feed/'`.
**No existe ninguna customización de feed en el repositorio** (ni en el plugin ni en el tema): el feed
de podcast es *greenfield*.

Puntos de extensión del núcleo que se usarán:
| Punto | Para qué |
|---|---|
| `feed_content_type` | `application/rss+xml; charset=UTF-8` |
| `rss2_ns` | declarar `xmlns:podcast`/`xmlns:itunes` en `<rss>` |
| `rss2_head` | elementos de podcasting del `<channel>` |
| `rss2_item` | elementos del `<item>` (`<enclosure>`, numeración, transcripción) |
| `get_bloginfo_rss` | el **idioma del programa** (ajuste `atareao_podcast_language`) |
| `pre_get_posts` | excluir del feed el contenido no público (contraseña) |

Estos hooks son **globales a cualquier feed RSS2**, así que el módulo **debe** acotarse al feed del
archivo de `podcast`: es la condición de la capability `main-feed` (spec existente, un requisito:
«Main feed composition»), que este change SHALL NOT alterar. La salvaguarda es una guarda
`isPodcastFeed()` en todas las emisiones.

**Hallazgo del núcleo — los elementos de canal son del núcleo, no nuestros** (verificado en
`wp-includes/feed-rss2.php` de la **WP 7.1.3** que corre el stack): el núcleo emite, **antes** de
`rss2_head`, el `<title>` (`wp_title_rss()`), el `<atom:link rel="self">` (`self_link()`), el
`<link>` (`bloginfo_rss('url')`), la `<description>` (`bloginfo_rss('description')`) y el
`<language>` (`bloginfo_rss('language')` → `apply_filters('get_bloginfo_rss', …)`). El módulo, por
tanto, **solo añade** los elementos de podcasting, y jamás reemite esos cinco: un duplicado invalida el
feed. Consecuencia para el idioma: como el programa **sí** lo configura un ajuste, no se emite desde
`rss2_head` sino que se filtra en `get_bloginfo_rss` (clave `language`), de modo que el `<language>`
del núcleo lleva el valor del programa y sigue habiendo **un único** `<language>`.

Y una consecuencia más: el `rss_enclosure()` del núcleo (línea 117 de la plantilla) lee el metadato
`enclosure`, que este sitio no usa —el audio vive en `mp3-url`—, así que el `<enclosure>` del episodio
es el nuestro y **solo** el nuestro: «exactamente uno» se cumple por construcción.

**0.2 — Metadatos del episodio.** `class-metaboxes.php::registerMetaFields()` registra, para `podcast`,
`mp3-url` (URL del audio), `number` y `season`, y los declara públicos en REST. Sus cajas y su guardado
viven en `renderAudioUrlMetabox()`, `renderEpisodeNumberMetabox()`, `renderSeasonMetabox()` y
`saveMetaboxes()`. **Decisión: el metadato de la transcripción es `transcript-srt`, y guarda el
**ID del adjunto** (entero), no una URL.** Consecuencias: la URL publicada sale de
`wp_get_attachment_url()` y la existencia se comprueba con `get_attached_file()` —una URL suelta no
permitiría verificar «el fichero no existe o no es accesible»—; y el pipeline puede fijarlo con
`wp post meta update <id> transcript-srt <attachment_id>` tras `wp media import`. El feed lee **solo**
este metadato.

**0.3 — Dobles del arnés.** Ya existen `home_url`, `get_option`, `get_post_meta`, `esc_url`,
`esc_attr`, `esc_html`, `esc_url_raw`, `get_the_title`, `get_permalink`, `get_the_excerpt`,
`wp_strip_all_tags`, `sanitize_text_field`, `apply_filters`, `do_action`, `add_action`/`add_filter`,
`wp_salt`, `absint`(**no**, falta) y `wp_json_encode`. **Faltan** y hay que añadirlos modelando el
núcleo, no la implementación: `get_bloginfo`, `get_post_type_archive_link`,
`get_post_type_archive_feed_link`, `wp_get_attachment_url`, `get_attached_file`, `get_the_date`,
`trailingslashit`, `absint`, `get_query_var`, `is_feed`, `is_post_type_archive`, `update_post_meta`
y `wp_kses_post`. El XML se valida con `DOMDocument`/`simplexml` nativos (sin doble). La suite medirá
por **API pura** —el patrón ya establecido por `MCPDiscovery::responseFor()` y `MCPRegistry::responseFor()`—
y compondrá el documento a partir de los descriptores de elemento para probar que el XML es **bien
formado** y que los prefijos resuelven a namespaces declarados.

**0.4 — `podcast:guid` y valores por defecto.** `podcast:guid` = **UUIDv5** (RFC 4122: SHA-1 sobre
`NAMESPACE_URL` + la URL del feed, con los bits de versión y variante fijados) de
`feedUrl()`. Es la convención que recomienda Podcasting 2.0 — el guid *es* la derivación estable de la
URL del feed — y cumple el requisito «determinista y que no cambia entre reconstrucciones ni versiones».
Ajustes nuevos en `class-theme-options.php`, todos con valor por defecto razonable y **una sola fuente
de verdad**: `atareao_podcast_cover` (portada; debe ser URL **absoluta https**),
`atareao_podcast_author`, `atareao_podcast_language` (por defecto el idioma del sitio),
`atareao_podcast_category`, `atareao_podcast_owner_name`, `atareao_podcast_owner_email`,
`atareao_podcast_type` (`serial` por defecto, configurable) y `atareao_podcast_explicit`
(booleano explícito). La URL del feed reutiliza la opción que **ya** existe y **ya** anuncia el tema,
`atareao_podcast_feed`, con repliegue a la URL del archivo.

**0.5 — Contrato único del SRT, con las dos vías.** El contrato es **adjunto de la biblioteca de medios
+ metadato `transcript-srt` del episodio**, y el metadato es su **única fuente de verdad**; el feed lee
solo el metadato (ni una ruta por convención, ni un nombre de fichero, ni un escaneo de directorio). Las
dos vías escriben **el mismo** metadato con el **mismo** tipo de valor (ID de adjunto), de modo que el
feed no puede distinguir cómo llegó el fichero:
- **manual** — la caja de la ficha del episodio permite elegir un adjunto existente o subir uno nuevo,
  con saneado en el guardado (`absint` del ID + comprobación de que el adjunto existe y es accesible);
- **automatizada** — el pipeline sube el SRT como adjunto y escribe el metadato, **sin** duplicar la
  lógica de asociación: reutiliza la misma función de normalización que usa la caja.

## 1. RED — los tests primero, y que fallan

- [x] 1.1 El feed del podcast se sirve como `application/rss+xml` y su cuerpo es XML bien formado
- [x] 1.2 El elemento raíz declara los namespaces `podcast` e `itunes`
- [x] 1.3 El canal declara los metadatos que exigen los directorios (autor, tipo, categoría, propietario,
      portada absoluta) y **no duplica** los cinco elementos que ya emite el núcleo (título, enlace,
      descripción, idioma, `atom:link`)
- [x] 1.4 `podcast:locked` vale `yes` y lleva el correo del propietario
- [x] 1.5 `podcast:guid` es idéntico en dos composiciones del mismo programa
- [x] 1.6 Un episodio publicado con `mp3-url` declara un único `<enclosure>` con URL, tipo y tamaño
- [x] 1.7 Sin tamaño determinable → `length` `0` y el episodio sigue suscribible
- [x] 1.8 Un episodio con `number` y `season` declara la numeración (`itunes:` y `podcast:`)
- [x] 1.9 Un podcast sin `mp3-url` no declara `<enclosure>` ni elementos de episodio, y el XML sigue válido
- [x] 1.10 Un episodio con SRT declara `<podcast:transcript>` con URL absoluta, tipo `application/x-subrip` e idioma
- [x] 1.11 Sin transcripción no se declara `<podcast:transcript>`; una transcripción a un fichero
      inexistente tampoco se declara
- [x] 1.12 El feed excluye borradores, privados y protegidos por contraseña; no expone datos internos
- [x] 1.13 El feed principal `/feed/` no incorpora vocabulario de podcasting
- [x] 1.14 `nginx/pro/nginx.conf`: la ruta del feed está exenta del bloqueo por User-Agent (línea anclada, no comentada)
- [x] 1.15 El idioma del programa llega por el filtro `get_bloginfo_rss` y el canal declara **un único** `<language>`
- [x] 1.16 La caja del SRT guarda el **ID del adjunto normalizado** (`normalizeTranscriptAttachment`)
- [x] 1.17 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

Suite nueva `Podcast Feed Test Suite` en `tests/test-valet.php` (**solo adiciones**: 975 líneas) y los
dobles que faltaban en `tests/wp-stubs.php` (**solo adiciones**: 220 líneas). Ningún fichero de
producción se toca: en RED lo que se mide es la ausencia del módulo, así que toda llamada va guardada
con `class_exists`/`method_exists` (patrón de la suite `(reg)`) y el envoltorio devuelve `null` sin
abortar el proceso. **16 escenarios, 109 aserciones con prefijo `(feed)`.**

```
$ just test
Total passed: 924, Total failed: 88        # EXIT=1
error: recipe `test` failed with exit code 1

$ grep -c 'FAIL: (feed)'                  → 88
$ grep 'FAIL:' … | grep -vc '(feed)'      → 0     # ninguna aserción preexistente rota
```

Línea base verificada antes de la suite: `Total passed: 899, Total failed: 0`; y tras añadir **solo**
los dobles, `899 / 0` — los dobles nuevos no rompen ninguna aserción preexistente. Los 88 fallos son
todos nuevos y todos del módulo ausente; las **25** aserciones `(feed)` verdes en RED son
contrapruebas legítimas (ausencia de elementos, invariante de no duplicación, XML bien formado del
documento vacío, y la estabilidad del guid, que en RED compara `null` consigo mismo). Se eliminaron dos
aserciones que solo medían la plantilla de la propia suite y no podían fallar nunca.

**Dobles añadidos** (modelan el núcleo, no la implementación): `get_bloginfo`,
`get_post_type_archive_link`, `get_post_type_archive_feed_link`, `get_attached_file`,
`attachment_url_to_postid`, `trailingslashit`, `absint`, `wp_kses_post`, `get_query_var`,
`is_post_type_archive` y `update_post_meta`; más `mock_bloginfo`, `mock_archive_links`,
`mock_archive_feed_links`, `mock_query_vars` y `mock_post_type_archive` en `harness_reset_state()`.

**Cómo mide la suite.** Por API pura (el patrón de `MCPDiscovery::responseFor()` y
`MCPRegistry::responseFor()`) y **componiendo el documento**: la suite teje un feed RSS2 como el del
núcleo —con su `atom:link`, su `<language>` pasado por `apply_filters('get_bloginfo_rss', …)` y su
`<item>`— y le inyecta los descriptores del módulo, de modo que el XML se valida con
`DOMDocument`/`libxml_get_errors()` y los prefijos se comprueban resolviendo **namespaces de verdad**
(`getElementsByTagNameNS()`). El tamaño del audio y la accesibilidad del SRT se miden contra
**ficheros temporales reales** (`1234` bytes y un SRT legible) y contra una ruta que no existe, que es
lo único que distingue de verdad «el fichero no existe o no es accesible» de un metadato puesto.

**El guid se verifica contra una implementación independiente.** La suite implementa **UUIDv5 (RFC
4122)** por su cuenta —SHA-1 sobre los bytes de `NAMESPACE_URL` + la URL del feed, con los bits de
versión y variante fijados— y afirma que `programGuid()` da exactamente ese valor. Comprobado además
por el Director con una implementación de terceros (`uuid.uuid5` de Python):
`cfba68b4-171d-5e9c-a892-b80e25ebc869` para `https://atareao.es/podcast/feed/`.

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
