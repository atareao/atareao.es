#!/usr/bin/env bash
#
# Runner de pruebas para scripts/deploy-to-prod.sh
#
# El repositorio no tiene framework de tests. Este script verifica el contrato
# del despliegue quirúrgico a producción sin tocar producción ni ejecutar `ssh`
# real: antepone al PATH un stub ejecutable `ssh` (más un `docker` y un `date`
# falsos) que emula un "remoto falso" en un directorio de estado ($FAKE_REMOTE).
#
# El stub de `ssh` ejecuta el comando remoto con `bash -c` y un `docker` falso
# en el PATH, de modo que una inyección de comandos en el host remoto (H1) se
# reproduce de verdad (p. ej. `touch PWNED`) en un sandbox aislado ($SANDBOX).
#
#   - Imprime PASS/FAIL por caso y acumula total/passed/failed.
#   - NO se aborta al primer fallo: sigue hasta el final.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#   - Deriva sus rutas de ${BASH_SOURCE[0]}: puede ejecutarse desde cualquier
#     CWD (raíz del repo, /tmp, runner de CI).
#
# Casos (uno por escenario de la spec):
#   a) despliegue correcto: exit 0, owner final 82:82 e imprime `OK ...`.
#   b) hash discrepante: exit == 1 y mensaje `hash discrepante` citando el fichero.
#   c) propiedad final incorrecta: exit == 1 y mensaje `propiedad incorrecta`.
#   d) copia de seguridad: el `.bak` conserva el contenido PREVIO.
#   e) faltan argumentos: uso + exit != 0.
#   f) path traversal (`../escaped.php`): rechazo y no escribe fuera de WP_ROOT.
#   g) inyección en `rel`: rechazo y NO se crea `PWNED`.
#   h) `rel` absoluta (`/etc/passwd`): rechazo.
#   i) `rel` con espacios: rechazo (charset inválido).
#   j) reejecución rápida: el primer `.bak` conserva el original y hay dos `.bak`.
#   k) contrato CI: el workflow invoca esta suite.
#   l) charset ASCII estricto e independiente del locale (LC_ALL=C): 'café.jpg'
#      se rechaza aunque el entorno invoque con LC_ALL=es_ES.UTF-8.
#   m) DEPLOY_HOST que empieza por '-' se rechaza (ssh lo leería como opción).
#
# Uso: bash scripts/deploy-to-prod.test.sh
set -uo pipefail

# Rutas derivadas de la ubicación del propio script, no del CWD.
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
SCRIPT="$SCRIPT_DIR/deploy-to-prod.sh"
REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
WORKFLOW="$REPO_ROOT/.github/workflows/ci.yml"

total=0
passed=0
failed=0

TMP_DIR=$(mktemp -d)
trap 'rm -rf "$TMP_DIR"' EXIT

FAKE_REMOTE="$TMP_DIR/remote"
mkdir -p "$FAKE_REMOTE"

# Sandbox de trabajo: aquí caerían los efectos de una inyección de comandos.
SANDBOX="$TMP_DIR/cwd"
mkdir -p "$SANDBOX"

STUB_DIR="$TMP_DIR/bin"
mkdir -p "$STUB_DIR"

# Stub de `ssh`: emula el host remoto ejecutando el comando tal cual lo haría
# la shell remota, con un `docker` falso en el PATH. Así una inyección de
# comandos (`rel` con `'` y `;`) se ejecuta de verdad en $SANDBOX.
cat > "$STUB_DIR/ssh" <<'STUB'
#!/usr/bin/env bash
# Fake ssh: emula el shell remoto sobre un remoto falso.
set -uo pipefail

# ssh <host> <comando-remoto>
shift || true            # descarta el host
cmd="$*"
exec bash -c "$cmd"
STUB
chmod +x "$STUB_DIR/ssh"

# Stub de `docker`: emula `docker exec ...` sobre el filesystem local real.
# - `sh -c` se ejecuta de verdad (rutas absolutas de $FAKE_REMOTE, sin traducir).
# - `chown`/`chmod`/`sha256sum`/`stat` se interceptan (owner falso, no-op, hash).
cat > "$STUB_DIR/docker" <<'STUB'
#!/usr/bin/env bash
# Fake docker: emula `docker exec ...` sobre el filesystem local real.
set -uo pipefail

