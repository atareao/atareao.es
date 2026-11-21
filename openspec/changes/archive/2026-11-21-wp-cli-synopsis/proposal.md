# Proposal: La interfaz de los comandos WP-CLI del plugin se ve en `--help`

## Why

El plugin registra **8 comandos** con `WP_CLI::add_command` y **ninguno** declara `@synopsis` (ni tags
de parámetros). Por eso `wp atareao <comando> --help` muestra el nombre y la descripción pero **no los
flags**. El que importa es el de seguridad, **`--dry-run`**: un operador que consulta la ayuda antes de
ejecutar un comando que escribe cientos de filas no lo ve, y tiene que leer el README o el código.

## What Changes

1. Añadir **`@synopsis`** al docblock de **cada** callback registrado con `WP_CLI::add_command`, con
   los argumentos posicionales y los flags que el callback **lee de verdad** en su cuerpo. Los comandos
   sin argumentos ni flags no requieren `@synopsis`.
2. **Guardia anti-deriva:** un test con la lista verificada `comando → synopsis` (estilo
   `t10-residuals/verified-hooks.php`) que falla si (a) un callback registrado carece del `@synopsis`
   esperado, (b) el `@synopsis` omite un flag que el callback lee (`$assoc['…']`), o (c) anuncia un flag
   que no lee.
3. **Sin cambios de comportamiento:** solo docblocks (y el test). Ningún comando cambia lo que hace.

## Capabilities

### New Capabilities

- **`wp-cli-help`**: contrato de que la interfaz de los comandos WP-CLI del plugin es visible en `--help`.

### Modified Capabilities

_(ninguna)_

## Impact

- **Código (solo docblocks):** `wp-content/plugins/atareao-functionality/includes/class-author-canonical.php`,
  `class-pocketid-login.php`, `class-podcast-audio.php`, `class-podcast-duration.php`,
  `class-podcast-feed.php`, `class-podcast-guid.php`.
- **Tests:** nueva guardia en el arnés procedural (`tests/`) + encaje en `just test` / CI.
- **Spec:** `openspec/specs/wp-cli-help/spec.md`.
- **Verificación en producción:** `wp atareao podcast-duration-backfill --help` (y los demás comandos)
  listan sus flags, incluido `--dry-run`.

## Comandos y flags (mapa de referencia)

| Comando registrado | Callback | Interfaz |
| --- | --- | --- |
| `atareao-author canonicalize` | `cliCanonicalize` | `[--dry-run]` |
| `atareao-pocketid bind-sub` | `cliBindSub` | `<user> <sub>` |
| `atareao-pocketid list-bindings` | `cliListBindings` | — |
| `atareao podcast-audio-sync` | `cliSync` | `[--dry-run] [--missing] [--limit=<n>] [--format=<table\|json>]` |
| `atareao podcast-duration-backfill` | `cliBackfill` | `[--dry-run] [--limit=<n>] [--source=<ruta>]` |
| `atareao podcast-audio-length` | `cliWarmAudioLengths` | — |
| `atareao podcast-guid-map` | `cliMap` | `[--dry-run] [--limit=<n>]` |
| `atareao podcast-guid-import-missing` | `cliImportMissing` | `[--dry-run] [--author=<id>]` |
