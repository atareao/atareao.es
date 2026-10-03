# Design: Validación de credenciales del pipeline de release

## Context

Ver `proposal.md` para la motivación. Estado actual relevante:

- `.github/workflows/release-prepare.yml` tiene dos jobs (`release` y `sync-development`). Ambos arrancan con `actions/checkout@v4` usando `token: ${{ secrets.GH_PAT || secrets.GITHUB_TOKEN }}` y exportan `GH_TOKEN: ${{ secrets.GH_PAT || secrets.GITHUB_TOKEN }}` en los pasos de git/`gh`.
- El job `release` hace bump con vampus, changelog con git-cliff, crea la rama `release/vX.Y.Z`, pushea la rama y el tag `vX.Y.Z`, y abre el PR a `main`. El push del tag debe encadenar `release.yml`.
- El job `sync-development` hace merge de `main` en `development` sobre una rama temporal y abre el PR de sync.
- El repositorio es público. Un PAT inválido no produce un 401 legible en el checkout: git lo trata como acceso anónimo, pide credenciales y aborta con `terminal prompts disabled`.
- Restricción de verificación: no se puede lanzar el happy path con `gh workflow run release-prepare.yml`, porque volvería a bumpear la versión y crear una release espuria.

## Goals / Non-Goals

**Goals**

- Detectar credenciales inválidas, revocadas o ausentes en el primer paso de cada job, antes de tocar el repositorio.
- Distinguir de forma inequívoca los tres fallos típicos (sin PAT, 401, 404) con remediación concreta.
- Garantizar que el valor del token nunca aparezca en los logs.
- Hacer `GH_PAT` un requisito duro y eliminar el fallback silencioso que ocultaba la causa.

**Non-Goals**

- No cambiar la lógica de versionado, changelog, creación de ramas/tags ni PRs.
- No introducir un framework de tests en el repositorio (no existe hoy).
- No tocar `release.yml` ni otros workflows.
- No validar los permisos finos del PAT más allá de que la API del repositorio responda 200 (una comprobación de `push` real es insegura en seco).

## Decisions

### Decisión 1: Validar antes de `actions/checkout@v4`

El error críptico aparece precisamente en el checkout. Si la validación va después, el fallo previo ya ensucia el diagnóstico y reproduce el problema que queremos eliminar. Colocando el paso antes, cualquier problema de credenciales se reporta con el mensaje correcto y el checkout ni siquiera se intenta.

**Alternativa descartada:** validar después del checkout. Es tarde: el checkout es el que falla.

### Decisión 2: Fail-fast (`exit 1`)

Un PAT inválido no es recuperable dentro del run; continuar solo produce errores en cascada (git config, push, `gh pr create`) con mensajes peores. Abortar el job en el primer paso concentra la señal y evita operaciones parciales.

**Alternativa descartada:** `continue-on-error` o warning sin abortar. Permitiría que el job siguiera y fallara más tarde de forma confusa, que es exactamente el comportamiento actual.

### Decisión 3: Descartar el fallback a `GITHUB_TOKEN`

El fallback `secrets.GH_PAT || secrets.GITHUB_TOKEN` es la causa de que el problema sea silencioso. Los pushes hechos con el `GITHUB_TOKEN` efímero **no disparan otros workflows** (restricción documentada de GitHub). Por tanto:

- El push del tag `vX.Y.Z` con `GITHUB_TOKEN` **no** encadenaría `release.yml` (build de zips + GitHub Release).
- El PR de `sync-development` creado con `GITHUB_TOKEN` **no** dispararía el CI.

Es decir, el fallback no es una red de seguridad válida: produce un pipeline que "funciona" pero no cumple su objetivo. Se usa `secrets.GH_PAT` explícito en `token:` y `GH_TOKEN:`.

**Alternativa descartada:** mantener el fallback y solo añadir un warning cuando se use `GITHUB_TOKEN`. No resuelve el encadenado de workflows y deja una vía rota accesible.

### Decisión 4: `GH_PAT` sigue siendo obligatorio (encadenado de workflows)

No es sustituible por `GITHUB_TOKEN` porque el diseño del release depende del efecto "workflow que dispara workflow". El tag debe lanzar `release.yml` y el PR de sync debe lanzar el CI. Solo un PAT (u otro token de app con permisos) dispara workflows en un push/PR. Por eso el pipeline exige `GH_PAT` y falla si no está.

