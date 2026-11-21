# seo-titulos-meta-2026-11-21 — Títulos y meta descripciones reescritos (5 páginas)

## Qué es

Acción de SEO on-page (issue **#1**): reescritura de `_genesis_title` y `_genesis_description`
de **cinco páginas con CTR bajo** en Search Console, para alinearlas con la **consulta real** que
ya trae impresiones y sin sufijo de marca.

## Páginas y cambios

Datos de Search Console de los últimos **28 días** (consulta → página), medidos el **2026-11-21**.
El título mostrado por Google lo compone The SEO Framework (TSF): valor de `_genesis_title` +
sufijo ` - atareao con Linux`.

> **Sobre el «antes»:** los valores previos de producción se capturaron hoy con `?cb=` (evitando la
> caché). Las **cinco** páginas tenían `_genesis_description` **vacía**, así que TSF generaba la
> descripción a partir del **extracto** (*post excerpt*) del post —no del título—. En cuanto al
> título, `_genesis_title` estaba **vacío en cuatro** de ellas (TSF usaba el título del post); solo
> **Neovim** tenía un `_genesis_title` personalizado (`Neovim`).

### 16565 — yt-dlp
- **URL:** https://atareao.es/podcast/yt-dlp-es-una-pura-maravilla-eliminando-carazas/
- **Consulta GSC:** `yt-dlp` — 5.668 impr · 16 clics · CTR 0,28 % · pos. 8,47
- **Título:** `yt-dlp es una pura maravilla. Eliminando carazas - atareao con Linux`
  → `yt-dlp: la navaja suiza para descargar vídeo y audio - atareao con Linux`
- **Meta:** `yt-dlp una herramienta imprescindible para quien quiera relacionarse con #YouTube en #Linux y sacarle el máximo partido, seas creador de contenido o no`
  → `yt-dlp es la herramienta imprescindible en Linux para descargar vídeo y audio de YouTube y cientos de webs. Guía, formatos y trucos.`

### 19732 — ArtCraft
- **URL:** https://atareao.es/podcast/artcraft-haz-que-la-ia-trabaje-por-ti/
- **Consulta GSC:** `artcraft` — 3.766 impr · 14 clics · CTR 0,37 % · pos. 6,11
- **Título:** `ArtCraft: haz que la IA trabaje por ti - atareao con Linux`
  → `ArtCraft: apps con MCP para que la IA trabaje por ti - atareao con Linux`
- **Meta:** `ArtCraft lleva servidor MCP dentro de cada app: pídele a tu agente el Word, la hoja de cálculo o la presentación. Demos reales, coste en tokens y alfa.`
  → `ArtCraft integra un servidor MCP en cada app: pídele a tu agente el Word, la hoja de cálculo o la presentación. Demos reales y coste en tokens.`

### 16274 — Neovim
- **URL:** https://atareao.es/tutorial/neovim/
- **Consulta GSC:** `neovim` — 2.189 impr · 5 clics · CTR 0,23 % · pos. 5,76
- **Título:** `Neovim - atareao con Linux`
  → `Neovim: el editor de Vim moderno y extensible - atareao con Linux`
- **Meta:** `Neovim es un editor de texto súper extensible basado en Vim. El gran potencial de este editor de texto radica en su increíble potencial de personalización.`
  → `Neovim es un editor de texto súper extensible basado en Vim. Descubre su personalización, plugins y por qué mejora a Vim.`

### 17498 — Niri
- **URL:** https://atareao.es/podcast/niri-un-gestor-de-ventanas-diferente/
- **Consulta GSC:** `niri` — 1.169 impr · 10 clics · CTR 0,86 % · pos. 5,55
- **Título:** `Niri, un gestor de ventanas diferente - atareao con Linux`
  → `Niri: un gestor de ventanas con scroll para Linux - atareao con Linux`
- **Meta:** `Descubre un nuevo gestor de ventanas para #Linux que usa scroll en lugar de escritorios fijos. Una forma distinta de organizar tu escritorio.`
  → `Niri es un gestor de ventanas Wayland para Linux que usa scroll en lugar de escritorios fijos. Otra forma de organizar tu escritorio.`

### 16599 — Gitea
- **URL:** https://atareao.es/podcast/mi-propio-github-de-la-mano-de-gitea/
- **Consulta GSC:** `gitea` — 1.171 impr · 6 clics · CTR 0,51 % · pos. 5,70
- **Título:** `Mi propio GitHub de la mano de Gitea - atareao con Linux`
  → `Gitea: tu propio GitHub autoalojado - atareao con Linux`
- **Meta:** `Gitea es una alternativa a #GitHub que puedes auto hospedar en modo #selfhosted y con el que tendrás tu servicio de repositorios #git para #Linux y otros`
  → `Gitea es una alternativa ligera a GitHub que puedes autoalojar para tener tu servidor de repositorios Git. Instalación y uso.`

## Mecanismo

- Se escriben las metas por **WP-CLI** contra producción, con `update_post_meta()`:

  ```php
  update_post_meta($id, '_genesis_title', $nuevo_titulo);
  update_post_meta($id, '_genesis_description', $nueva_descripcion);
  ```

- **TSF** lee esas metas y compone el `<title>` como `_genesis_title` + ` - atareao con Linux`
  (el sufijo lo añade el plugin, no se guarda en la meta).
- No hay cambio de código ni de plugin: es **contenido** (postmeta), no deploy.

## Cómo revertir

Restaurar el estado previo real (capturado hoy con `?cb=`):

- **16274 (Neovim):** recuperar su `_genesis_title` personalizado (`Neovim`) y borrar
  `_genesis_description`.
- **16565, 19732, 17498, 16599:** borrar `_genesis_title` y `_genesis_description`.

  ```bash
  # 16274: restaurar el título personalizado y borrar la descripción
  docker exec wpcli wp post meta update 16274 _genesis_title 'Neovim'
  docker exec wpcli wp post meta delete 16274 _genesis_description
  # el resto: borrar ambas metas (vuelven al título del post y al extracto)
  for id in 16565 19732 17498 16599; do
    docker exec wpcli wp post meta delete $id _genesis_title
    docker exec wpcli wp post meta delete $id _genesis_description
  done
  ```

- Tras el cambio, purgar la caché (`fastcgi_cache`) de las cinco URLs para no servir el `<title>`
  antiguo.

## Otras acciones registradas (2026-11-21)

- **Sitemap duplicado:** `/sitemap_index.xml` responde **301 → `/sitemap.xml`** (verificado en
  producción; change `sitemap-index-redirect`). **Pendiente manual:** retirarlo de la UI de
  Search Console.
- **#255 VideoObject:** verificado en producción (`embedUrl` `youtube-nocookie`, `duration`
  ISO 8601 y `thumbnailUrl` `maxres`). **Pendiente manual:** validar la corrección en GSC
  (informe *Vídeos*) y cerrar #255.
- **Permisos:** `chmod 600` al JSON de la *service account* de GSC
  (`/home/lorenzo/Descargas/gen-lang-client-0363145848-a7e67b441a2d.json`, antes `644`).
