# Proposal: Integración de Google Search Console (`just gsc`)

## Why

Hoy no existe ninguna forma de consultar Google Search Console de `https://atareao.es/` desde el repositorio: no hay herramienta, ni receta, ni integración. Las decisiones de contenido (qué artículo reescribir, qué canibaliza qué, dónde hay visibilidad que no se convierte) se toman mirando la interfaz web a mano, sin repetibilidad y sin dejar rastro en el proyecto.

Ya existe una service account de GSC con permiso `siteFullUser` sobre la propiedad (su correo sigue la forma `searchconsole-reader@<PROYECTO>.iam.gserviceaccount.com`; el identificador real de proyecto no se reproduce aquí), y se ha validado el acceso con un script temporal (`/tmp/opencode/gsc/`) que autentica con JWT RS256 contra la Search Console API. Falta convertirlo en herramienta del repositorio.

Además, la auditoría de sitemaps ha destapado un problema real y verificable: `sitemap.xml` y `sitemap_index.xml` son **contenido idéntico** (mismo MD5 `fc94f1a1c9eccc68d4b620adb8fd364b`, 3.179 URLs, los mismos 143 warnings) y `sitemap_index.xml` **no es un índice** (`isSitemapsIndex: false`, cero etiquetas `<sitemap>`). Los dos están enviados a GSC, de modo que los 143 warnings se cuentan y se mantienen duplicados. No hay ninguna herramienta que detecte esta clase de incoherencia.

Finalmente, el proyecto ya cuenta con `crypta` como gestor de secretos (se usa para `atareao_wordpress_db_password`, `atareao_pocketid_api_key`, etc.), por lo que la credencial de GSC tiene una vía natural y ya establecida para no acabar versionada.

## What Changes

- **Nueva herramienta `scripts/gsc/`.** Cliente de solo lectura de la Search Console API: resolución de credenciales, firma JWT RS256, transporte HTTP y dos consultas (`searchanalytics.query` y `sitemaps.list`). Más un script de informes con subcomandos.
- **Nueva receta `just gsc`.** Receta de passthrough con subcomandos: `resumen`, `queries`, `paginas`, `sitemaps`, `oportunidades` y `compara`, con `--rows`, `--json` y `--out`. Se añaden recetas nuevas; **no se modifica ninguna receta existente**.
- **Credencial gestionada con `crypta`.** La service account se almacena con `crypta store atareao_gsc_service_account` (valor por stdin) y se lee con `crypta lookup`. La credencial **nunca** entra al repositorio ni viaja por argumentos de línea de comandos (visibles en `ps`). Ámbito limitado a `https://www.googleapis.com/auth/webmasters.readonly`.
- **Informe de oportunidades.** Cruce de impresiones altas, posición baja y CTR bajo para ordenar candidatos a mejora por impacto potencial, más una comparativa entre el periodo actual y el anterior.
- **Auditoría de sitemaps.** El subcomando `sitemaps` lista lo enviado a GSC y avisa de sitemaps duplicados (misma huella), ficheros cuyo nombre promete un índice que no lo es, errores y warnings. Descarga como mucho los ficheros XML declarados: **nunca** rastrea las 3.179 URLs.
- **Ejecución periódica desatendida.** Unidades `systemd/atareao-gsc-opportunities.{service,timer}` (semanal, lunes 07:00, `Persistent=true`) y la receta `just gsc-timer install|uninstall|status|logs|run` que las enlaza en `~/.config/systemd/user/`. La salida del informe queda **fuera del repositorio**, en `$HOME/.local/share/atareao/gsc/` (ruta fija, no derivada de `XDG_DATA_HOME`, para que instalador y unidad coincidan siempre).
- **Cero dependencias externas de Python.** Se usa la biblioteca estándar más `openssl` para la firma, coherente con `scripts/podcast-transcripts/transcribe.py`, que ya es stdlib-only y sin `requirements.txt`. Tests con `unittest` y transporte HTTP inyectable, sin red.
- **Sin efectos sobre el sitio ni sobre GSC.** La integración es exclusivamente de lectura: no escribe en Search Console, no altera el sitemap, no toca el theme ni el plugin, y no cambia nada de lo que ve un visitante.

## Capabilities

### New Capabilities

- `gsc-reporting`: consulta de solo lectura de Google Search Console desde el repositorio, con la credencial resuelta desde `crypta`, la receta `just gsc` con subcomandos (resumen, queries, páginas, sitemaps, oportunidades y comparativa), el informe de oportunidades y la auditoría de sitemaps, y su ejecución periódica desatendida mediante un timer de systemd.

### Modified Capabilities

Ninguna.

## Impact

- **Ficheros nuevos (solo en la fase de implementación, tras aprobación):**
  - `scripts/gsc/gsc.py` — cliente de la Search Console API (credenciales, JWT RS256, transporte, consultas).
  - `scripts/gsc/report.py` — subcomandos e informes.
  - `tests/gsc/test_gsc.py` — pruebas `unittest` con transporte falso y fixtures grabados.
  - `systemd/atareao-gsc-opportunities.service` y `systemd/atareao-gsc-opportunities.timer`.
- **Ficheros modificados:**
  - `.justfile` — se añaden las recetas `gsc`, `test-gsc` y `gsc-timer`. Las recetas existentes no se tocan.
- **Specs al archivar:** nueva `openspec/specs/gsc-reporting/spec.md` a partir del delta `specs/gsc-reporting/spec.md`.
- **Lo que no cambia:** theme, plugin, contenido, quadlets, nginx, base de datos y el sitio público. La Search Console no recibe ninguna escritura.
- **Dependencias:** ninguna nueva. Se usan `python3`, `openssl` y `crypta`, ya presentes en el host.
- **Documentación:** `docs/gsc-reporting.md` (el directorio `docs/` está en `.gitignore`, por lo que queda local).