sub="$1"; shift
if [[ "$sub" != "exec" ]]; then
  printf 'fake-docker: sólo se soporta exec: %s\n' "$sub" >&2
  exit 127
fi

# Descarta flags (-i, -t, -u X, --user X, ...) y el nombre del contenedor.
while [[ $# -gt 0 && "$1" == -* ]]; do
  case "$1" in
    -u | --user) shift ;;
  esac
  shift
done
shift

verb="$1"
case "$verb" in
  sh)
    shift 2              # sh -c
    script="$1"
    if [[ "$script" == cat\ \>* ]]; then
      dest="${script#cat > }"
      dest="${dest%\"}"; dest="${dest#\"}"
      mkdir -p "$(dirname "$dest")"
    fi
    exec bash -c "$script"
    ;;
  chown)
    owner="$2"
    dest="${!#}"
    printf '%s' "$owner" > "$dest.owner"
    ;;
  chmod)
    : # no-op: el modo no afecta a las aserciones
    ;;
  sha256sum)
    dest="${!#}"
    if [[ -n "${FAKE_HASH_OVERRIDE:-}" ]]; then
      printf '%s  %s\n' "$FAKE_HASH_OVERRIDE" "$dest"
    else
      sha256sum "$dest"
    fi
    ;;
  stat)
    dest="${!#}"
    if [[ -n "${FAKE_OWNER_OVERRIDE:-}" ]]; then
      printf '%s\n' "$FAKE_OWNER_OVERRIDE"
    else
      cat "$dest.owner" 2>/dev/null || true
    fi
    ;;
  *)
    printf 'fake-docker: comando no soportado: %s\n' "$verb" >&2
    exit 127
    ;;
esac
STUB
chmod +x "$STUB_DIR/docker"

# Stub de `date`: valor constante. Fuerza la colisión de TS del H3 en el RED
# (una sola marca por segundo) y sigue permitiendo unicidad con `-$$` tras el
# arreglo.
cat > "$STUB_DIR/date" <<'STUB'
#!/usr/bin/env bash
printf '1700000000\n'
STUB
chmod +x "$STUB_DIR/date"

PATH="$STUB_DIR:$PATH"
export PATH
export FAKE_REMOTE
# La raíz WP apunta al árbol del doble: así el "remoto" es un directorio local.
export DEPLOY_WP_ROOT="$FAKE_REMOTE/var/www/html"

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

# assert_missing <etiqueta> <ruta>: la ruta NO debe existir.
assert_missing() {
  if [[ ! -e "$2" ]]; then pass "$1"; else fail "$1 (existe '$2')"; fi
}

# run_deploy <args...>: ejecuta el script desde el sandbox y deja salida y exit
# code en OUT/CODE. El sandbox recoge los efectos de una eventual inyección.
run_deploy() {
  OUT=$(cd "$SANDBOX" && bash "$SCRIPT" "$@" 2>&1)
  CODE=$?
}

# run_deploy_lc <locale> <args...>: como run_deploy pero con LC_ALL forzado.
run_deploy_lc() {
  local lc="$1"
  shift
  OUT=$(cd "$SANDBOX" && LC_ALL="$lc" bash "$SCRIPT" "$@" 2>&1)
  CODE=$?
}

# ---------------------------------------------------------------------------
# (e) Faltan argumentos -> uso + exit != 0.
# ---------------------------------------------------------------------------
run_deploy
assert_nonzero "e) sin argumentos sale != 0" "$CODE"
assert_output_contains "e) imprime uso" "$OUT" "Uso:"

# ---------------------------------------------------------------------------
# (a) Despliegue correcto + owner final 82:82.
# ---------------------------------------------------------------------------
local_a="$TMP_DIR/class-srt-upload.php"
printf '<?php // correcto\n' > "$local_a"
rel_a="wp-content/plugins/atareao-functionality/includes/class-srt-upload.php"
run_deploy --host fake-host "$local_a=$rel_a"
assert_ok "a) despliegue correcto sale 0" "$CODE"
assert_output_contains "a) imprime línea OK" "$OUT" "OK "
fake_dest_a="$DEPLOY_WP_ROOT/$rel_a"
assert_eq "a) owner final 82:82" "82:82" "$(cat "$fake_dest_a.owner" 2>/dev/null)"

