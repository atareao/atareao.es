#!/bin/bash

# Script de instalación para Atareao WordPress con Quadlets
set -e

THEME_SOURCE="/data/php/new.atareao.es/wp-content/themes/atareao-twentytwentyfive"
THEME_TARGET="$HOME/.local/share/atareao-theme"
QUADLET_DIR="$HOME/.config/containers/systemd"

echo "🚀 Instalando Atareao WordPress con Quadlets..."

# Crear directorio de quadlets si no existe
mkdir -p "$QUADLET_DIR"

# Crear enlace simbólico del tema
echo "📁 Creando enlace simbólico del tema..."
if [ -L "$THEME_TARGET" ]; then
    echo "   Eliminando enlace simbólico existente..."
    rm "$THEME_TARGET"
elif [ -d "$THEME_TARGET" ]; then
    echo "   ⚠️  Directorio existente encontrado en $THEME_TARGET"
    echo "   Renombrando a ${THEME_TARGET}.backup"
    mv "$THEME_TARGET" "${THEME_TARGET}.backup"
fi

ln -s "$THEME_SOURCE" "$THEME_TARGET"
echo "   ✅ Enlace simbólico creado: $THEME_TARGET -> $THEME_SOURCE"

# Copiar archivos de quadlets
echo "📦 Copiando configuración de quadlets..."
cp "$THEME_SOURCE/quadlets"/*.container "$QUADLET_DIR/"
cp "$THEME_SOURCE/quadlets"/*.network "$QUADLET_DIR/"
cp "$THEME_SOURCE/quadlets"/*.volume "$QUADLET_DIR/"

# Copiar script wp-cli
echo "🔧 Instalando script wp-cli..."
mkdir -p "$HOME/.local/bin"
cp "$THEME_SOURCE/quadlets/wp" "$HOME/.local/bin/wp"
chmod +x "$HOME/.local/bin/wp"
echo "   ✅ Script wp instalado en $HOME/.local/bin/wp"

# Verificar si ~/.local/bin está en el PATH
if [[ ":$PATH:" != *":$HOME/.local/bin:"* ]]; then
    echo "   ⚠️  $HOME/.local/bin no está en tu PATH"
    echo "   Añade esto a tu ~/.bashrc o ~/.zshrc:"
    echo "   export PATH=\"\$HOME/.local/bin:\$PATH\""
fi

# Copiar directorios de configuración
if [ -d "$THEME_SOURCE/quadlets/mariadb" ]; then
    cp -r "$THEME_SOURCE/quadlets/mariadb" "$HOME/.local/share/"
fi
if [ -d "$THEME_SOURCE/quadlets/nginx" ]; then
    cp -r "$THEME_SOURCE/quadlets/nginx" "$HOME/.local/share/"
fi

echo "🔄 Recargando systemd daemon..."
systemctl --user daemon-reload

echo "🌐 Iniciando servicios..."
systemctl --user start atareao-network.network
systemctl --user start atareao-mariadb.container
systemctl --user start atareao-redis.container
systemctl --user start atareao-wordpress.container
systemctl --user start atareao-nginx.container

echo "🔧 Habilitando servicios para inicio automático..."
systemctl --user enable atareao-network.network
systemctl --user enable atareao-mariadb.container
systemctl --user enable atareao-redis.container
systemctl --user enable atareao-wordpress.container
systemctl --user enable atareao-nginx.container

echo ""
echo "✅ ¡Instalación completada!"
echo ""
echo "🌐 WordPress disponible en: http://localhost:8080"
echo "📊 PhpMyAdmin disponible en: http://localhost:8081"
echo "📧 MailHog disponible en: http://localhost:8025"
echo ""
echo "📋 Comandos útiles:"
echo "   Ver estado:    systemctl --user status atareao-wordpress"
echo "   Ver logs:      journalctl --user -fu atareao-wordpress"
echo "   Parar todo:    ./uninstall.sh"
