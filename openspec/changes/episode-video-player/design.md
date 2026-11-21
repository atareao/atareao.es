# Design: Reproductor del vídeo del episodio incrustado (fachada + youtube-nocookie)

## Contexto

`\Atareao\SEO::resolveVideo()` (privada) resuelve el vídeo de un episodio con precedencia
**contenido → metadato**, y `embeddedVideo()` es su único punto público. Hoy devuelve
`array{url:string,thumbnail:string}`. Dos consumidores lo usan: el enlace al final del contenido
(`appendEpisodeVideoLink`, enganchado a `the_content` prioridad 20) y la entidad `VideoObject`
(`appendVideoObject`). El tema pinta el contenido con `apply_filters('the_content', $raw)`, así que
lo que se añada al filtro aparece al final de las notas del episodio.

## Decisiones

### Decisión 1 — Fachada (miniatura + play), no iframe directo

Se muestra la miniatura con un botón de reproducción; el `<iframe>` de YouTube se carga **al
pulsar**. Evita cargar el reproductor (y sus cookies/JS de terceros) al abrir la página, protegiendo
los Core Web Vitals. Es el patrón «lite embed».

**Alternativa descartada:** `<iframe>` directo al cargar la página (más simple, pero penaliza LCP/CLS
y carga terceros siempre).

### Decisión 2 — `youtube-nocookie.com`

El `<iframe>` se carga desde `https://www.youtube-nocookie.com/embed/<id>` (privacidad mejorada):
YouTube no deja cookies de seguimiento hasta que se reproduce.

### Decisión 3 — La fachada solo cuando el vídeo proviene del metadato

Si el cuerpo del episodio **ya incrusta** el reproductor (`source === 'content'`), no se añade nada
al final: el usuario ya ve el vídeo en el cuerpo y añadir la fachada sería un **reproductor
duplicado**. La fachada se emite **solo** cuando el vídeo viene del metadato `youtube_url`
(`source === 'meta'`), que es exactamente el caso que hoy solo enlaza.

**Consecuencia:** los ~36 episodios que incrustan el vídeo en el cuerpo dejan de mostrar el enlace de
texto final. Es una mejora (ya muestran el vídeo), no una pérdida.

**Alternativa descartada:** sustituir el enlace por la fachada en todos los episodios con vídeo
(duplicaría el reproductor en los ~36 con embed).

### Decisión 4 — `resolveVideo()` amplía su retorno con `id` y `source`

Para que la fachada construya el embed necesita el **identificador** de YouTube, y para la Decisión 3
necesita saber el **origen**. Se amplía el retorno a
`array{url:string,id:string,thumbnail:string,source:string}`:

- `id`: identificador de YouTube; `''` para un vídeo de Vimeo (sin id de YouTube).
- `source`: `'content'` si proviene del contenido, `'meta'` si del metadato.

Es **aditivo** para los consumidores actuales (`VideoObject` solo usa `url`/`thumbnail`). Modifica el
contrato documentado del requisito archivado, por lo que el delta lo marca como **MODIFIED**.

### Decisión 5 — Markup con mejora progresiva (anchor → iframe), sin `<noscript>`

La fachada es un `<a>` a la URL canónica del vídeo que contiene la miniatura y el botón de
reproducción. Sin JavaScript, el clic navega a YouTube (fallback funcional). Con JavaScript, el clic
se intercepta, se evita la navegación y el `<a>` se sustituye por el `<iframe>` de nocookie. Evita
duplicar el marcado con `<noscript>` y mantiene la accesibilidad (foco y nombre accesible).

Markup emitido (al final del contenido):

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

Al pulsar, `assets/js/episode-video.js` reemplaza el `<a>` por:

```html
<iframe class="episode-video__iframe" width="560" height="315"
        src="https://www.youtube-nocookie.com/embed/<id>?autoplay=1&rel=0"
        title="Vídeo del episodio" loading="lazy" allowfullscreen
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
```

### Decisión 6 — Assets encolados solo en el singular con vídeo de metadato

CSS y JS se **registran** siempre (barato) y se **encolan** en `wp_enqueue_scripts` únicamente cuando
`is_singular(PODCAST_EPISODE_TYPES)` y la resolución da `source === 'meta'`. Evita servirlos en
listados, portada o episodios sin vídeo. La comprobación se memoiza por petición.

### Decisión 7 — CSP: `img-src` += `https://i.ytimg.com`

La CSP de producción (`nginx/pro/wp.conf`, versionada y desplegada en el host) tiene
`img-src 'self' data: https://atareao.es https://secure.gravatar.com` y **bloquearía la miniatura**
de la fachada. Se añade `https://i.ytimg.com`. El iframe queda cubierto por `frame-src 'self' https:
blob:` (ya permite `https:`), por lo que **no** hace falta tocar `frame-src`. No se añade
`connect-src` (el iframe gestiona su propio tráfico).

## Riesgos

- **Rendimiento:** la miniatura de `i.ytimg.com` es una petición externa; se mitiga con
  `loading="lazy"`, `decoding="async"` y dimensiones explícitas (sin CLS).
- **JS ausente/roto:** cubierto por la mejora progresiva (el `<a>` sigue funcionando).
- **Contrato público:** `resolveVideo()` es privada; solo cambia el retorno de `embeddedVideo()`,
  aditivo para los consumidores.
