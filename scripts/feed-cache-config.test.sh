#!/usr/bin/env bash
#
# Runner de verificación para la caché de feeds en nginx
# (`openspec/changes/feed-performance-and-caching/specs/feed-cache/spec.md`).
#
# El repositorio no tiene framework de tests y estas aserciones son de
# configuración (no hay nada que ejecutar): el script comprueba, con
# grep/awk/sed, que `nginx/default.conf` y `nginx/pro/wp.conf` cumplen el
# contrato de la capability `feed-cache`:
#
#   a) la regla `"~*/feed/" 1;` NO está en el `map $skip_cache` de ninguno de
#      los dos ficheros (los feeds no se excluyen de la caché).
#   b) existe en AMBOS un `location ~* /feed/?$` con: `fastcgi_cache_valid 200
#      5m` (TTL corto, no 1h/6h), `fastcgi_cache_use_stale` con `timeout`,
#      `fastcgi_cache_background_update on`, `fastcgi_cache_lock on` y una
#      `fastcgi_cache_key` que incluya `$request_uri`.
#   c) el bypass por sesión se mantiene: `"~*wordpress_logged_in" 1;` sigue en
#      el `map` de ambos ficheros.
#   d) `fastcgi_cache_use_stale` NO usa `http_502` ni `http_504` (inválidos en
#      FastCGI; romperían `nginx -t`).
#
#   - Imprime PASS/FAIL por aserción y acumula total/passed/failed.
#   - NO se aborta al primer fallo: sigue hasta el final.
#   - Sale con 1 si hay al menos un fallo, con 0 si todos pasan.
#   - Deriva sus rutas de ${BASH_SOURCE[0]}, así que puede ejecutarse desde
#     cualquier CWD (raíz del repo, /tmp, runner de CI). Las rutas de los
#     ficheros son parametrizables con NGINX_DEFAULT_CONF / NGINX_PRO_CONF
#     (útil para verificar una copia pre-change).
#
# Uso: bash scripts/feed-cache-config.test.sh
set -uo pipefail

# Rutas derivadas de la ubicación del propio script, no del CWD.
SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)

NGINX_DEFAULT_CONF="${NGINX_DEFAULT_CONF:-$REPO_ROOT/nginx/default.conf}"
NGINX_PRO_CONF="${NGINX_PRO_CONF:-$REPO_ROOT/nginx/pro/wp.conf}"

total=0
passed=0
failed=0

pass() { total=$((total + 1)); passed=$((passed + 1)); printf 'PASS: %s\n' "$1"; }
fail() { total=$((total + 1)); failed=$((failed + 1)); printf 'FAIL: %s\n' "$1"; }

# assert_contains <etiqueta> <texto> <aguja literal>
assert_contains() {
  if printf '%s' "$2" | grep -qF -- "$3"; then
    pass "$1"
  else
    fail "$1 (falta: $3)"
  fi
}

# assert_not_contains <etiqueta> <texto> <aguja literal>
assert_not_contains() {
  if printf '%s' "$2" | grep -qF -- "$3"; then
    fail "$1 (presente: $3)"
  else
    pass "$1"
  fi
}

# extract_block <fichero> <regex de la línea de apertura>: extrae el bloque
# que abre con esa línea (contando llaves) y lo imprime por stdout.
extract_block() {
  local file="$1" pat="$2"
  awk -v pat="$pat" '
    $0 ~ pat { inblock = 1 }
    inblock {
      print
      tmp = $0; o = gsub(/\{/, "{", tmp)
      tmp = $0; c = gsub(/\}/, "}", tmp)
      depth += o - c
      if (o > 0) opened = 1
      if (opened && depth <= 0) exit
    }
  ' "$file"
}

# Patrones de las líneas de apertura (regex dinámica de awk). Anclados a inicio
# de línea (`^[[:space:]]*`) para no casar comentarios que citan el propio
# bloque (p. ej. un comentario que menciona `location ~* /feed/?$`).
MAP_OPEN='^[[:space:]]*map[[:space:]]+"[$]request_uri:[$]http_cookie"'
FEED_OPEN='^[[:space:]]*location[[:space:]]+~[*][[:space:]]+/feed/[?][$]'

