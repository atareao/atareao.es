# Tasks: Interfaz visible de los comandos WP-CLI (`@synopsis`)

## RED

- [x] Guardia en el arnés procedural: lista verificada `comando → callback → synopsis` y comprobación de
      que (a) el plugin registra ese comando con ese callback, (b) el docblock del callback contiene el
      `@synopsis` esperado, (c) los flags del synopsis coinciden con los leídos en el cuerpo
      (`$assoc['…']`). Confirmar que **falla hoy** (ningún callback tiene `@synopsis`).
- [ ] Reproducir en producción (read-only): `wp atareao podcast-duration-backfill --help` → hoy **sin flags**.

## GREEN

- [x] Añadir `@synopsis` al docblock de cada callback con interfaz (7 comandos; `list-bindings` y
      `podcast-audio-length` no lo requieren por no aceptar args/flags). Sin tocar la lógica.
- [x] Todo verde: `just test`, `just php-lint`.

## REFACTOR

- [x] Dejar la lista verificada como única fuente de verdad y documentar el hecho (WP-CLI resuelve la
      ayuda desde el docblock del callback), al estilo de `verified-hooks.php`.
- [x] Re-ejecutar la suite.

## VERIFICACIÓN EN PRODUCCIÓN

- [ ] Tras el deploy: `wp atareao podcast-duration-backfill --help` (y los demás) listan sus flags,
      incluido `--dry-run`.

## ARCHIVE

- [x] `openspec archive 2026-11-21-wp-cli-synopsis`.
