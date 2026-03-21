#!/bin/bash

# Script de desinstalación para Atareao WordPress con Quadlets
set -e

THEME_TARGET="$HOME/.local/share/atareao-theme"
QUADLET_DIR="$HOME/.config/containers/systemd"

echo "🛑 Desinstalando Atareao WordPress..."

# Parar y deshabilitar servicios
echo "⏹️  Parando servicios..."
systemctl --user stop atareao-nginx.container 2>/dev/null || true
systemctl --user stop atareao-wordpress.container 2>/dev/null || true
systemctl --user stop atareao-redis.container 2>/dev/null || true
systemctl --user stop atareao-mariadb.container 2>/dev/null || true
systemctl --user stop atareao-network.network 2>/dev/null || true

echo "🚫 Deshabilitando servicios..."
systemctl --user disable atareao-nginx.container 2>/dev/null || true
systemctl --user disable atareao-wordpress.container 2>/dev/null || true
systemctl --user disable atareao-redis.container 2>/dev/null || true
systemctl --user disable atareao-mariadb.container 2>/dev/null || true
systemctl --user disable atareao-network.network 2>/dev/null || true

# Eliminar archivos de quadlets
echo "🗑️  Eliminando archivos de quadlets..."
rm -f "$QUADLET_DIR/atareao-"*.container
rm -f "$QUADLET_DIR/atareao-"*.network
rm -f "$QUADLET_DIR/"*-data.volume

# Eliminar script wp-cli
echo "🗑️  Eliminando script wp-cli..."
rm -f "$HOME/.local/bin/wp"
echo "   ✅ Script wp eliminado"

# Eliminar enlace simbólico del tema
echo "🔗 Eliminando enlace simbólico del tema..."
if [ -L "$THEME_TARGET" ]; then
    rm "$THEME_TARGET"
    echo "   ✅ Enlace simbólico eliminado"
elif [ -d "$THEME_TARGET" ]; then
    echo "   ⚠️  Directorio encontrado en lugar de enlace simbólico"
    echo "   No se elimina automáticamente por seguridad"
    echo "   Ubicación: $THEME_TARGET"
fi

# Restaurar backup si existe
if [ -d "${THEME_TARGET}.backup" ]; then
    echo "📁 Restaurando backup del directorio del tema..."
    mv "${THEME_TARGET}.backup" "$THEME_TARGET"
    echo "   ✅ Backup restaurado"
fi

# Eliminar directorios de configuración (opcional - comentado por seguridad)
# echo "🗑️  Eliminando datos de configuración..."
# rm -rf "$HOME/.local/share/mariadb"
# rm -rf "$HOME/.local/share/nginx"

echo "🔄 Recargando systemd daemon..."
systemctl --user daemon-reload

echo ""
echo "✅ ¡Desinstalación completada!"
echo ""
echo "📝 Nota: Los datos de MariaDB y otros volúmenes permanecen intactos"
echo "   Para eliminarlos completamente, ejecuta:"
echo "   podman volume ls | grep atareao"
echo "   podman volume rm <volume_name>"
echo ""
echo "🔍 Para verificar que todo se eliminó:"
echo "   systemctl --user list-units | grep atareao"
