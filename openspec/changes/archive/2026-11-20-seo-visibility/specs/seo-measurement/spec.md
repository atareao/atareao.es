# seo-measurement — Delta

## Purpose

El protocolo con el que se mide el efecto de este change sobre el CTR: comparar, a 30 días vista, el
rendimiento por página y por consulta contra la baseline registrada en `docs/medicion-baseline.md` §3,
reutilizando el cliente de solo lectura de Google Search Console de la capability `gsc-reporting`.

## ADDED Requirements

### Requirement: Comparación del CTR a 30 días por página y por consulta

Treinta días después de desplegar el change, la medición SHALL comparar el CTR por **página** y por
**consulta** del periodo de 30 días con las cifras de la baseline de `docs/medicion-baseline.md` §3
(CTR global 1,4 %, episodios ~1,3 % y `yt-dlp` 0,2 %). La comparación SHALL realizarse con el cliente
de solo lectura de Search Console de la capability `gsc-reporting` (`just gsc`, con
`searchanalytics.query` por página y por consulta), sin introducir una credencial nueva, y SHALL
quedar registrada por escrito.

#### Scenario: Extracción por página y por consulta

- **WHEN** se ejecuta la comparación de 30 días
- **THEN** se obtienen filas de páginas y de consultas del periodo, con clics, impresiones y CTR

#### Scenario: Comparación contra la baseline

- **WHEN** se contrasta el resultado con `docs/medicion-baseline.md` §3
- **THEN** se comparan el CTR global, el de los episodios y el de `yt-dlp` con sus valores de referencia

#### Scenario: Se reutiliza el cliente existente

- **WHEN** se consulta Search Console para la medición
- **THEN** se usa el cliente de solo lectura de `gsc-reporting` y la credencial ya existente, sin una nueva

#### Scenario: El resultado queda por escrito

- **WHEN** se completa la comparación
- **THEN** el resultado se registra en un documento de `docs/` con la fecha del periodo medido
