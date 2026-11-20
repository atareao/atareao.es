# PLAN-003 — Integración de Google Search Console (`just gsc`)

> Checkpoint del Director · Creado 2026-11-14 · Estado: **IMPLEMENTADO, AUDITADO — propuesta de integración en curso**
> Issue: [#183](https://github.com/atareao/atareao.es/issues/183) · Change OpenSpec: `openspec/changes/gsc-reporting/` · Rama: `feature/gsc-reporting`
> Regla dura: ninguna casilla se marca sin evidencia CLI pegada literalmente.
> Temporales en `/tmp/opencode`. Nunca editar el core de WordPress en los contenedores.

## 0. Objetivo

Consultar Google Search Console de `https://atareao.es/` desde la terminal, con datos accionables, sin abrir la UI y sin que ninguna credencial entre al repositorio. Tres entregas:

- **(a)** herramienta `scripts/gsc/` + receta `just gsc` con subcomandos.
- **(b)** diagnóstico de los 143 warnings del sitemap + detección automática de sitemaps duplicados.
- **(c)** informe de oportunidades + ejecución periódica desatendida vía timer de systemd.

## 1. Diagnóstico previo (b) — evidencia recogida (2026-11-14)

Acceso validado con un script temporal en `/tmp/opencode/gsc/`. Service account `searchconsole-reader@<PROYECTO>.iam.gserviceaccount.com`, permiso `siteFullUser` sobre `https://atareao.es/`.

| Comprobación | Evidencia |
| --- | --- |
| Propiedad accesible | `webmasters/v3/sites` → 200, `https://atareao.es/` |
| Resumen 28d | 4.935 clics · 393.500 impresiones · CTR 1,25 % · posición media 7,0 |
| Sitemap: URLs | 3.179 (0 duplicadas, 0 con query, 0 sin barra final, 0 mayúsculas, 0 attachments) |
| Sitemap: huella | `sitemap.xml` y `sitemap_index.xml` → **mismo MD5 `fc94f1a1c9eccc68d4b620adb8fd364b`** |
| `sitemap_index.xml` | `isSitemapsIndex: false`, **0** etiquetas `<sitemap>` ⇒ no es un índice |
| robots.txt | declara solo `https://atareao.es/sitemap.xml` |
| API sitemaps | ambos: `errors 0`, `warnings 143`, `contents.indexed 0` |
| Muestra 200 URLs (canonical/noindex) | 200/200 → 200 OK, self-canonical, indexables |
| Muestra 120 URLs (status) | 106×200 y 14×503; los 503 eran **rate-limit de mi propio `curl -P 24`**, en serie → 200 |
| Cabeceras del sitemap | `x-cache-status: BYPASS`, 463 KB |
| Generador | The SEO Framework (vía `class-seo.php` del plugin) |

Conclusiones:

1. **El contenido del sitemap no está roto**: los warnings no provienen de URLs inaccesibles, no canónicas ni `noindex`.
2. **Sí hay duplicidad real**: dos sitemaps con contenido idéntico enviados a GSC ⇒ los 143 warnings se cuentan dos veces.
3. El desglose de los 143 warnings **solo existe en la UI** de GSC (la API no lo expone) ⇒ verificación manual del usuario.
4. Cualquier subcomando que toque el sitio **no debe rastrear 3.179 URLs**: el rate-limit del WAF devuelve 503.

## 2. Change proposal

`openspec/changes/gsc-reporting/` con capability nueva `gsc-reporting`. Validado: `openspec validate gsc-reporting` → **valid**, 4/4 artefactos. Estado: pendiente de aprobación.

## 3. Diseño

### 3.1 Piezas

| Pieza | Ruta | Responsabilidad |
| --- | --- | --- |
| Cliente GSC | `scripts/gsc/gsc.py` | credenciales, JWT RS256, transporte HTTP, `searchanalytics.query`, `sitemaps.list` |
| Informes | `scripts/gsc/report.py` | subcomandos, formateo de tablas, `--json` |
| Tests | `tests/gsc/test_gsc.py` | `unittest` + `unittest.mock`, sin red |
| Receta | `.justfile` → `gsc *args` | passthrough a `report.py` |
| Timer | `systemd/atareao-gsc-opportunities.service` y `.timer` | ejecución semanal desatendida |
| Receta timer | `.justfile` → `gsc-timer install\|status\|logs\|stop` | enlaza y habilita el timer |

### 3.2 Credencial

- Alta: `crypta store atareao_gsc_service_account < <RUTA-DEL-JSON-DE-LA-SERVICE-ACCOUNT>` (valor por stdin).
- Lectura: `crypta lookup atareao_gsc_service_account` (no interactivo; verificado).
- **Nunca** por argumento de línea de comandos (visible en `ps`).
- Ámbito: solo `https://www.googleapis.com/auth/webmasters.readonly`.
- El JSON de la service account **nunca** entra al repositorio.

### 3.3 Firma JWT sin dependencias

El repo no tiene `requirements.txt` y `scripts/podcast-transcripts/transcribe.py` es stdlib-only. Decisión: **stdlib + `openssl`** (`openssl dgst -sha256 -sign`), presente en el host. Nada de `cryptography` ni `requests`. La clave privada se pasa a `openssl` por **stdin**, nunca por fichero temporal.

### 3.4 Subcomandos (`just gsc …`)

| Comando | Salida |
| --- | --- |
| `resumen [días=28]` | clics, impresiones, CTR, posición media, rango de fechas |
| `queries [días] [n=15]` | top queries por clics |
| `paginas [días] [n=15]` | top páginas por clics |
| `sitemaps` | sitemaps enviados + aviso de duplicados / no-índice / errores / warnings |
| `oportunidades [días] [n=20]` | impresiones altas × posición baja × CTR bajo |
| `compara [días=28]` | periodo actual vs periodo anterior |

Flags comunes: `--rows N`, `--json`, `--out FICHERO`.

### 3.5 Timer

- `.service` (`Type=oneshot`) ejecuta `python3 scripts/gsc/report.py oportunidades --out …`.
- `.timer` semanal (lunes 07:00, `Persistent=true`).
- Salida en `${XDG_DATA_HOME:-~/.local/share}/atareao/gsc/oportunidades-<fecha>.md` — **fuera del repo**.
- Los quadlets (`~/.config/containers/systemd`) solo admiten unidades de contenedor; por eso las unidades van al directorio nuevo `systemd/`, que `just gsc-timer install` enlaza a `~/.config/systemd/user/`.

## 4. Tareas (TDD)

- [x] 4.1 Dar de alta la service account en `crypta` como `atareao_gsc_service_account` (valor por stdin) y verificar su lectura con `crypta lookup`.
- [x] 4.2 RED: `tests/gsc/test_gsc.py` con fixtures de payloads grabados y transporte HTTP falso.
- [x] 4.3 GREEN: `scripts/gsc/gsc.py` (credenciales, JWT RS256, transporte, query, sitemaps) con el mínimo código que pase los tests.
- [x] 4.4 GREEN: `scripts/gsc/report.py` (subcomandos, tablas, `--json`, `--out`).
- [x] 4.5 Recetas `just gsc *args` y `just test-gsc` en `.justfile` (sin tocar recetas existentes).
- [x] 4.6 Unidades `systemd/atareao-gsc-opportunities.{service,timer}` + receta `just gsc-timer`.
- [x] 4.7 REFACTOR: `python3 -m unittest discover -s tests/gsc -v` en verde, `just test` intacto (1208 passed, 0 failed), `just php-lint` limpio.
- [x] 4.8 Verificación anti-fuga: `git grep` de clave privada, `client_email` y `private_key_id` en el árbol versionado → 0 coincidencias.
- [x] 4.9 Evidencia de `just gsc resumen`, `just gsc sitemaps` y `just gsc oportunidades` pegada literalmente.
- [x] 4.10 `docs/gsc-reporting.md` (docs/ está en .gitignore).
- [ ] 4.11 Verificación manual del desglose de los 143 warnings en la UI de GSC (la API no lo expone).

## 5. Criterios de aceptación

1. `just gsc resumen` devuelve clics/impresiones/CTR/posición del periodo.
2. `just gsc oportunidades` lista queries de alto volumen y baja posición, ordenadas por impacto potencial.
3. Ninguna credencial en el repositorio: la service account se resuelve vía `crypta lookup`.
4. `just gsc sitemaps` marca `sitemap_index.xml` como duplicado y no-índice.
5. `just test` sigue en verde y `just php-lint` sin errores.
6. El timer deja el informe de oportunidades en un fichero localizado de forma explícita.

## 6. Riesgos

| Riesgo | Mitigación |
| --- | --- |
| `crypta` deja de ser no interactivo | El script falla con mensaje explícito; nunca pide entrada por teclado |
| `openssl` ausente en el host | Comprobación al arrancar con error claro |
| Cuota de la API de GSC | Solo lectura y bajo demanda; el timer hace 2 consultas por semana |
| El WAF del sitio devuelve 503 ante rastreo | El subcomando `sitemaps` solo descarga 2 ficheros XML, nunca 3.179 URLs |
| Los 143 warnings no se resuelven sin UI | Se documenta como verificación manual y no se da por cerrado el punto (b) |

## 7. Auditoría y cierre

**Veredicto: 0 hallazgos bloqueantes.** Auditoría independiente sobre la implementación ya verificada.

**Huecos de prueba destapados por mutaciones (I2, I3, I4).** Tres mutaciones sobre la lógica de oportunidades, la detección de duplicados y la heurística de índice no hacían fallar la batería. Se añadieron las pruebas que faltaban; hoy las tres mutaciones hacen fallar la suite.

**Resto de hallazgos cerrados.** I1 (identificadores reales de la service account en la documentación → retirados), M1 (huella del sitemap unificada), M2/M3 (unidad de systemd y su comentario), M4 (la receta `gsc` aborta ante fallo intermedio), M5 (`proposal` alineado con las acciones reales), M6 (heurística del índice acotada) y O3 (validación de `--rows`).

**Aceptados sin acción.** O2 (`crypta` con stdin heredado, ya asumido en el diseño), O4 (argumentos sin comillas en la receta `gsc`, documentado como follow-up) y O5 (pruebas de firma que se saltan si falta `openssl`).

**No verificable por código.** El desglose de los 143 warnings del sitemap solo existe en la interfaz de Search Console; la API no lo expone. Queda como verificación manual del usuario (tarea 8.6).

**Defectos reales cazados durante la implementación.**

| Defecto | Síntoma | Causa |
| --- | --- | --- |
| Ruta `searchAnalytics/query` en minúscula | 404 | La API distingue mayúsculas en el segmento del método |
| `Content-Type` ausente en la petición | 400 | El cuerpo JSON se enviaba sin cabecera de tipo |
| `resolve_out_path` ignoraba el directorio | Fichero en el destino equivocado | Un test que consolidaba el error lo cazó al fijar la ruta esperada |
