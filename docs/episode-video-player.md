# episode-video-player — Reproductor del vídeo del episodio incrustado

## Qué es y qué problema resuelve

Hasta ahora, un episodio con vídeo conocido **enlazaba** su vídeo al final de las notas con un
enlace de texto (`«Ver el vídeo de este episodio»`, `.episode-video-link`, introducido en **#214**),
pero el vídeo **no se veía** en la página: para verlo había que salir a YouTube. La ficha del
episodio y su vídeo quedaban desconectados.

Esta feature convierte ese enlace en un **reproductor incrustado** en la propia página del episodio:
una **fachada** —miniatura más botón de reproducción— que carga el `<iframe>` de YouTube **solo al
pulsar**, desde el dominio de privacidad mejorada `www.youtube-nocookie.com`. El usuario ve el vídeo
sin salir de la ficha y sin cargar terceros al abrir la página.

La base ya estaba puesta por **#214**, que añadió el metadato `youtube_url` y la resolución
contenido → metadato: **843 de 850** episodios exponen hoy su identificador de YouTube. Este cambio
(Issue de entrada **#240**) multiplica ese valor haciendo que ese vídeo **se vea**, no solo se enlace.

El cambio es **aditivo y reversible**: sin el metadato o sin el vídeo resuelto, el comportamiento es
exactamente el de antes.

## Ficheros implicados

| Fichero (ruta relativa al repo) | Papel |
|---|---|
| `wp-content/plugins/atareao-functionality/includes/class-seo.php` | Amplía `SEO::resolveVideo()` (retorno con `id` y `source`); contiene la fachada `SEO::appendEpisodeVideoPlayer()` (sustituye a `appendEpisodeVideoLink()`) y el registro/encolado de assets (`registerVideoAssets()`, `enqueueVideoAssets()`, `videoPlayerApplies()`). Constantes `VIDEO_HANDLE = 'atareao-episode-video'` y `PODCAST_EPISODE_TYPES`. |
| `wp-content/plugins/atareao-functionality/assets/css/episode-video.css` | Estilo de la fachada (contenedor, miniatura, botón de reproducción, texto accesible oculto). Sin dependencias. |
| `wp-content/plugins/atareao-functionality/assets/js/episode-video.js` | Mejora progresiva: intercepta el clic del enlace y lo sustituye por el `<iframe>` de `youtube-nocookie.com`. JS vanilla, sin jQuery. |
| `nginx/pro/wp.conf` | CSP de producción: añade `https://i.ytimg.com` a `img-src` para permitir la miniatura. |
| `tests/episode-video-player/test_player.php` | Arnés PHP del contrato: retorno ampliado, markup de la fachada, no-duplicado, encolado condicional, CSP y no-regresión de `VideoObject`. |
| `openspec/changes/episode-video-player/` | Change OpenSpec: `proposal.md`, `design.md` y el delta `specs/seo/spec.md`. |

## Cómo funciona

La resolución del vídeo vive en **un único camino** (herencia de #214), de forma que la fachada y el
`VideoObject` nunca se contradicen:

```
appendVideoObject()            ─┐
appendEpisodeVideoPlayer()     ─┴─► SEO::embeddedVideo($post) ─► SEO::resolveVideo($post)
```

`SEO::embeddedVideo( int|\WP_Post|null $post ): array|null` es el punto público. `resolveVideo()`
prueba **en dos pasos** y devuelve ahora
`array{url:string, id:string, thumbnail:string, source:string}` o `null`:

1. **Contenido** (`post_content`): extrae el identificador de un embed de YouTube
   (`youtube.com/embed/`, `youtube.com/watch?v=`, `youtu.be/`) o detecta una URL de Vimeo. Es el
   comportamiento histórico **sin cambios**; devuelve `source = 'content'` (y `id` vacío para Vimeo).
2. Solo si el contenido no declara vídeo, el **metadato `youtube_url`**, del que se extrae el
   identificador de YouTube. Devuelve `source = 'meta'`.

La **miniatura** se deriva siempre del identificador de YouTube
(`https://i.ytimg.com/vi/<id>/hqdefault.jpg`). Los campos `id` y `source` son **aditivos**: los
consumidores que solo leían `url`/`thumbnail` (como `VideoObject`) no cambian.

**La fachada se emite SOLO cuando `source === 'meta'`.** `appendEpisodeVideoPlayer()` está
enganchada a `the_content` (prioridad 20) y retorna el contenido intacto salvo cuando la página es el
singular de un episodio **y** el vídeo proviene del metadato. Si el cuerpo ya incrusta el vídeo
(`source === 'content'`), **no se añade nada** al final: el usuario ya lo ve en el cuerpo y la
fachada sería un reproductor duplicado.

**Mejora progresiva.** El markup emitido es un enlace `<a>` a la URL canónica del vídeo. **Sin
JavaScript**, el clic navega a YouTube (respaldo funcional y accesible). **Con JavaScript**
(`assets/js/episode-video.js`), el clic se intercepta, se evita la navegación y el `<a>` se sustituye
por el `<iframe>` de `www.youtube-nocookie.com`, que solo entonces se carga (`autoplay=1&rel=0`). No
se usa `<noscript>`: el propio enlace es el respaldo.

## El markup de la fachada

Al final del contenido de un episodio con vídeo de metadato, `appendEpisodeVideoPlayer()` emite:

```html
<figure class="episode-video" data-video-id="<id>">
  <a class="episode-video__link" href="https://www.youtube.com/watch?v=<id>" rel="noopener">
    <img class="episode-video__thumb" src="https://i.ytimg.com/vi/<id>/hqdefault.jpg"
         alt="" width="480" height="360" loading="lazy" decoding="async">
    <span class="episode-video__play" aria-hidden="true"></span>
    <span class="screen-reader-text">Ver el vídeo de este episodio</span>
  </a>
</figure>
```

El `<a>` contiene la miniatura (con `loading="lazy"`, `decoding="async"` y dimensiones explícitas
`480×360` para evitar CLS), el botón de reproducción (`aria-hidden`, decorativo) y un texto accesible
oculto (`screen-reader-text`) que describe la acción.

**Al pulsar**, el JS lee el identificador de `data-video-id` (del `<figure>`, con respaldo en el
enlace), crea el `<iframe>` y **reemplaza el `<a>`** por él:

```html
<iframe class="episode-video__iframe" width="560" height="315"
        src="https://www.youtube-nocookie.com/embed/<id>?autoplay=1&rel=0"
        title="Vídeo del episodio" loading="lazy" allowfullscreen
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
```

El CSS da al contenedor `.episode-video` un `aspect-ratio: 16 / 9` y fondo negro, y dibuja el botón
de reproducción (rectángulo con triángulo) centrado sobre la miniatura; al pasar el ratón o al
enfocar, el botón se vuelve rojo (`#c00`).

## Assets

Los assets de la fachada viven en el plugin y se identifican con un único handle,
`atareao-episode-video` (`SEO::VIDEO_HANDLE`); WordPress mantiene registros separados para estilos y
scripts, así que no colisionan:

- `wp-content/plugins/atareao-functionality/assets/css/episode-video.css`
- `wp-content/plugins/atareao-functionality/assets/js/episode-video.js`

**Registro:** `registerVideoAssets()` se llama siempre en `SEO::init()` (registrar es barato). La
ruta sale de `ATAREAO_PLUGIN_URL` y el **versionado de `ATAREAO_PLUGIN_VERSION`** (cache-busting). El
JS se registra con `in_footer = true` (se imprime al pie).

**Encolado condicional:** `enqueueVideoAssets()` está enganchada a `wp_enqueue_scripts` y encola CSS y
JS **solo** cuando `videoPlayerApplies()` es cierto: la página es el **singular de un episodio**
(`is_singular(PODCAST_EPISODE_TYPES)`) y la resolución del vídeo da `source === 'meta'`. Nunca en
listados, portada ni episodios sin vídeo (tampoco cuando el vídeo está en el cuerpo). El veredicto se
**memoiza por identificador de entrada** (`$video_assets_applies`), de modo que una petición real
resuelve el vídeo una sola vez.

## CSP

La Cabecera de Seguridad de Contenido de producción está **definida y versionada** en
`nginx/pro/wp.conf` (y desplegada en el host). Su `img-src` era:

```
img-src 'self' data: https://atareao.es https://secure.gravatar.com
```

con lo que la miniatura de la fachada (`https://i.ytimg.com/vi/<id>/hqdefault.jpg`) habría quedado
**bloqueada** por el navegador. Por eso se añadió `https://i.ytimg.com`:

```
img-src 'self' data: https://atareao.es https://secure.gravatar.com https://i.ytimg.com; … frame-src 'self' https: blob:;
```

El `<iframe>` **no** requería tocar la CSP: `frame-src` ya admite `https:` y cubre el origen del
reproductor. Tampoco se añade `connect-src`: el iframe gestiona su propio tráfico. No hay cambio
alguno en `script-src` ni `style-src`, porque los assets se sirven desde el propio dominio
(`'self'`).

## Cómo habilitar / desplegar

El cambio es **aditivo y reversible**; no hay migración de datos. Secuencia:

1. **Merge** de la rama `feature/episode-video-player` a `development` (release a `main` por el flujo
   habitual).
2. **Release** y **deploy de ficheros** del plugin `atareao-functionality` (incluye el cambio en
   `includes/class-seo.php` y los dos assets nuevos).
3. **Deploy de `nginx/pro/wp.conf`** con **recarga de nginx** (la CSP es una cabecera del servidor;
   sin recargar, la miniatura seguiría bloqueada).
4. **Verificar en producción:** abrir la página de un episodio **sin** embed en el cuerpo y
   comprobar que (a) aparece la fachada `.episode-video` con la miniatura, (b) al pulsar se carga el
   `<iframe>` de `youtube-nocookie.com`, y (c) la consola del navegador **no** reporta la miniatura
   como bloqueada por CSP.

**Deshacer** es retirar la emisión de la fachada (por ejemplo, desenganchar
`appendEpisodeVideoPlayer` de `the_content`, que deja el comportamiento previo con el vídeo resuelto
para el `VideoObject`). La CSP también es reversible: basta **quitar `https://i.ytimg.com` de
`img-src`** y recargar nginx. Como el enlace de texto previo queda cubierto por la fachada, no hace
falta revertir datos.

## Decisiones

Resumen de `openspec/changes/episode-video-player/design.md`:

- **Fachada, no `<iframe>` directo.** Miniatura más botón de reproducción; el reproductor se carga al
  pulsar (patrón «lite embed»). Evita cargar terceros y las cookies/JS de YouTube al abrir, protegiendo
  los Core Web Vitals (LCP/CLS). Descartado: `<iframe>` directo al cargar.
- **`youtube-nocookie.com`.** El iframe se sirve desde el dominio de privacidad mejorada: YouTube no
  deja cookies de seguimiento hasta que se reproduce.
- **La fachada solo cuando el vídeo proviene del metadato.** Con el vídeo ya incrustado en el cuerpo
  (`source === 'content'`) no se emite nada, para no duplicar el reproductor. Los ~36 episodios que
  incrustan el vídeo dejan de mostrar el enlace final: es una mejora (ya ven el vídeo), no una pérdida.
  Descartado: sustituir el enlace por la fachada en todos los episodios con vídeo.
- **`id` y `source` aditivos en la resolución.** `resolveVideo()` amplía su retorno con el
  identificador de YouTube (`id`; vacío para Vimeo) y el origen (`source`), necesarios para construir
  el embed y para decidir el no-duplicado. Aditivo para los consumidores actuales (`VideoObject`).
- **Mejora progresiva (anchor → iframe), sin `<noscript>`.** El `<a>` navega a YouTube sin JS y se
  sustituye por el iframe con JS. Evita duplicar marcado y mantiene accesibilidad (foco y nombre
  accesible).
- **Encolado condicional de assets.** Registro siempre (barato) y encolado solo en el singular con
  vídeo de metadato, memoizado por petición.

## Follow-ups

- **Retirar el metadato `youtube_url`** cuando deje de aportar (hoy alimenta la fachada y el
  `VideoObject`); valorar si el mapa de datos sigue vigente.
- **Revisar los 7 episodios sin vídeo** del residuo del mapeo de #214 (3 previos a 2018, 1 entrevista
  de 2022 y 3 con el título reformulado), si se desea cubrirlos.
- **Valorar una miniatura local** (servir la imagen desde el propio dominio) para evitar la petición a
  `i.ytimg.com` y poder prescindir del permiso de CSP correspondiente.

## Referencias

- Issues: **#240** (entrada de este change) y **#214** (metadato `youtube_url` y resolución
  contenido → metadato).
- Change OpenSpec: `openspec/changes/episode-video-player/` (`proposal.md`, `design.md`,
  `specs/seo/spec.md`).
- Feature previa: `docs/youtube-video-fallback.md`.
