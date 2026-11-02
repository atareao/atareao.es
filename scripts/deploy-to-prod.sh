#!/usr/bin/env bash
#
# deploy-to-prod.sh — Despliegue quirúrgico de ficheros al volumen de producción.
#
# CONTRATO
#   deploy-to-prod.sh --host <host> <local>=<ruta-relativa> [<local>=<ruta-relativa> ...]
#
#   - `<local>` es un fichero del working tree (origen).
#   - `<ruta-relativa>` es relativa a la raíz WP; el destino remoto es
#     $WP_ROOT/<ruta-relativa> (WP_ROOT = DEPLOY_WP_ROOT, por defecto /var/www/html).
#   - Host: `--host <host>` o DEPLOY_HOST.
#   - Contenedor WP-CLI: DEPLOY_CLI_CONTAINER (por defecto `wpcli`).
#   - Propietario: DEPLOY_OWNER (por defecto `82:82`). Modo: DEPLOY_MODE (por defecto `644`).
#
# MECANISMO (todo el trabajo remoto pasa por `ssh`; NO se usa `docker cp` ni `scp`)
#   1. Backup si existe:   ssh <host> "docker exec <cli> sh -c 'test -e <dest> && cp -p <dest> <dest>.bak.<ts>' || true"
#      Va ANTES de copiar: conserva el contenido PREVIO, no el recién desplegado.
#   2. Copia por stdin:    ssh <host> "docker exec -i <cli> sh -c 'cat > <dest>'" < <local>
#      Evita el staging en el host y el `docker cp`, que filtra el uid del host.
#   3. Propiedad y modo:   ssh <host> "docker exec <cli> chown <owner> <dest>" y `chmod <mode> <dest>`
#   4. Integridad sha256:  hash local vs. hash remoto; si difieren -> exit 1.
#   5. Propiedad final:    ssh <host> "docker exec <cli> stat -c '%u:%g' <dest>"; si != owner -> exit 1.
#
# POR QUÉ LA PROPIEDAD 82:82
#   WP-CLI corre como uid 82 dentro del contenedor. El procedimiento manual
#   (`docker cp` + `chown` a mano) dejó ficheros con uid 1000 (uid del host
#   filtrado por `docker cp`), y WP-CLI falló con `chmod(): Operation not permitted`.
#   Este script normaliza SIEMPRE el propietario a 82:82 (configurable) y verifica
#   el hash para no dejar copias parciales.
#
# Salida: una línea por fichero `OK <dest> sha256=<hash> owner=82:82`; exit 0 si todo cuadra.
set -euo pipefail

WP_ROOT="${DEPLOY_WP_ROOT:-/var/www/html}"
HOST="${DEPLOY_HOST:-}"
CLI="${DEPLOY_CLI_CONTAINER:-wpcli}"
OWNER="${DEPLOY_OWNER:-82:82}"
MODE="${DEPLOY_MODE:-644}"

usage() {
  cat <<'EOF'
Uso: deploy-to-prod.sh --host <host> <local>=<ruta-relativa> [<local>=<ruta-relativa> ...]

Despliega quirúrgicamente ficheros al volumen de producción. Normaliza la
propiedad a DEPLOY_OWNER (82:82 por defecto) y verifica el sha256 antes de dar
por bueno cada fichero.

Variables de entorno:
  DEPLOY_HOST           host SSH (alternativa a --host)
  DEPLOY_WP_ROOT        raíz WP remota (por defecto /var/www/html)
  DEPLOY_CLI_CONTAINER  contenedor WP-CLI (por defecto wpcli)
  DEPLOY_OWNER          propietario remoto (por defecto 82:82)
  DEPLOY_MODE           modo remoto (por defecto 644)
EOF
}

files=()
while [[ $# -gt 0 ]]; do
  case "$1" in
    --host)
      if [[ $# -lt 2 ]]; then
        echo "ERROR: --host requiere un valor" >&2
        usage >&2
        exit 2
      fi
      HOST="$2"
      shift 2
      ;;
    --host=*)
      HOST="${1#--host=}"
      shift
      ;;
    -h | --help)
      usage
      exit 0
      ;;
    *)
      files+=("$1")
      shift
      ;;
  esac
done

if [[ -z "$HOST" ]]; then
  echo "ERROR: falta el host (usa --host <host> o DEPLOY_HOST)" >&2
  usage >&2
  exit 2
fi

if [[ ${#files[@]} -eq 0 ]]; then
  echo "ERROR: faltan pares <local>=<ruta-relativa>" >&2
  usage >&2
  exit 2
fi

TS=$(date +%Y%m%d%H%M%S)

for spec in "${files[@]}"; do
  if [[ "$spec" != *=* ]]; then
    echo "ERROR: argumento inválido '$spec' (se espera <local>=<ruta-relativa>)" >&2
    exit 2
  fi

  local_file="${spec%%=*}"
  rel="${spec#*=}"
  dest="$WP_ROOT/$rel"

  if [[ ! -f "$local_file" ]]; then
    echo "ERROR: no existe el fichero local '$local_file'" >&2
    exit 1
  fi

  # 1. Backup con marca de tiempo ANTES de sobrescribir (solo si el destino existe):
  #    conserva el contenido PREVIO, no el recién desplegado.
  ssh "$HOST" "docker exec $CLI sh -c 'test -e $dest && cp -p $dest $dest.bak.$TS' || true"

  # 2. Copia por stdin (sin docker cp, sin staging).
  ssh "$HOST" "docker exec -i $CLI sh -c 'cat > $dest'" < "$local_file"

  # 3. Propiedad y modo.
  ssh "$HOST" "docker exec $CLI chown $OWNER $dest"
  ssh "$HOST" "docker exec $CLI chmod $MODE $dest"

  # 4. Integridad sha256.
  local_hash=$(sha256sum "$local_file" | awk '{print $1}')
  remote_hash=$(ssh "$HOST" "docker exec $CLI sha256sum $dest" | awk '{print $1}')
  if [[ "$local_hash" != "$remote_hash" ]]; then
    echo "ERROR: hash discrepante para '$dest'" >&2
    echo "       local=$local_hash remoto=$remote_hash (origen '$local_file')" >&2
    echo "       La copia remota no coincide: revisa la conexión y reintenta." >&2
    exit 1
  fi

  # 5. Propiedad final.
  remote_owner=$(ssh "$HOST" "docker exec $CLI stat -c '%u:%g' $dest")
  if [[ "$remote_owner" != "$OWNER" ]]; then
    echo "ERROR: propiedad incorrecta para '$dest' (obtenido=$remote_owner esperado=$OWNER)" >&2
    echo "       WP-CLI corre como uid 82; el chown no surtió efecto." >&2
    exit 1
  fi

  echo "OK $dest sha256=$local_hash owner=$OWNER"
done

exit 0