# ---------------------------------------------------------------------------
# (b) Hash discrepante -> exit == 1 y mensaje 'hash discrepante'.
# ---------------------------------------------------------------------------
local_b="$TMP_DIR/b.php"
printf 'b\n' > "$local_b"
export FAKE_HASH_OVERRIDE="deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef"
run_deploy --host fake-host "$local_b=wp-content/b.php"
unset FAKE_HASH_OVERRIDE
assert_eq "b) hash discrepante sale 1" "1" "$CODE"
assert_output_contains "b) mensaje 'hash discrepante'" "$OUT" "hash discrepante"
assert_output_contains "b) el mensaje cita el fichero" "$OUT" "wp-content/b.php"

# ---------------------------------------------------------------------------
# (c) Propiedad final incorrecta -> exit == 1 y mensaje 'propiedad incorrecta'.
# ---------------------------------------------------------------------------
local_c="$TMP_DIR/c.php"
printf 'c\n' > "$local_c"
export FAKE_OWNER_OVERRIDE="1000:1000"
run_deploy --host fake-host "$local_c=wp-content/c.php"
unset FAKE_OWNER_OVERRIDE
assert_eq "c) propiedad incorrecta sale 1" "1" "$CODE"
assert_output_contains "c) mensaje 'propiedad incorrecta'" "$OUT" "propiedad incorrecta"
assert_output_contains "c) el mensaje cita el fichero" "$OUT" "wp-content/c.php"

# ---------------------------------------------------------------------------
# (d) Copia de seguridad cuando el destino ya existe (contenido PREVIO).
# ---------------------------------------------------------------------------
local_d="$TMP_DIR/d.php"
printf 'nuevo\n' > "$local_d"
fake_dest_d="$DEPLOY_WP_ROOT/wp-content/d.php"
mkdir -p "$(dirname "$fake_dest_d")"
printf 'antiguo\n' > "$fake_dest_d"
run_deploy --host fake-host "$local_d=wp-content/d.php"
assert_ok "d) despliegue con destino preexistente sale 0" "$CODE"
if compgen -G "$fake_dest_d.bak.*" >/dev/null; then
  pass "d) se crea la copia de seguridad *.bak.*"
  bak_d=$(compgen -G "$fake_dest_d.bak.*" | head -n1)
  assert_file_equals "d) el backup guarda el contenido previo ('antiguo')" "$bak_d" "antiguo"
  assert_file_equals "d) el destino desplegado contiene 'nuevo'" "$fake_dest_d" "nuevo"
else
  fail "d) no se creó la copia de seguridad *.bak.*"
  fail "d) el backup guarda el contenido previo ('antiguo')"
  fail "d) el destino desplegado contiene 'nuevo'"
fi

# ---------------------------------------------------------------------------
# (f) Path traversal -> rechazo y nada escrito fuera de WP_ROOT.
# ---------------------------------------------------------------------------
local_f="$TMP_DIR/f.php"
printf 'f\n' > "$local_f"
run_deploy --host fake-host "$local_f=../escaped.php"
assert_nonzero "f) path traversal sale != 0" "$CODE"
assert_output_contains "f) mensaje de rechazo" "$OUT" "rechaz"
assert_missing "f) no se escribe fuera de WP_ROOT" "$FAKE_REMOTE/var/www/escaped.php"

# ---------------------------------------------------------------------------
# (g) Inyección de comandos en `rel` -> rechazo y NO se crea PWNED.
# ---------------------------------------------------------------------------
local_g="$TMP_DIR/g.php"
printf 'g\n' > "$local_g"
rm -f "$SANDBOX/PWNED" "$REPO_ROOT/PWNED"
run_deploy --host fake-host "$local_g=dir/evil.php'; touch PWNED; echo '"
assert_nonzero "g) inyección en rel sale != 0" "$CODE"
assert_missing "g) PWNED no se crea (sandbox)" "$SANDBOX/PWNED"
assert_missing "g) PWNED no se crea (raíz del repo)" "$REPO_ROOT/PWNED"

# ---------------------------------------------------------------------------
# (h) `rel` absoluta -> rechazo.
# ---------------------------------------------------------------------------
local_h="$TMP_DIR/h.php"
printf 'h\n' > "$local_h"
run_deploy --host fake-host "$local_h=/etc/passwd"
assert_nonzero "h) rel absoluta sale != 0" "$CODE"
assert_output_contains "h) mensaje de rechazo" "$OUT" "rechaz"

