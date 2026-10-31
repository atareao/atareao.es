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
- [x] 1.14 `nginx/pro/nginx.conf`: la ruta del feed **y** las transcripciones SRT (`/wp-content/uploads/*.srt`) están exentas del bloqueo por User-Agent (líneas ancladas, no comentadas, y la exención del SRT es quirúrgica)
- [x] 1.15 El idioma del programa llega por el filtro `get_bloginfo_rss` y el canal declara **un único** `<language>`
- [x] 1.16 La caja del SRT guarda el **ID del adjunto normalizado** (`normalizeTranscriptAttachment`)
- [x] 1.17 Ejecutar `just test` → los escenarios nuevos en **ROJO**; el resto en **VERDE**

### Evidencia — fase RED

Suite nueva `Podcast Feed Test Suite` en `tests/test-valet.php` (**solo adiciones**: 1001 líneas) y los
dobles que faltaban en `tests/wp-stubs.php` (**solo adiciones**: 220 líneas). Ningún fichero de
producción se toca: en RED lo que se mide es la ausencia del módulo, así que toda llamada va guardada
con `class_exists`/`method_exists` (patrón de la suite `(reg)`) y el envoltorio devuelve `null` sin
abortar el proceso. **16 escenarios, 111 puntos de aserción** que ejecutan **115 aserciones**, todas
con prefijo `(feed)`.

```
$ just test
Total passed: 925, Total failed: 89        # EXIT=1
error: recipe `test` failed with exit code 1

$ grep -c 'FAIL: (feed)'                  → 89
$ grep 'FAIL:' … | grep -vc '(feed)'      → 0     # ninguna aserción preexistente rota
```

Línea base verificada antes de la suite: `Total passed: 899, Total failed: 0`; y tras añadir **solo**
los dobles, `899 / 0` — los dobles nuevos no rompen ninguna aserción preexistente. Los 89 fallos son
todos nuevos y todos del módulo ausente; las **26** aserciones `(feed)` verdes en RED son
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

- [x] 2.1 Namespaces del feed y metadatos del canal, con sus ajustes y valores por defecto
- [x] 2.2 `podcast:guid` con derivación determinista y estable
- [x] 2.3 `<enclosure>` y numeración del episodio desde `mp3-url`, `number` y `season`
- [x] 2.4 Metadato de transcripción + `<podcast:transcript>` cuando el SRT existe y es accesible
- [x] 2.5 Caja del SRT en la ficha del podcast (`class-metaboxes.php`): elegir un adjunto existente o
      subir uno nuevo con la biblioteca de medios (`wp.media`), con saneado en el guardado (vía **manual**)
- [x] 2.6 `nginx/pro/nginx.conf`: exención de la ruta del feed del podcast **y** de las transcripciones SRT
- [x] 2.7 Vía **automatizada**: receta del pipeline que sube el SRT como adjunto y escribe el metadato
      del episodio, sin duplicar la lógica de asociación de la caja
- [x] 2.8 Documentar en el `README.md` del plugin las **dos vías** de carga del SRT y el contrato del feed
- [x] 2.9 `just test` → **100% verde**; `just php-lint` limpio

### Evidencia — fase GREEN

Módulo nuevo `\Atareao\PodcastFeed` en `includes/class-podcast-feed.php` (namespace `Atareao`, guardián
`ABSPATH`, `init()` sin guardián de idempotencia) y su cableado en el bootstrap (`require_once` +
`::init()`). Un solo punto de emisión por hook: `rss2_ns` → namespaces, `rss2_head` → elementos de
podcasting del canal, `rss2_item` → elementos del episodio, `feed_content_type` → media type,
`get_bloginfo_rss` → idioma del programa, `pre_get_posts` → exclusión de contenido no público. Todo
acotado por `isPodcastFeed()`.

```
$ just test
Total passed: 1014, Total failed: 0        # EXIT=0   (899 preexistentes + 115 de la suite (feed))
All tests passed successfully!
```

Los 89 escenarios que estaban en rojo pasan a verde **sin tocar ninguna aserción** salvo el contador
heredado del mapa de exención (`6` → `8`), que sube porque el change añade **dos** entradas (el feed y
los SRT), no una.