### Decisión 5: `curl` en lugar de `gh api`

Se usa `curl` contra `https://api.github.com/repos/${GITHUB_REPOSITORY}` capturando el código HTTP con `--write-out '%{http_code}'`:

| Criterio | `curl` | `gh api` |
|---|---|---|
| Código HTTP exacto (401 vs 404) | Directo con `%{http_code}` | Requiere `--include` y parsear la respuesta |
| Credenciales | Cabecera explícita `Authorization: Bearer` | Usa `GH_TOKEN` del entorno (circularidad con lo que se valida) |
| Salida en error | Silenciosa con `--silent --output /dev/null` | `gh` imprime su propio mensaje de error en stderr |
| Disponibilidad | Preinstalado en `ubuntu-latest` | Preinstalado, pero añade dependencia de su semántica |

`curl` da una comprobación determinista y neutral del código HTTP, sin depender de la propia capa de autenticación de `gh` que estamos validando. El token se pasa por cabecera y nunca se imprime (`--silent`, sin `-v`, sin `echo`).

**Alternativa descartada:** `gh api repos/${GITHUB_REPOSITORY}`. Funciona, pero distingue peor 401 de 404 y acopla la validación al mismo cliente que luego se usa en el pipeline.

### Decisión 6: No se puede hacer dry-run del happy path

`gh workflow run release-prepare.yml` ejecutaría el workflow completo: bump de versión, changelog y creación de rama/tag/PR. Eso generaría una release espuria, justo lo que el workflow no debe hacer en una prueba. Por eso la verificación del camino feliz se limita a:

1. **Estática:** parseo del YAML y `bash -n` del snippet `run:`.
2. **Local:** ejecución del snippet con un token falso para confirmar el mensaje de 401 y que el token no se filtra.
3. **E2E diferida:** el próximo release real ejercita el camino 200.

No hay forma segura de provocar el 200 en CI sin efectos secundarios, así que se acepta explícitamente esta limitación.

### Decisión 7: No imprimir nunca el valor del token

El paso define `GH_PAT` en `env:` (nunca en la URL ni en `argv` visible), captura solo `%{http_code}` con `--output /dev/null`, y no usa `set -x` ni `curl -v`. El mensaje de log es `GitHub API /repos/<repo> respondió HTTP <code>`. GitHub además enmascara los secretos registrados, pero no se depende de ello.

## Risks / Trade-offs

- **[`curl` no disponible en el runner]** → `curl` está garantizado en imágenes `ubuntu-latest` de GitHub Actions. No se instala nada.
- **[PAT fine-grained sin acceso al repo devuelve 404]** → Es el comportamiento esperado; el mensaje de 404 explica que puede ser falta de acceso/permisos y cómo regenerarlo.
- **[5xx o rate limit de la API]** → El caso `*)` falla con un mensaje de fallo de red/estado de la API; no se confunde con un token inválido.
- **[El paso valida acceso de lectura, no permiso de push]** → Limitación aceptada: un 200 confirma identidad y acceso; un intento de push en seco sería peor. El PR real sigue siendo la validación última de `Contents/Pull requests: write`.
- **[Duplicación del snippet en dos jobs]** → Es la opción correcta en GitHub Actions sin encapsular en una acción compuesta; el coste es mantener dos copias del mismo bloque.

## Migration Plan

1. Editar `.github/workflows/release-prepare.yml`: añadir el paso de validación al inicio de los dos jobs y sustituir los fallbacks por `secrets.GH_PAT`.
2. Verificación estática local (parseo YAML, `bash -n`, snippet con token falso).
3. PR de la rama `feature/ci-release-token-validation` a `development` y merge por gitflow.
4. E2E diferida: se comprueba en el próximo release real que el paso da 200 y el pipeline continúa igual.

**Rollback:** revertir el YAML (`git revert` del commit que toca `release-prepare.yml`). Al revertir se recuperan los fallbacks previos; no hay estado, secretos ni migración de datos implicados. Si el problema fuera un PAT malo, la acción correcta no es revertir sino regenerar el secreto con `gh secret set GH_PAT`.

## Open Questions

Ninguna. Las decisiones de esquema de autenticación y de verificación quedan resueltas arriba.
