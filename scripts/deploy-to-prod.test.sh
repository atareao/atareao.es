#!/usr/bin/env bash
#
# Runner de pruebas para scripts/deploy-to-prod.sh
#
# El repositorio no tiene framework de tests. Este script verifica el contrato
# del despliegue quirúrgico a producción sin tocar producción ni ejecutar `ssh`
# real: antepone al PATH un stub ejecutable `ssh` que emula un "remoto falso"
# en un directorio de estado ($FAKE_REMOTE).
#
#   - Imprime PASS/FAIL por caso y acumula total/passed/failed.
#   - NO se aborta al primer fallo: sigue hasta el final.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#   - Deriva sus rutas de ${BASH_SOURCE[0]}: puede ejecutarse desde cualquier
#     CWD (raíz del repo, /tmp, runner de CI).
#
# Casos (uno por escenario de la spec):
#   a) despliegue correcto: exit 0, owner 82:82 e imprime `OK ...`.
#   b) hash discrepante: exit != 0 y el mensaje cita el fichero.
#   c) propiedad final incorrecta: exit != 0.
#   d) copia de seguridad: si el destino ya existe, queda un `*.bak.*`.
#   e) faltan argumentos: uso + exit != 0.
#
# Uso: bash scripts/deploy-to-prod.test.sh
set -uo pipefail

# Rutas derivadas de la ubicación del propio script, no del CWD.
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT="$SCRIPT_DIR/deploy-to-prod.sh"

total=0
passed=0
failed=0

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

FAKE_REMOTE="$TMP_DIR/remote"
mkdir -p "$FAKE_REMOTE"

STUB_DIR="$TMP_DIR/bin"
mkdir -p "$STUB_DIR"

# Stub de `ssh`: emula el "remoto falso". Interpreta el comando remoto que el
# script le pasa como segundo argumento y traduce las rutas absolutas al árbol
# de $FAKE_REMOTE. Sólo intercepta las primitivas documentadas.
cat > "$STUB_DIR/ssh" <<'STUB'
#!/usr/bin/env bash
# Fake ssh: emula `docker exec ...` sobre un remoto falso en $FAKE_REMOTE.
set -uo pipefail

# ssh <host> <comando-remoto>
shift || true            # descarta el host
cmd="$*"

fake_path() {
  printf '%s%s' "$FAKE_REMOTE" "$1"
}