check_file() {
  local label="$1" file="$2"

  if [[ ! -f "$file" ]]; then
    fail "$label: no existe el fichero '$file'"
    return
  fi

  local map_block feed_block
  map_block=$(extract_block "$file" "$MAP_OPEN")
  feed_block=$(extract_block "$file" "$FEED_OPEN")

  # (a) La regla de exclusión de feeds NO debe estar en el map.
  if [[ -z "$map_block" ]]; then
    fail "$label a) no se encontró el map \$skip_cache"
  else
    assert_not_contains "$label a) la regla \"~*/feed/\" 1; NO está en el map \$skip_cache" \
      "$map_block" '"~*/feed/" 1;'
  fi

  # (c) El bypass por sesión debe seguir presente en el map.
  if [[ -z "$map_block" ]]; then
    fail "$label c) no se encontró el map \$skip_cache"
  else
    assert_contains "$label c) la regla \"~*wordpress_logged_in\" 1; sigue en el map \$skip_cache" \
      "$map_block" '"~*wordpress_logged_in" 1;'
  fi

  # (b) Debe existir el location de feeds con la política dedicada.
  if [[ -z "$feed_block" ]]; then
    fail "$label b) no existe 'location ~* /feed/?$'"
    fail "$label b) 'fastcgi_cache_valid 200 5m' en el location de feeds"
    fail "$label b) TTL 200 corto (sin 1h/6h) en el location de feeds"
    fail "$label b) 'fastcgi_cache_use_stale' incluye 'timeout'"
    fail "$label b) 'fastcgi_cache_background_update on'"
    fail "$label b) 'fastcgi_cache_lock on'"
    fail "$label b) 'fastcgi_cache_key' incluye '\$request_uri'"
    fail "$label d) 'fastcgi_cache_use_stale' sin http_502/http_504"
    return
  fi

  assert_contains "$label b) 'fastcgi_cache_valid 200 5m' en el location de feeds" \
    "$feed_block" 'fastcgi_cache_valid 200 5m'

  if printf '%s' "$feed_block" | grep -Eq 'fastcgi_cache_valid[[:space:]]+200[[:space:]]+[0-9].*(1h|6h)'; then
    fail "$label b) TTL 200 corto (sin 1h/6h) en el location de feeds"
  else
    pass "$label b) TTL 200 corto (sin 1h/6h) en el location de feeds"
  fi

  # fastcgi_cache_use_stale debe incluir 'timeout' (sirve copia obsoleta).
  # Sólo se consideran líneas de directiva (no comentarios que explican el porqué).
  if printf '%s' "$feed_block" | grep -Eq '^[[:space:]]*fastcgi_cache_use_stale[^;]*[[:space:]]timeout([[:space:];]|$)'; then
    pass "$label b) 'fastcgi_cache_use_stale' incluye 'timeout'"
  else
    fail "$label b) 'fastcgi_cache_use_stale' incluye 'timeout'"
  fi

  assert_contains "$label b) 'fastcgi_cache_background_update on'" \
    "$feed_block" 'fastcgi_cache_background_update on'
  assert_contains "$label b) 'fastcgi_cache_lock on'" \
    "$feed_block" 'fastcgi_cache_lock on'
  assert_contains "$label b) 'fastcgi_cache_key' incluye '\$request_uri'" \
    "$feed_block" 'fastcgi_cache_key "$scheme$request_method$host$request_uri"'

  # (d) http_502/http_504 no son válidos en fastcgi_cache_use_stale.
  # Sólo líneas de directiva (los comentarios pueden citar 502/504 para explicar).
  local stale_lines
  stale_lines=$(printf '%s' "$feed_block" | grep -E '^[[:space:]]*fastcgi_cache_use_stale' || true)
  if printf '%s' "$stale_lines" | grep -Eq 'http_50[24]'; then
    fail "$label d) 'fastcgi_cache_use_stale' sin http_502/http_504"
  else
    pass "$label d) 'fastcgi_cache_use_stale' sin http_502/http_504"
  fi
}

printf 'Feed cache config: %s\n' "$NGINX_DEFAULT_CONF"
printf 'Feed cache config: %s\n\n' "$NGINX_PRO_CONF"

check_file "default.conf" "$NGINX_DEFAULT_CONF"
check_file "pro/wp.conf" "$NGINX_PRO_CONF"

printf '\nTOTAL=%d PASS=%d FAIL=%d\n' "$total" "$passed" "$failed"

if [[ "$failed" -gt 0 ]]; then
  exit 1
fi
exit 0
