#!/bin/bash

# Script de actualización para Atareao WordPress
set -e

THEME_SOURCE="/data/php/new.atareao.es/wp-content/themes/atareao-twentytwentyfive"
THEME_TARGET="$HOME/.local/share/atareao-theme"

echo "🔄 Actualizando Atareao WordPress..."

# Verificar que el enlace simbólico existe
if [ ! -L "$THEME_TARGET" ]; then
    echo "❌ Error: El enlace simbólico del tema no existe"
    echo "   Ejecuta ./install.sh primero"
    exit 1
fi

# Verificar que apunta al directorio correcto
CURRENT_TARGET=$(readlink "$THEME_TARGET")
if [ "$CURRENT_TARGET" != "$THEME_SOURCE" ]; then
    echo "⚠️  El enlace simbólico apunta a: $CURRENT_TARGET"
    echo "   Se esperaba: $THEME_SOURCE"
    echo "🔧 Actualizando enlace simbólico..."
    rm "$THEME_TARGET"
    ln -s "$THEME_SOURCE" "$THEME_TARGET"
    echo "   ✅ Enlace simbólico actualizado"
fi

# Actualizar configuración de quadlets si es necesario
QUADLET_DIR="$HOME/.config/containers/systemd"
echo "📦 Verificando configuración de quadlets..."

# Comparar y actualizar si hay cambios
UPDATED=false
for file in "$THEME_SOURCE/quadlets"/*.{container,network,volume}; do
    if [ -f "$file" ]; then
        filename=$(basename "$file")
        if [ ! -f "$QUADLET_DIR/$filename" ] || ! cmp -s "$file" "$QUADLET_DIR/$filename"; then
            echo "   Actualizando: $filename"
            cp "$file" "$QUADLET_DIR/"
            UPDATED=true
        fi
    fi
done

# Actualizar directorios de configuración
for dir in mariadb nginx; do
    if [ -d "$THEME_SOURCE/quadlets/$dir" ]; then
        if [ ! -d "$HOME/.local/share/$dir" ] || ! diff -r "$THEME_SOURCE/quadlets/$dir" "$HOME/.local/share/$dir" >/dev/null 2>&1; then
            echo "   Actualizando configuración: $dir"
            cp -r "$THEME_SOURCE/quadlets/$dir" "$HOME/.local/share/"
            UPDATED=true
        fi
    fi
done

if [ "$UPDATED" = true ]; then
    echo "🔄 Recargando systemd daemon..."
    systemctl --user daemon-reload
    
    echo "🔄 Reiniciando servicios afectados..."
    systemctl --user restart atareao-wordpress.container
    echo "   ✅ Servicios reiniciados"
else
    echo "   ✅ No hay actualizaciones necesarias"
fi

echo ""
echo "✅ ¡Actualización completada!"
echo ""
echo "📋 Estado de servicios:"
systemctl --user is-active atareao-wordpress.container atareao-mariadb.container atareao-nginx.container 2>/dev/null || true
