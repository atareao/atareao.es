# Git Flow

Este proyecto sigue **Git Flow** con versionado semántico automático.

## Ramas

| Rama | Propósito | Base |
|---|---|---|
| `main` | Producción. Cada merge aquí dispara una release automática. | — |
| `development` | Integración de features en curso. | `main` |
| `feature/*` | Nuevas funcionalidades. | `development` |
| `hotfix/*` | Correcciones urgentes a producción. | `main` |

## Flujo diario

### Features

```bash
git checkout development && git pull origin development
git checkout -b feature/mi-feature
git commit -m "✨ feat: add dark mode toggle"
git push origin feature/mi-feature
# Crear Pull Request en GitHub
```

### Hotfixes

```bash
git checkout main && git pull origin main
git checkout -b hotfix/arreglo-critico
git commit -m "🚑️ hotfix: crash on empty input"
git push origin hotfix/arreglo-critico
# Crear Pull Request en GitHub
# Después del merge, sincronizar development
git checkout development && git merge main && git push origin development
```

### Releases

```bash
# PR: development → main
# Al mergear, CI automáticamente:
#   a) Detecta bump type según conventional commits
#   b) vampus actualiza versión
#   c) git-cliff genera CHANGELOG.md
#   d) Crea tag vX.Y.Z
#   e) GitHub Actions compila y publica
```

## Conventional Commits con Gitmoji

El formato determina el bump automático:

| Mensaje | Bump |
|---|---|
| `✨ feat: ...` | minor (0.Y.0) |
| `🐛 fix: ...` | patch (0.0.Z) |
| `♻️ refactor: ...` | patch (0.0.Z) |
| `📝 docs: ...` | patch (0.0.Z) |
| `💥 feat!: ...` o `BREAKING CHANGE` | major (X.0.0) |

### Gitmoji de referencia

| Tipo | Emoji |
|---|---|
| feat | ✨ |
| fix | 🐛 |
| hotfix | 🚑️ |
| docs | 📝 |
| refactor | ♻️ |
| perf | ⚡ |
| style | 💄 |
| test | ✅ |
| chore | 🔧 |
| ci | 👷 |
| revert | ⏪️ |
| deps | ⬆️ |
| breaking | 💥 |

## CI Workflows

- **`ci.yml`** — PR a main/development: php-lint
- **`release-prepare.yml`** — push a main: bump version, changelog, tag, sincroniza development
- **`release.yml`** — tag v*: compila zips de tema y plugin, y crea GitHub Release

## Secretos de GitHub necesarios

| Secreto | Propósito |
|---|---|
| `GH_PAT` | Personal Access Token con permisos `Contents: read/write` y `Pull requests: read/write`. Es imprescindible porque los pushes hechos con el `GITHUB_TOKEN` efímero no disparan workflows: el push del tag `vX.Y.Z` debe encadenar `release.yml` (y el PR de sync debe disparar el CI). |

El pipeline de release (`release-prepare.yml`) valida el `GH_PAT` al arrancar, antes de cualquier checkout, y falla rápido si el secreto falta o no autentica (HTTP 401/404), indicando cómo regenerarlo con `gh secret set GH_PAT`.
