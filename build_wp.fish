#!/usr/bin/fish

# Definir las rutas absolutas para evitar confusiones
set BASE_DIR "/data/php/atareao.es"
set THEME_PATH "$BASE_DIR/wp-content/themes"
set PLUGIN_PATH "$BASE_DIR/wp-content/plugins"

set THEME_NAME "atareao-theme"
set PLUGIN_NAME "atareao-functionality"

echo "📦 Preparando paquetes para instalación directa en WordPress..."

# --- Empaquetar el TEMA ---
if test -d "$THEME_PATH/$THEME_NAME"
    echo "🎨 Procesando tema: $THEME_NAME"
    # Entramos en la carpeta de temas para que el zip no herede la ruta absoluta
    pushd $THEME_PATH
    zip -r -q "$BASE_DIR/$THEME_NAME.zip" $THEME_NAME -x "*.git*" "node_modules/*" ".DS_Store"
    popd
else
    echo "⚠️  Error: No se encuentra la carpeta del tema en $THEME_PATH"
end

# --- Empaquetar el PLUGIN ---
if test -d "$PLUGIN_PATH/$PLUGIN_NAME"
    echo "🔌 Procesando plugin: $PLUGIN_NAME"
    # Entramos en la carpeta de plugins
    pushd $PLUGIN_PATH
    zip -r -q "$BASE_DIR/$PLUGIN_NAME.zip" $PLUGIN_NAME -x "*.git*" "node_modules/*" ".DS_Store"
    popd
else
    echo "⚠️  Error: No se encuentra la carpeta del plugin en $PLUGIN_PATH"
end

echo "✅ ¡Hecho! Los archivos .zip en $BASE_DIR ya son instalables."
