# .justfile — manage quadlets (WordPress, MariaDB, Redis, NGINX, etc.)

ROOT_DIR := justfile_directory()
HOME_DIR := home_directory()
QUADLETS_SOURCE_DIR := ROOT_DIR / "quadlets"
QUADLETS_TARGET_DIR := HOME_DIR / ".config/containers/systemd"
NGINX_CONFIG_SOURCE_DIR := ROOT_DIR / "nginx"
NGINX_CONFIG_TARGET_DIR := HOME_DIR / ".config/nginx"


# list: show all available recipes in this justfile
list:
    @just --list

# Install: create symlinks of quadlets into the systemd user directory and reload
install:
    #!/usr/bin/env fish
    echo "Installing quadlets from {{ QUADLETS_SOURCE_DIR}} into {{ QUADLETS_TARGET_DIR }}"
    mkdir -p "{{ QUADLETS_TARGET_DIR }}"
    for f in {{ QUADLETS_SOURCE_DIR }}/*container {{ QUADLETS_SOURCE_DIR }}/*network {{ QUADLETS_SOURCE_DIR }}/*volume {{ QUADLETS_SOURCE_DIR }}/*service {{ QUADLETS_SOURCE_DIR }}/*socket {{ QUADLETS_SOURCE_DIR }}/*mount
        set quadlet_name "$(basename $f)"
        if test -e "$f"
            echo "Linking $f to {{ QUADLETS_TARGET_DIR }}/$quadlet_name"
            ln -sf "$f" "{{ QUADLETS_TARGET_DIR }}/$quadlet_name"
        end
    end
    echo "Reloading systemd user daemon to recognize new quadlet units"
    systemctl --user daemon-reload
    echo "Installing configuration files for nginx"
    mkdir -p "{{ NGINX_CONFIG_TARGET_DIR }}"
    for c in {{ NGINX_CONFIG_SOURCE_DIR }}/*.conf
        set config_name "$(basename $c)"
        if test -e "$c"
            echo "Linking $c to {{ NGINX_CONFIG_TARGET_DIR }}/$config_name"
            ln -sf "$c" "{{ NGINX_CONFIG_TARGET_DIR }}/$config_name"
        end
    end
    echo "Install complete."

# Uninstall: stop all services and remove symlinks
uninstall:
    #!/usr/bin/env fish
    echo "Stopping services and removing quadlet symlinks from {{ QUADLETS_TARGET_DIR }}"
    for f in {{ QUADLETS_SOURCE_DIR }}/*container {{ QUADLETS_SOURCE_DIR }}/*network {{ QUADLETS_SOURCE_DIR }}/*volume {{ QUADLETS_SOURCE_DIR }}/*service {{ QUADLETS_SOURCE_DIR }}/*socket {{ QUADLETS_SOURCE_DIR }}/*mount
        set quadlet_filename_with_extension "$(basename $f)"
        set quadlet_filename "$(path change-extension '' $quadlet_filename_with_extension)"
        set quadlet_fileextension "$(path extension $quadlet_filename_with_extension)"
        if test -e "{{ QUADLETS_TARGET_DIR }}/$quadlet_filename_with_extension"
            if test "$quadlet_fileextension" = ".container"
                echo "Stopping $quadlet_filename_with_extension if running"
                systemctl --user stop "$quadlet_filename" || true
            end
            echo "Removing symlink {{ QUADLETS_TARGET_DIR }}/$quadlet_filename_with_extension"
            rm -f "{{ QUADLETS_TARGET_DIR }}/$quadlet_filename_with_extension"
        end
    end
    echo "Reloading systemd user daemon to recognize new quadlet units"
    systemctl --user daemon-reload
    echo "Uninstalling configuration files for nginx from "
    for c in {{ NGINX_CONFIG_SOURCE_DIR }}/*.conf
        set config_name "$(basename $c)"
        if test -e "$c"
            echo "Removing symlink $c to {{ NGINX_CONFIG_TARGET_DIR }}/$config_name"
            rm -f "{{ NGINX_CONFIG_TARGET_DIR }}/$config_name"
        end
    end
    echo "Uninstall complete."

# Start: start all quadlet units found in the target dir
start:
    #!/usr/bin/env fish
    echo "Starting quadlet units found in {{ QUADLETS_TARGET_DIR }}"
    for f in {{ QUADLETS_SOURCE_DIR }}/*container
        set quadlet_filename_with_extension "$(basename $f)"
        set quadlet_filename "$(path change-extension '' $quadlet_filename_with_extension)"
        set quadlet_fileextension "$(path extension $quadlet_filename_with_extension)"
        if test -e "{{ QUADLETS_TARGET_DIR}}/$quadlet_filename_with_extension"
            echo "Starting $quadlet_filename"
            systemctl --user start "$quadlet_filename" || true
        end
    end

# Stop: stop all quadlet units found in the target dir
stop:
    #!/usr/bin/env fish
    echo "Stopping quadlet units found in {{ QUADLETS_TARGET_DIR }}"
    for f in {{ QUADLETS_SOURCE_DIR }}/*container
        set quadlet_filename_with_extension "$(basename $f)"
        set quadlet_filename "$(path change-extension '' $quadlet_filename_with_extension)"
        set quadlet_fileextension "$(path extension $quadlet_filename_with_extension)"
        if test -e "{{ QUADLETS_TARGET_DIR}}/$quadlet_filename_with_extension"
            echo "Stopping $quadlet_filename"
            systemctl --user stop "$quadlet_filename" || true
        end
    end

# status: show status for each quadlet unit (no pager)
status:
    #!/usr/bin/env fish
    echo "Quadlet unit status from {{ QUADLETS_TARGET_DIR }}:"
    echo -n "🔍 Analizando quadlets... "
    set -l table_data
    for f in {{ QUADLETS_SOURCE_DIR }}/*container
        set -l link_status "OFF"
        set -l link_color red
        set -l run_status "---"
        set -l run_color white
        set quadlet_filename_with_extension "$(basename $f)"
        set quadlet_filename "$(path change-extension '' $quadlet_filename_with_extension)"
        set quadlet_fileextension "$(path extension $quadlet_filename_with_extension)"
        if test -e "{{ QUADLETS_TARGET_DIR}}/$quadlet_filename_with_extension"
            set link_status "ON"
            set link_color green
            if systemctl --user is-active --quiet "$quadlet_filename"
                set run_status "running"
                set run_color green
            else
                set run_status "stopped"
                set run_color yellow
            end
        end
        set -a table_data "$link_color|$link_status|$run_color|$run_status|$quadlet_filename"
    end
    printf "\r%-50s\n" "✅ Análisis completado"
    echo "------------------------------------------"
    printf "%-8s %-12s %s\n" "LINK" "STATUS" "STACK"
    echo "------------------------------------------"
    for line in $table_data
        set -l parts (string split "|" $line)
        echo -n " ["
        set_color $parts[1]; echo -n "$parts[2]"; set_color normal
        echo -n "]    "
        set_color $parts[3]; printf "%-11s" "$parts[4]"; set_color normal
        echo " $parts[5]"
    end
    echo "------------------------------------------"

logs service:
    echo "Showing logs for {{ service }} container (Ctrl+C to exit)"
    journalctl --user -u "{{ service }}" -f --since "1 hour ago"

build:
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