case "$cmd" in
  *"cat > "*)
    dest=${cmd##*cat > }
    dest=${dest%\'}
    fp=$(fake_path "$dest")
    mkdir -p "$(dirname "$fp")"
    cat > "$fp"
    ;;
  *"cp -p "*)
    # cmd: ... sh -c 'test -e <dest> && cp -p <dest> <dest>.bak.<ts>' || true
    args=${cmd##*cp -p }
    src=${args%% *}
    bak=${args#* }
    bak=${bak%%\'*}
    if [[ -e "$(fake_path "$src")" ]]; then
      cp -p "$(fake_path "$src")" "$(fake_path "$bak")"
    fi
    ;;
  *"chown "*)
    args=${cmd##*chown }
    owner=${args%% *}
    dest=${args##* }
    printf '%s' "$owner" > "$(fake_path "$dest").owner"
    ;;
  *"chmod "*)
    : # no-op: el modo no afecta a las aserciones
    ;;
  *"sha256sum "*)
    dest=${cmd##*sha256sum }
    if [[ -n "${FAKE_HASH_OVERRIDE:-}" ]]; then
      printf '%s  %s\n' "$FAKE_HASH_OVERRIDE" "$dest"
    else
      sha256sum "$(fake_path "$dest")"
    fi
    ;;
  *"stat -c "*)
    # cmd: ... stat -c '%u:%g' <dest>
    args=${cmd##*stat -c }
    dest=${args#* }
    if [[ -n "${FAKE_OWNER_OVERRIDE:-}" ]]; then
      printf '%s\n' "$FAKE_OWNER_OVERRIDE"
    else
      cat "$(fake_path "$dest").owner" 2>/dev/null || true
    fi
    ;;
  *)
    printf 'fake-ssh: comando no soportado: %s\n' "$cmd" >&2
    exit 127
    ;;
esac
STUB
chmod +x "$STUB_DIR/ssh"

PATH="$STUB_DIR:$PATH"
export PATH
export FAKE_REMOTE

OUT=""
CODE=0

pass() { total=$((total + 1)); passed=$((passed + 1)); printf 'PASS: %s\n' "$1"; }
fail() { total=$((total + 1)); failed=$((failed + 1)); printf 'FAIL: %s\n' "$1"; }

# assert_ok <etiqueta> <exit_code>
assert_ok() {
  if [[ "$2" -eq 0 ]]; then pass "$1"; else fail "$1 (exit=$2)"; fi
}

# assert_nonzero <etiqueta> <exit_code>
assert_nonzero() {
  if [[ "$2" -ne 0 ]]; then pass "$1"; else fail "$1 (exit=0)"; fi
}

# assert_output_contains <etiqueta> <salida> <aguja>
assert_output_contains() {
  if [[ "$2" == *"$3"* ]]; then pass "$1"; else fail "$1 (falta '$3' en la salida)"; fi
}

# assert_eq <etiqueta> <esperado> <obtenido>
assert_eq() {
  if [[ "$2" == "$3" ]]; then pass "$1"; else fail "$1 (esperado='$2' obtenido='$3')"; fi
}

# assert_file_equals <etiqueta> <fichero> <contenido> (sin el salto final)
assert_file_equals() {
  local got
  got=$(cat "$2" 2>/dev/null || true)
  if [[ "$got" == "$3" ]]; then pass "$1"; else fail "$1 (esperado='$3' obtenido='$got')"; fi
}

# run_deploy <args...>: ejecuta el script y deja salida y exit code en OUT/CODE.
run_deploy() {
  OUT=$(bash "$SCRIPT" "$@" 2>&1)
  CODE=$?
}

# ---------------------------------------------------------------------------
# (e) Faltan argumentos -> uso + exit != 0.
# ---------------------------------------------------------------------------
run_deploy
assert_nonzero "e) sin argumentos sale != 0" "$CODE"
assert_output_contains "e) imprime uso" "$OUT" "Uso:"

# ---------------------------------------------------------------------------
# (a) Despliegue correcto.
# ---------------------------------------------------------------------------
local_a="$TMP_DIR/class-srt-upload.php"
printf '<?php // correcto\n' > "$local_a"
rel_a="wp-content/plugins/atareao-functionality/includes/class-srt-upload.php"
run_deploy --host fake-host "$local_a=$rel_a"
assert_ok "a) despliegue correcto sale 0" "$CODE"
assert_output_contains "a) imprime línea OK" "$OUT" "OK "
fake_dest_a="$FAKE_REMOTE/var/www/html/$rel_a"
assert_eq "a) owner remoto falso 82:82" "82:82" "$(cat "$fake_dest_a.owner" 2>/dev/null)"

# ---------------------------------------------------------------------------
# (b) Hash discrepante -> exit != 0 y el mensaje cita el fichero.
# ---------------------------------------------------------------------------
local_b="$TMP_DIR/b.php"
printf 'b\n' > "$local_b"
export FAKE_HASH_OVERRIDE="deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef"
run_deploy --host fake-host "$local_b=wp-content/b.php"
unset FAKE_HASH_OVERRIDE
assert_nonzero "b) hash discrepante sale != 0" "$CODE"
assert_output_contains "b) el mensaje cita el fichero" "$OUT" "wp-content/b.php"

# ---------------------------------------------------------------------------
# (c) Propiedad final incorrecta -> exit != 0.
# ---------------------------------------------------------------------------
local_c="$TMP_DIR/c.php"
printf 'c\n' > "$local_c"
export FAKE_OWNER_OVERRIDE="1000:1000"
run_deploy --host fake-host "$local_c=wp-content/c.php"
unset FAKE_OWNER_OVERRIDE
assert_nonzero "c) propiedad final incorrecta sale != 0" "$CODE"
assert_output_contains "c) el mensaje cita el fichero" "$OUT" "wp-content/c.php"

# ---------------------------------------------------------------------------
# (d) Copia de seguridad cuando el destino ya existe.
# ---------------------------------------------------------------------------
local_d="$TMP_DIR/d.php"
printf 'nuevo\n' > "$local_d"
fake_dest_d="$FAKE_REMOTE/var/www/html/wp-content/d.php"
mkdir -p "$(dirname "$fake_dest_d")"
printf 'antiguo\n' > "$fake_dest_d"
run_deploy --host fake-host "$local_d=wp-content/d.php"
assert_ok "d) despliegue con destino preexistente sale 0" "$CODE"
if compgen -G "$fake_dest_d.bak.*" >/dev/null; then
  pass "d) se crea la copia de seguridad *.bak.*"
  bak_d=$(compgen -G "$fake_dest_d.bak.*" | head -n1)
  # El backup debe conservar el contenido PREVIO ('antiguo'), no el desplegado.
  assert_file_equals "d) el backup guarda el contenido previo ('antiguo')" "$bak_d" "antiguo"
  assert_file_equals "d) el destino desplegado contiene 'nuevo'" "$fake_dest_d" "nuevo"
else
  fail "d) no se creó la copia de seguridad *.bak.*"
  fail "d) el backup guarda el contenido previo ('antiguo')"
  fail "d) el destino desplegado contiene 'nuevo'"
fi

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
