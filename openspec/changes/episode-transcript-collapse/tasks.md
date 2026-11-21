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
- [ ] 3.4 Release → deploy → verificación en producción (plegado + texto presente) → cierre de #246.
- [ ] 3.5 **Requiere** `podcast-feed-soberania` archivado antes para poder archivar este change.