**El contrato del SRT tiene una sola fuente de verdad.** `PodcastFeed::normalizeTranscriptAttachment()`
canoniza el valor a un entero positivo (o `0`) y la usan las tres puertas: la caja del metabox en
`saveMetaboxes()`, el `sanitize_callback` de `register_post_meta('podcast', 'transcript-srt', …)` y la
lectura del feed en `itemElements()`. El metadato se identifica por la constante
`PodcastFeed::TRANSCRIPT_META`, no por literales sueltos.

**La caja del SRT elige o sube el adjunto.** El campo oculto `transcript_srt` (que sigue llevando el ID)
se rellena desde `wp.media` con los botones «Seleccionar SRT» y «Quitar»; el guardado pasa el valor por
la normalización única. La ampliación de `enqueueAdminEditScripts()` se hizo por dispatch explícito
(`enqueueCapituloEditScript()` / `enqueueTranscriptPickerScript()`), de modo que el autocompletado del
número de capítulo queda intacto.

## 3. REFACTOR

- [x] 3.1 Una sola fuente de verdad por dato (URL del feed, guid, portada, idioma, metadato del SRT)
- [x] 3.2 Los elementos de podcast se emiten en un único punto; el feed principal queda intacto
- [x] 3.3 `just phpcs` sin violaciones nuevas en los ficheros tocados; `just php-lint-changed` limpio

### Evidencia — fase REFACTOR

`phpcs` (PSR12) de los ficheros tocados, antes/después: `class-theme-options.php` vuelve a
**0 ERRORS / 12 WARNINGS** (las 8 filas HTML nuevas se envolvieron por debajo de 120 caracteres; las
27 advertencias de longitud desaparecen y **ninguna fila preexistente se toca**), `class-metaboxes.php`
se queda en **0 / 18** (las mismas de antes), `class-podcast-feed.php` **0 / 1** (el mismo aviso de la
guarda `ABSPATH` que ya tiene `class-mcp-registry.php`) y `atareao-functionality.php` **0 / 1**
(preexistente). **0 ERRORES en todos.**

**El `echo` + `return` de los tres callbacks de emisión queda documentado y blindado.** WordPress
descarta el valor de retorno de `do_action()`, así que un callback de `rss2_ns`/`rss2_head`/`rss2_item`
**tiene que imprimir** para que el feed real reciba los elementos; y la suite mide el valor de retorno
(y el cableado exige esos nombres exactos). Los tres métodos lo explican en su docblock para que nadie
lo «arregle» quitando el `echo`.

`just test` tras el REFACTOR: **`Total passed: 1014, Total failed: 0`**.

## 4. Verificación

- [x] 4.1 `openspec validate podcast-feed-namespace --strict` → valid
- [x] 4.2 `just check-spec` → en verde
- [x] 4.3 Auditoría de seguridad: escapado de todas las URLs y de los metadatos en el XML (sin inyección
      ni entidades sin escapar), sin exponer contenido no público ni datos internos
- [x] 4.4 Validación del feed contra un validador de podcast (XML bien formado y elementos exigidos)
      — **resultó insuficiente**: validaba el documento **compuesto por la suite**, no el feed
      servido, y por eso el truncado del §6 pasó desapercibido. Ver §6.
- [x] 4.5 Verificación E2E en producción tras el despliegue: el feed responde, declara los namespaces,
      los episodios llevan su `<enclosure>` y la transcripción, y es alcanzable **con User-Agent `curl`**
      — con dos observaciones honestas: `length` sale `0` en todos los episodios (audio remoto) y la
      transcripción **no** se ha podido verificar porque ningún episodio tiene SRT asociado

### Evidencia — verificación

**4.1/4.2.** `openspec validate podcast-feed-namespace --strict` → *Change 'podcast-feed-namespace is
valid*; `just check-spec` → en verde. `just php-lint` global → `EXIT=0`.

**4.3 — auditoría de seguridad: 0 hallazgos explotables.** La revisión de los cuatro vectores (inyección
en el XML, exposición de contenido no público, la caja del metabox y la exención de nginx) concluye
**0 CRITICAL / 0 HIGH / 0 MEDIUM**; los cuatro hallazgos restantes (1 LOW + 3 INFO) son endurecimiento
opcional y **no bloquean el merge**. Además se ejecutó una **prueba empírica de escapado** con cargas
hostiles (ajustes y metadatos con `<script>`, `&`, `"`, `]]>`, y una URL de audio con comilla y etiqueta):

