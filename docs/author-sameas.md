# author-sameas — Perfiles externos (`sameAs`) en la entidad `Person` del grafo

## Qué es

El plugin `atareao-functionality` enriquece el grafo JSON-LD que compone The SEO Framework (TSF)
añadiendo `sameAs` —los perfiles externos del sitio (GitHub, YouTube, iVoox, Spotify, Apple,
Telegram, X, Mastodon, LinkedIn), leídos de las opciones `atareao_social_*`— a las entidades
`Person` del grafo.

Antes de este change el enriquecimiento solo alcanzaba las `Person` de **primer nivel**. Como TSF
emite la `Person` del sitio **incrustada** dentro de `WebSite.publisher` en todas las superficies
salvo la portada, la persona quedaba descrita a medias: en `/author/atareao/` —criterio de
aceptación de T10— la entidad `Person` **no declaraba `sameAs`**.

## Ficheros implicados

| Fichero | Qué aporta |
|---|---|
| `wp-content/plugins/atareao-functionality/includes/class-seo.php` | `enrichPersonProfiles()` (línea ~948) y los helpers `enrichPersons()` e `isDescribedPerson()`; `mergeProfiles()` (~979) y `socialProfiles()` (~1040) |
| `tests/structured-data/test_person_sameas.php` | Arnés del change: 6 escenarios del delta |
| `tests/test-valet.php` | Suite de datos estructurados: escenarios de `Person` anidada y de referencia pura |
| `.justfile` (receta `test-t10`) y `.github/workflows/ci.yml` | Ejecutan el arnés en local y en CI |

## Cómo funciona

1. TSF pasa el grafo —un array secuencial de entidades— por el filtro
   `the_seo_framework_schema_graph_data`, con `addArticleSchema()` enganchado a él.
2. `addArticleSchema()` llama a `enrichPersonProfiles($graph)`.
3. `enrichPersonProfiles()` lee `socialProfiles()`; si está vacío, devuelve el grafo sin tocar.
4. `enrichPersons()` recorre el grafo **en profundidad** (listas y mapas) y enriquece toda entidad
   que sea una **`Person` descrita** (`isDescribedPerson()`: `@type` incluye `Person` **y** declara
   `name` o `url`), fusionando `sameAs` con `mergeProfiles()`.
5. Las **referencias puras** (nodos `Person` con solo `@id`, o `@id` + `@type`) **no se tocan**: por
   diseño no llevan propiedades propias y añadírselas los convertiría en nodos en línea duplicados.

Efecto por superficie:

| Superficie | Antes | Después |
|---|---|---|
| `/` (portada) | `Person` de primer nivel con 9 perfiles | igual |
| `/author/atareao/` | `WebSite.publisher` sin `sameAs` | **con `sameAs`** |
| `/blog/`, `/podcast/`, `/tutoriales/`, `/aplicaciones/`, `/software/` | `publisher` sin `sameAs` | con `sameAs` |
| ficha de episodio o de tutorial | `publisher` sin `sameAs`; `WebPage.author` con solo `x.com` | `publisher` con `sameAs`; `author` con los perfiles del sitio añadidos |

`@type`, `@id`, el número de entidades del grafo y el número de bloques
`<script type="application/ld+json">` no cambian.

## Variables / configuración

Ninguna nueva. Los perfiles salen de las opciones existentes `atareao_social_github`,
`atareao_social_youtube`, `atareao_social_ivoox`, `atareao_social_spotify`, `atareao_social_apple`,
`atareao_social_telegram`, `atareao_social_x`, `atareao_social_mastodon` y `atareao_social_linkedin`.
El enlace de YouTube se normaliza al canal canónico (`normalizeYouTubeChannel()`).

## Cómo verificar

```bash
# Local (sin WordPress): arnés del change
podman run --rm -v "$PWD:/workspace:Z" -w /workspace --entrypoint php \
  docker.io/wordpress:cli-php8.3 tests/structured-data/test_person_sameas.php

# Suites completas
just test-t10 && just test && just php-lint
```

Producción (bypass de nginx con `?cb=`; comprobar `x-cache-status: BYPASS`):

```bash
CB=$RANDOM$RANDOM
curl -s "https://atareao.es/author/atareao/?cb=$CB" | rg -o '"sameAs":\[[^]]*\]'
```

Debe devolver un `sameAs` con GitHub, el canal de YouTube y las plataformas de podcast.

## Decisiones

- **Alcance general, no `is_author()`**: el enriquecimiento depende del **grafo**, no del contexto de
  la petición. Arreglar solo la página de autor habría dejado inconsistentes los listados y las
  fichas, que sufren el mismo hueco, y habría atado la lógica a condicionales de WordPress (más
  difícil de probar). Ver `openspec/changes/2026-11-20-author-sameas/design.md` (D1).
- **«Descrita» = `name` o `url`** (D3): criterio mínimo y estable para no inflar referencias puras.
- **Fusión aditiva y deduplicada** (D2): se reutiliza `mergeProfiles()`; los perfiles previos se
  conservan y no se repiten.

## Despliegue

Un solo fichero: `includes/class-seo.php`, con `just deploy-prod` (sha256 + `owner=82:82`). Rollback:
restaurar el fichero anterior; el cambio está confinado a `enrichPersonProfiles()` y sus dos helpers.

## Follow-ups

- **Vídeo del episodio** (`atareao/atareao.es#214`): solo 36/850 fichas de episodio enlazan su vídeo
  (falta el dato vídeo↔episodio, que depende del acceso a YouTube).
- El requisito se fusionó en `openspec/specs/structured-data/spec.md` al archivar el change.