# ---------------------------------------------------------------------------
# (i) `rel` con espacios -> rechazo (charset inválido).
# ---------------------------------------------------------------------------
local_i="$TMP_DIR/i.php"
printf 'i\n' > "$local_i"
run_deploy --host fake-host "$local_i=dir/my file.php"
assert_nonzero "i) rel con espacios sale != 0" "$CODE"
assert_output_contains "i) mensaje de rechazo" "$OUT" "rechaz"

# ---------------------------------------------------------------------------
# (j) Reejecución rápida preserva el backup original (H3).
# ---------------------------------------------------------------------------
local_j2="$TMP_DIR/j_v2.php"
local_j3="$TMP_DIR/j_v3.php"
printf 'V2\n' > "$local_j2"
printf 'V3\n' > "$local_j3"
fake_dest_j="$DEPLOY_WP_ROOT/wp-content/j.php"
mkdir -p "$(dirname "$fake_dest_j")"
printf 'PROD-ORIGINAL\n' > "$fake_dest_j"
run_deploy --host fake-host "$local_j2=wp-content/j.php"
code_j1=$CODE
run_deploy --host fake-host "$local_j3=wp-content/j.php"
code_j2=$CODE
assert_ok "j) run 1 sale 0" "$code_j1"
assert_ok "j) run 2 sale 0" "$code_j2"
original_kept=0
count_baks=0
for b in "$fake_dest_j".bak.*; do
  [[ -e "$b" ]] || continue
  count_baks=$((count_baks + 1))
  [[ "$(cat "$b")" == "PROD-ORIGINAL" ]] && original_kept=1
done
if [[ "$original_kept" -eq 1 ]]; then
  pass "j) el backup original ('PROD-ORIGINAL') se preserva"
else
  fail "j) el backup original ('PROD-ORIGINAL') se preserva"
fi
assert_eq "j) existen dos .bak distintos" "2" "$count_baks"
assert_file_equals "j) el destino final es 'V3'" "$fake_dest_j" "V3"

# ---------------------------------------------------------------------------
# (k) Contrato CI: el workflow invoca esta suite.
# ---------------------------------------------------------------------------
if grep -q -F 'bash scripts/deploy-to-prod.test.sh' "$WORKFLOW"; then
  pass "k) CI invoca scripts/deploy-to-prod.test.sh"
else
  fail "k) CI invoca scripts/deploy-to-prod.test.sh"
fi

# ---------------------------------------------------------------------------
# (l) Charset ASCII estricto e independiente del locale (N1).
#     Con LC_ALL=es_ES.UTF-8, [A-Za-z] casaría letras acentuadas; el script fija
#     LC_ALL=C, así que 'café.jpg'/'añejo.jpg' deben rechazarse SIEMPRE.
# ---------------------------------------------------------------------------
local_l="$TMP_DIR/l.php"
printf 'l\n' > "$local_l"
lc_es=""
if locale -a 2>/dev/null | grep -qi '^es_ES'; then
  lc_es=$(locale -a 2>/dev/null | grep -i '^es_ES' | head -n1)
fi
for rel_l in "dir/café.jpg" "dir/añejo.jpg"; do
  if [[ -n "$lc_es" ]]; then
    run_deploy_lc "$lc_es" --host fake-host "$local_l=$rel_l"
    assert_eq "l) rechaza '$rel_l' con LC_ALL=$lc_es (exit 2)" "2" "$CODE"
    assert_output_contains "l) mensaje de rechazo para '$rel_l'" "$OUT" "rechaz"
  else
    pass "l) rechaza '$rel_l' (SKIP: locale es_ES no disponible)"
  fi
done

# ---------------------------------------------------------------------------
# (m) DEPLOY_HOST que empieza por '-' -> rechazo (N2): ssh lo leería como opción.
# ---------------------------------------------------------------------------
local_m="$TMP_DIR/m.php"
printf 'm\n' > "$local_m"
OUT=$(cd "$SANDBOX" && DEPLOY_HOST="-V" bash "$SCRIPT" "$local_m=wp-content/m.php" 2>&1)
CODE=$?
assert_eq "m) DEPLOY_HOST='-V' sale 2" "2" "$CODE"
assert_output_contains "m) mensaje de rechazo del host" "$OUT" "DEPLOY_HOST inválido"

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
