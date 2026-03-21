#!/bin/bash

# Script de verificación de estado para Atareao WordPress
set -e

THEME_TARGET="$HOME/.local/share/atareao-theme"

echo "📊 Estado de Atareao WordPress"
echo "================================"

# Verificar enlace simbólico
echo ""
echo "🔗 Enlace simbólico del tema:"
if [ -L "$THEME_TARGET" ]; then
    TARGET=$(readlink "$THEME_TARGET")
    echo "   ✅ Existe: $THEME_TARGET -> $TARGET"
    if [ -d "$TARGET" ]; then
        echo "   ✅ Directorio destino existe"
    else
        echo "   ❌ Directorio destino no existe: $TARGET"
    fi
elif [ -d "$THEME_TARGET" ]; then
    echo "   ⚠️  Existe como directorio (no enlace): $THEME_TARGET"
else
    echo "   ❌ No existe: $THEME_TARGET"
fi

# Verificar servicios
echo ""
echo "🔧 Estado de servicios:"
SERVICES=(
    "atareao-network.network"
    "atareao-mariadb.container"
    "atareao-redis.container"
    "atareao-wordpress.container"
    "atareao-nginx.container"
    "atareao-phpmyadmin.container"
)

for service in "${SERVICES[@]}"; do
    if systemctl --user is-active "$service" >/dev/null 2>&1; then
        STATUS="✅ ACTIVO"
        if systemctl --user is-enabled "$service" >/dev/null 2>&1; then
            STATUS="$STATUS (habilitado)"
        else
            STATUS="$STATUS (no habilitado)"
        fi
    elif systemctl --user is-enabled "$service" >/dev/null 2>&1; then
        STATUS="🟡 INACTIVO (habilitado)"
    else
        STATUS="❌ INACTIVO (no habilitado)"
    fi
    printf "   %-30s %s\n" "$service" "$STATUS"
done

# Verificar puertos
echo ""
echo "🌐 Puertos en uso:"
PORT_CHECKS=(
    "8080:WordPress"
    "8081:PhpMyAdmin"
    "8025:MailHog"
    "80:Nginx"
)

for port_info in "${PORT_CHECKS[@]}"; do
    port="${port_info%:*}"
    service="${port_info#*:}"
    if ss -tuln | grep -q ":$port "; then
        echo "   ✅ Puerto $port ($service) - en uso"
    else
        echo "   ❌ Puerto $port ($service) - libre"
    fi
done

# Verificar volúmenes
echo ""
echo "💾 Volúmenes de Podman:"
if command -v podman >/dev/null 2>&1; then
    VOLUMES=$(podman volume ls --format "{{.Name}}" | grep -E "(mariadb-data|redis-data|wordpress-data)" 2>/dev/null || true)
    if [ -n "$VOLUMES" ]; then
        for volume in $VOLUMES; do
            echo "   ✅ $volume"
        done
    else
        echo "   ⚠️  No se encontraron volúmenes de Atareao"
    fi
else
    echo "   ❌ Podman no disponible"
fi

# URLs de acceso
echo ""
echo "🔗 URLs de acceso:"
echo "   WordPress:   http://localhost:8080"
echo "   PhpMyAdmin:  http://localhost:8081"
echo "   MailHog:     http://localhost:8025"

echo ""
echo "📋 Comandos útiles:"
echo "   Logs WordPress: journalctl --user -fu atareao-wordpress"
echo "   Logs MariaDB:     journalctl --user -fu atareao-mariadb"
echo "   Reiniciar todo: systemctl --user restart atareao-*.container"
echo ""