```
19 comprobaciones, 0 fallos   (EXIT=0)
```

El documento hostil carga con `DOMDocument` **sin un solo error de libxml**; **no aparece ni un elemento
`<script>`/`<b>`/`<i>`** inyectado (ni la cadena `<script` en crudo); el `]]>` del `duration` sale
escapado; cada valor desescapa **íntegro** por el parser (URL del enclosure, `length` = tamaño real del
fichero, `itunes:category/@text`, `podcast:locked/@owner`, `itunes:image/@href`); una portada `http` se
**omite**; y no se filtra ninguna ruta del servidor ni el nombre de los metadatos internos.

**4.4.** La validación **estructural** del documento compuesto (XML bien formado + todos los elementos
que exigen los directorios: namespaces, canal completo y episodio con `<enclosure>`, numeración y
`<podcast:transcript>`) queda cubierta por la propia suite `(feed)` y por la prueba de escapado. La
validación contra un **validador de directorio en línea** requiere el feed ya desplegado y se realiza en
la tarea 4.5.

#### Hallazgos de la auditoría (deuda aceptada, ninguno bloqueante)

| ID | Severidad | Qué es | Por qué se acepta |
|---|---|---|---|
| SEC-BE-001 | INFO | El `auth_callback` del metadato `transcript-srt` usa `edit_posts` en vez de `edit_post` con el post concreto. | No explotable: el controlador REST de posts ya exige `edit_post` antes de procesar los campos meta, y el patrón es idéntico al de los metadatos hermanos ya en producción. |
| SEC-BE-002 | LOW | Sin ajuste configurado, el correo del propietario cae a `admin_email` y se publica en el feed. | Es el **valor por defecto razonable** que pide el spec (el correo del propietario es requisito de los directorios y es público por diseño); el titular puede fijar `atareao_podcast_owner_email`. Documentado en el `README.md`. |
| SEC-BE-003 | INFO | La exención nginx de `*.srt` es amplia dentro de `/wp-content/uploads/`. | El mapa **solo** alimenta el `return 444` del bloqueo por User-Agent: no ejecuta nada, no toca el `deny` de `*.php` ni las zonas de rate-limit. |
| SEC-BE-004 | INFO | El idioma del programa llega al `<language>` del núcleo, que no lo escapa; `sanitize_text_field` no codifica `&`. | Exige privilegio `manage_options`; a lo sumo un feed mal formado por configuración errónea del propio admin. |

## 5. Entrega

