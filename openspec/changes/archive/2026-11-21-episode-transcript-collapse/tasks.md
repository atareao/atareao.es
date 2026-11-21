# Tasks: Plegar la transcripción del episodio en un desplegable

## 1. Presentación plegada (TDD)

- [x] 1.1 RED — Test: `transcriptHtml()` con transcripción → el HTML contiene `<details` con `class="episode-transcript"` y un `<summary>` «Transcripción», y el texto sigue presente (indexable).
- [x] 1.2 RED — Test: el `<details>` NO lleva `open` (plegado por defecto).
- [x] 1.3 RED — Test: sin transcripción accesible → cadena vacía (sin sección).
- [x] 1.4 GREEN — `transcriptHtml()` envuelve el texto en `<details>/<summary>`; conserva `aria-label` y clases.
- [x] 1.5 No-regresión: el feed y `resolveVideo()` (#214/#240) intactos; `just test` verde.

## 2. Estilo y accesibilidad

- [x] 2.1 CSS de `.episode-transcript`/`<summary>` (marcador, foco visible, espaciado), párrafos legibles.
- [x] 2.2 Test/verificación de que el `<summary>` es operable por teclado (nativo) y tiene texto visible.

## 3. Cierre

- [x] 3.1 `just test` completo EXIT 0; `just php-lint-changed` EXIT 0; phpcs PSR12 sin errores.
- [x] 3.2 `openspec validate episode-transcript-collapse --strict` (válido; INFO de archivado esperado).
- [x] 3.3 Doc `docs/episode-transcript-collapse.md`.
- [x] 3.4 Release v1.30.0 → deploy de 4 ficheros (`sha256` + `owner=82:82`, cache `fastcgi` purgada) → verificación en producción de `https://atareao.es/podcast/github-me-cerro-el-grifo-me-llevo-todo-a-gitea/`: `<details class="episode-transcript">` con `<summary>` como primer hijo y sin `open`, texto presente (238 `<p>`), CSS `?ver=1.30.0` con `.episode-transcript[open]`, no-regresión #240 y feed intacto (850 items / 850 `<podcast:transcript>`).
- [x] 3.5 Dependencia resuelta: `podcast-feed-soberania` se archivó en el PR #249, así que este change ya puede archivarse.