- [x] 5.1 PR `feature/podcast-feed-namespace → development` con **merge commit** (convención del proyecto)
      — fusionado ([#131](https://github.com/atareao/atareao.es/pull/131)). La corrección del §6 va en
      una rama `fix/*` con su propio PR antes de archivar.
- [x] 5.2 Archivar el change fusionando el delta en `openspec/specs/podcast-feed/spec.md` — archivado: el delta de `podcast-feed` se volcó en la spec y el change quedó en `openspec/changes/archive/`

## 6. Corrección de los defectos hallados en el E2E de producción

El feed desplegado estaba **truncado** —un solo `<item>`, sin `</item>`/`</channel>`/`</rss>`, sin
`<enclosure>` ni `<podcast:transcript>`— y servía un `Content-Type` con el `charset` duplicado. Ninguno de
los dos defectos podía verlo el arnés: su doble `do_action()` **no modelaba** la convención del núcleo
(`wp-includes/plugin.php` convierte la lista de argumentos vacía en `array( '' )`) y la suite medía el
**valor de retorno** del callback en vez de **disparar la acción**. Esta sección corrige los dos defectos
**y la causa raíz** —la infidelidad del doble— antes de archivar el change.

- [x] 6.1 `tests/wp-stubs.php`: el doble `do_action()` modela al núcleo (lista de argumentos vacía →
      `array('')`) y las aserciones preexistentes siguen en verde
- [x] 6.2 **RED:** disparar `do_action('rss2_item')` **sin argumentos**, como el núcleo, emite el `<item>`
      completo con `<enclosure>` y `<podcast:transcript>`, y sin error
- [x] 6.3 **RED:** con dos episodios públicos el documento declara dos `<item>` cerrados y termina en
      `</channel></rss>`
- [x] 6.4 **RED:** el `Content-Type` compuesto como en `feed-rss2.php:8` declara `application/rss+xml` y
      **una sola** aparición de `charset`
- [x] 6.5 **RED:** disparar `rss2_item` con el **ID entero** de un episodio emite los elementos de ese episodio
- [x] 6.6 **GREEN:** `itemElements` con firma **tolerante** —normaliza a `null` todo valor que no sea un entero
      positivo, incluida la cadena vacía del núcleo— y registro **explícito de 1 argumento**, que es el ID
      opcional que el callback sí acepta: registrarlo con 0 descartaría el identificador que un tercero
      pudiera pasar y dejaría sin cumplir el escenario «un tercero puede disparar la acción con el
      identificador del episodio»
- [x] 6.7 **GREEN:** `FEED_CONTENT_TYPE` sin el `charset`, que lo añade el núcleo al componer la cabecera
- [x] 6.8 Evidencia: `just test` en ROJO y en VERDE (literal), prueba de mutación, `php -l` y `phpcs`
- [x] 6.9 Despliegue y verificación E2E del feed **servido** (no del documento compuesto): XML bien formado,
      100 `<item>` con su `<enclosure>`, un solo `charset` y accesible con User-Agent `curl` — cierra
      también la tarea **[4.5]**

### Evidencia — corrección de los defectos del E2E

**Causa raíz, con la cadena completa** (leída en el código del núcleo, no inferida): `feed-rss2.php:125`
dispara `do_action( 'rss2_item' )` **sin argumentos**; `plugin.php:516-517` lo convierte en `array( '' )`;
`class-wp-hook.php:353` entrega ese `''` al callback; el parámetro declarado era `?int` y PHP 8 no coacciona
`''` a `int` → **`TypeError` no capturado** en mitad del primer `<item>`.

**El arnés era la causa de la ceguera, y se corrige.** El doble `do_action()` de `tests/wp-stubs.php` **no
modelaba** la coletilla del núcleo —era más benigno que WordPress— y las aserciones llamaban al método con
un `int` en lugar de disparar la acción, así que la ruta real del hook no se ejercía nunca. El RED empieza
por los dos: el doble (17 líneas) y un escenario que compone el documento conduciendo `rss2_ns`, `rss2_head`
y `rss2_item` como `feed-rss2.php`, **capturando el `Throwable`** —sin capturarlo el proceso muere y se
pierde el contador— y midiendo además la cabecera que compone el núcleo.

```
$ just test                                   # RED  (commit ffdaf2d)
Total passed: 1021, Total failed: 6           # EXIT=1, los seis del bloque nuevo
```

```
$ just test                                   # GREEN (commit 0d5157e)
Total passed: 1027, Total failed: 0           # EXIT=0
All tests passed successfully!
$ grep -c 'FAIL:'                             → 0
```

**Mutaciones inversas**, cada una restaurada y reverificada a `1027/0`:

| Mutación | Resultado | Qué confirma |
|---|---|---|
| Firma estricta `?int` (conservando la normalización) | `1022/5` | los cinco fallos de emisión son el `TypeError` |
| `charset` de vuelta en la constante **y** su literal en el arnés | `1026/1` | el sexto fallo es la cabecera duplicada |

El literal de la aserción preexistente que fijaba la constante se movió **con** ella, porque es el mismo
cambio intencionado: por eso revertir **solo** la constante daba dos fallos y hubo que revertir ambos para
aislar el defecto de la cabecera. Queda reportado como cambio de comportamiento de una aserción previa, no
como un arreglo silencioso.

**Mutación independiente del Director, sin editar código:** `git stash` sobre el árbol de GREEN devuelve al
commit de RED y el contador vuelve a `1021/6`; al deshacer el `stash`, `1027/0`. El verde lo produce el
arreglo, no otra cosa.

**Alcance comprobado:** `FEED_CONTENT_TYPE` solo lo lee `contentType()`, que lo devuelve al filtro
`feed_content_type`; ningún otro punto del repositorio compone cabeceras con él, y fuera del feed del
podcast `contentType()` devuelve el valor recibido (hay aserción que lo fija). `php -l` limpio y `phpcs`
PSR12 en **0 errores / 1 aviso** (la guarda `ABSPATH`, preexistente).

#### E2E del feed servido (tareas 4.5 y 6.9)

Sondeos con **User-Agent `curl`** —la ruta del feed está exenta del bloqueo por User-Agent del proxy, así
que un cliente de máquina la alcanza— sobre el código **ya desplegado**, con el md5 del fichero de producción
idéntico al del repositorio (`e150d0dd88df751cc4b607bd17c12097`):

| Comprobación | Antes del arreglo | Ahora |
|---|---|---|
| Estado y tipo | `200` · `application/rss+xml; charset=UTF-8; charset=UTF-8` | `200` · **`application/rss+xml; charset=UTF-8`** |
| Cuerpo | **3 190 B, XML TRUNCADO** · 1 `<item>` · sin `</item>`/`</channel>`/`</rss>` | **184 811 B, XML BIEN FORMADO** · 100 `<item>` · cierra `</channel>` y `</rss>` |
| `<enclosure>` | **0** | **100**, todos `type="audio/mpeg"` |
| Numeración | 0 | `itunes:episode`/`itunes:season` y `podcast:episode`/`podcast:season` en los 100 |
| `itunes:duration` | 0 | 38 (solo donde se conoce: se omite, no se inventa) |
| Caché | `HIT` | `BYPASS` |

Contrapruebas: namespaces `podcast` e `itunes` declarados; **ningún `<item>` duplica** `title`, `link` ni
`enclosure` (exactamente uno de cada en los 100); el canal declara **un solo** `<language>`, **un solo**
`atom:link`, **un solo** `podcast:guid` y **un solo** `podcast:locked`; y el **feed principal `/feed/` no
cambia** (100 `<item>`, cero vocabulario de podcasting).

##### Dos observaciones honestas del E2E

1. **`enclosure/@length` vale `0` en los 100 episodios.** Todo el audio es **remoto** (`anchor.fm`/
   cloudfront) y `audioLength()` solo puede medir ficheros de la biblioteca local:
   `attachment_url_to_postid()` no resuelve una URL externa, así que la rama del tamaño real **hoy nunca se
   ejerce**. No es una violación del spec —`0` es el valor neutro que el requisito autoriza cuando el tamaño
   no puede determinarse, y el episodio sigue siendo suscribible—, pero deja **sin evidencia la ruta
   principal**. Para cerrarla de verdad habría que resolver el tamaño con una petición `HEAD` a la CDN o
   importar el audio como adjunto: es un cambio de comportamiento con coste de red por petición de feed y
   **se decide aparte**, no aquí.
2. **`<podcast:transcript>` no se ha podido verificar en producción.** Hoy **0** episodios tienen el
   metadato `transcript-srt` y **0** adjuntos `.srt` existen en la biblioteca, así que la ausencia del
   elemento es el comportamiento que exige el spec (sin transcripción no se declara ninguna) — pero **no es
   evidencia** de que la ruta funcione. El mecanismo está cubierto por el arnés (escenarios «El episodio
   declara su transcripción» y «No se declara una transcripción inexistente»); la evidencia E2E exige
   adjuntar un SRT a un episodio.

## Notas de implementación

- **El feed principal no es terreno de este change.** Toda la emisión de vocabulario de podcasting se
  acota al feed del archivo de `podcast`; un escenario lo fija para que nadie lo «unifique» después.
- **`podcast:guid` no se recalcula.** Es la dirección permanente del programa ante los directorios;
  cambiarlo es un cambio de identidad, no una mejora.
- **Un doble infiel es peor que ninguno.** El arnés declaraba verde un feed que producción servía
  truncado; la lección queda fijada en el doble `do_action()` y en medir el documento del hook.
- **`length` `0` es la rendición honesta.** Se emite `0` solo cuando el tamaño no se puede determinar;
  inventarse un valor es peor que declarar que no se conoce.
