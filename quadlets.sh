#!/bin/bash

# Atareao Podman Quadlets Management Script
# Gestiona los servicios usando Podman Quadlets con systemd

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar mensajes con colores
log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Directorio de quadlets
QUADLETS_DIR="$(pwd)/quadlets"
USER_QUADLETS_DIR="$HOME/.config/containers/systemd"

# Función para mostrar ayuda
show_help() {
    echo "Atareao Podman Quadlets Management"
    echo ""
    echo "Uso: $0 [COMANDO] [OPCIONES]"
    echo ""
    echo "Comandos de Gestión:"
    echo "  install         Instalar quadlets en systemd user"
    echo "  uninstall       Desinstalar quadlets de systemd user"
    echo "  start [service] Iniciar todos los servicios o uno específico"
    echo "  stop [service]  Detener todos los servicios o uno específico"
    echo "  restart [service] Reiniciar todos los servicios o uno específico"
    echo "  status [service]  Estado de servicios"
    echo "  logs [service]  Mostrar logs de servicios"
    echo "  enable [service] Habilitar servicios para inicio automático"
    echo "  disable [service] Deshabilitar servicios"
    echo ""
    echo "Comandos de Utilidad:"
    echo "  list            Listar todos los quadlets disponibles"
    echo "  validate        Validar archivos quadlets"
    echo "  clean           Limpiar recursos no utilizados"
    echo "  backup          Backup de volúmenes de datos"
    echo "  shell [service] Acceder al shell de un contenedor"
    echo "  wp [command]    Ejecutar WP-CLI commands"
    echo "  db              Acceder a MariaDB"
    echo "  redis           Acceder a Redis CLI"
    echo "  urls            Mostrar URLs de servicios"
    echo ""
    echo "Servicios disponibles:"
    echo "  - atareao-wordpress"
    echo "  - atareao-mariadb"
    echo "  - atareao-redis"
    echo "  - atareao-nginx"
    echo "  - atareao-phpmyadmin"
    echo ""
    echo "Ejemplos:"
    echo "  $0 install                    Instalar todos los quadlets"
    echo "  $0 start                     Iniciar todos los servicios"
    echo "  $0 start atareao-wordpress   Iniciar solo WordPress"
    echo "  $0 logs atareao-mariadb        Ver logs de MariaDB"
    echo "  $0 wp plugin list            Listar plugins WP"
}

# Verificar dependencias
check_dependencies() {
    if ! command -v podman &> /dev/null; then
        log_error "Podman no está instalado"
        log_info "Instalar con: sudo apt-get install podman"
        exit 1
    fi

    if ! systemctl --user status &> /dev/null; then
        log_error "systemd user no está disponible"
        log_info "Habilitar con: sudo loginctl enable-linger $USER"
        exit 1
    fi
}

# Crear directorio de quadlets user si no existe
ensure_quadlets_dir() {
    if [ ! -d "$USER_QUADLETS_DIR" ]; then
        mkdir -p "$USER_QUADLETS_DIR"
        log_info "Creado directorio: $USER_QUADLETS_DIR"
    fi
}

# Instalar quadlets
install_quadlets() {
    log_info "Instalando quadlets..."
    check_dependencies
    ensure_quadlets_dir
    
    # Copiar archivos quadlets
    if cp "$QUADLETS_DIR"/*.{container,volume,network} "$USER_QUADLETS_DIR/" 2>/dev/null; then
        log_success "Archivos quadlets copiados"
    else
        log_error "Error copiando archivos quadlets"
        return 1
    fi
    # Copiar configuración
    if [ -d "${QUADLETS_DIR}/mariadb" ]; then
        cp -r "${QUADLETS_DIR}/mariadb" "$HOME/.local/share/"
    fi
    echo "=== Copiando nginx ==="
    if [ -d "${QUADLETS_DIR}/nginx" ]; then
        cp -r "${QUADLETS_DIR}/nginx" "$HOME/.local/share/"
    fi
    
    # Recargar systemd user
    systemctl --user daemon-reload
    log_info "systemd user recargado"
    
    # Verificar que los servicios se hayan creado
    sleep 2
    local created_services=$(systemctl --user list-unit-files | grep atareao- | wc -l)
    
    if [ "$created_services" -gt 0 ]; then
        log_success "Quadlets instalados correctamente ($created_services servicios creados)"
        log_info "Archivos instalados en: $USER_QUADLETS_DIR"
        log_info "Para habilitar inicio automático, ejecuta: $0 enable"
    else
        log_error "No se crearon los servicios systemd. Verificar configuración."
        return 1
    fi
}

# Desinstalar quadlets
uninstall_quadlets() {
    log_warning "¿Estás seguro de que quieres desinstalar todos los quadlets? (y/N)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        log_info "Desinstalando quadlets..."
        
        # Detener servicios
        stop_services
        
        # Eliminar archivos
        rm -f "$USER_QUADLETS_DIR"/atareao-*.{container,volume,network}
        
        # Recargar systemd
        systemctl --user daemon-reload
        
        log_success "Quadlets desinstalados"
    else
        log_info "Operación cancelada"
    fi
}

# Función para obtener lista de servicios
get_services() {
    local filter=${1:-""}
    local type=${2:-"all"}
    
    if [ -n "$filter" ]; then
        echo "$filter"
    else
        case $type in
            "containers")
                echo "atareao-wordpress atareao-mariadb atareao-redis atareao-nginx atareao-phpmyadmin"
                ;;
            "volumes")
                echo "wordpress-data mariadb-data redis-data"
                ;;
            "networks")
                echo "atareao-network"
                ;;
            *)
                echo "atareao-wordpress atareao-mariadb atareao-redis atareao-nginx atareao-phpmyadmin"
                ;;
        esac
    fi
}

# Iniciar servicios
start_services() {
    local service=${1:-""}
    log_info "Iniciando servicios..."
    
    # Primero iniciar red y volúmenes
    if [ -z "$service" ]; then
        log_info "Iniciando red y volúmenes..."
        systemctl --user start atareao-network-network 2>/dev/null || true
        systemctl --user start mariadb-data-volume 2>/dev/null || true
        systemctl --user start wordpress-data-volume 2>/dev/null || true
        systemctl --user start redis-data-volume 2>/dev/null || true
        
        # Pequeña pausa para asegurar que la red esté lista
        sleep 2
    fi
    
    # Luego iniciar containers
    for svc in $(get_services "$service" "containers"); do
        log_info "Iniciando $svc..."
        if systemctl --user start "$svc" 2>/dev/null; then
            log_success "✅ $svc iniciado"
        else
            log_warning "⚠️  No se pudo iniciar $svc"
        fi
    done
    
    log_success "Servicios iniciados."
    log_info "🌐 Ejecuta '$0 urls' para ver URLs disponibles"
}

# Detener servicios
stop_services() {
    local service=${1:-""}
    log_info "Deteniendo servicios..."
    
    for svc in $(get_services "$service" "containers"); do
        if systemctl --user is-active "$svc" &>/dev/null; then
            log_info "Deteniendo $svc..."
            if systemctl --user stop "$svc" 2>/dev/null; then
                log_success "✅ $svc detenido"
            else
                log_warning "⚠️  No se pudo detener $svc"
            fi
        fi
    done
    
    log_success "Servicios detenidos"
}

# Reiniciar servicios
restart_services() {
    local service=${1:-""}
    log_info "Reiniciando servicios..."
    
    for svc in $(get_services "$service" "containers"); do
        if systemctl --user list-unit-files | grep -q "^$svc.service"; then
            log_info "Reiniciando $svc..."
            if systemctl --user restart "$svc" 2>/dev/null; then
                log_success "✅ $svc reiniciado"
            else
                log_warning "⚠️  No se pudo reiniciar $svc"
            fi
        fi
    done
    
    log_success "Servicios reiniciados"
}

# Estado de servicios
status_services() {
    local service=${1:-""}
    log_info "Estado de servicios:"
    echo ""
    
    for svc in $(get_services "$service" "containers"); do
        if systemctl --user list-unit-files | grep -q "^$svc.service"; then
            status=$(systemctl --user is-active "$svc" 2>/dev/null || echo "inactive")
            enabled=$(systemctl --user is-enabled "$svc" 2>/dev/null || echo "disabled")
            
            case $status in
                active) color=$GREEN; icon="✅" ;;
                inactive) color=$YELLOW; icon="⏸️" ;;
                *) color=$RED; icon="❌" ;;
            esac
            
            echo -e "  $icon ${color}$svc${NC} - $status ($enabled)"
        else
            echo -e "  ❓ ${RED}$svc${NC} - no encontrado"
        fi
    done
    
    echo ""
    log_info "📊 Para ver logs: $0 logs [servicio]"
}

# Mostrar logs
show_logs() {
    local service=${1:-"atareao-wordpress"}
    log_info "Mostrando logs de $service..."
    journalctl --user -u "$service" -f --since "1 hour ago"
}

# Habilitar servicios
enable_services() {
    local service=${1:-""}
    log_info "Habilitando servicios para inicio automático..."
    
    # Solo habilitar containers, no volumes ni networks
    for svc in $(get_services "$service" "containers"); do
        if systemctl --user list-unit-files | grep -q "^$svc.service"; then
            log_info "Habilitando $svc..."
            if systemctl --user enable "$svc" 2>/dev/null; then
                log_success "✅ $svc habilitado"
            else
                log_warning "⚠️  No se pudo habilitar $svc (puede que ya esté habilitado)"
            fi
        else
            log_warning "⚠️  Servicio $svc no encontrado"
        fi
    done
    
    log_success "Proceso de habilitación completado"
    log_info "💡 Para habilitar inicio automático del usuario: sudo loginctl enable-linger $USER"
}

# Deshabilitar servicios
disable_services() {
    local service=${1:-""}
    log_info "Deshabilitando servicios..."
    
    for svc in $(get_services "$service" "containers"); do
        if systemctl --user is-enabled "$svc" &>/dev/null; then
            log_info "Deshabilitando $svc..."
            if systemctl --user disable "$svc" 2>/dev/null; then
                log_success "✅ $svc deshabilitado"
            else
                log_warning "⚠️  No se pudo deshabilitar $svc"
            fi
        fi
    done
    
    log_success "Servicios deshabilitados"
}

# Listar quadlets
list_quadlets() {
    log_info "Quadlets disponibles en $QUADLETS_DIR:"
    echo ""
    
    for file in "$QUADLETS_DIR"/*.{container,volume,network}; do
        if [ -f "$file" ]; then
            basename "$file"
        fi
    done 2>/dev/null
    
    echo ""
    log_info "Quadlets instalados en systemd:"
    systemctl --user list-unit-files | grep atareao- || log_warning "No hay quadlets instalados"
}

# Validar quadlets
validate_quadlets() {
    log_info "Validando archivos quadlets..."
    local errors=0
    
    for file in "$QUADLETS_DIR"/*.{container,volume,network}; do
        if [ -f "$file" ]; then
            log_info "Validando $(basename "$file")..."
            
            # Verificar secciones requeridas
            if ! grep -q "^\[Unit\]" "$file"; then
                log_error "$(basename "$file"): Falta sección [Unit]"
                ((errors++))
            fi
            
            if [[ "$file" == *.container ]] && ! grep -q "^\[Container\]" "$file"; then
                log_error "$(basename "$file"): Falta sección [Container]"
                ((errors++))
            fi
            
            if [[ "$file" == *.volume ]] && ! grep -q "^\[Volume\]" "$file"; then
                log_error "$(basename "$file"): Falta sección [Volume]"
                ((errors++))
            fi
            
            if [[ "$file" == *.network ]] && ! grep -q "^\[Network\]" "$file"; then
                log_error "$(basename "$file"): Falta sección [Network]"
                ((errors++))
            fi
        fi
    done
    
    if [ $errors -eq 0 ]; then
        log_success "Todos los quadlets son válidos"
    else
        log_error "Se encontraron $errors errores"
        exit 1
    fi
}

# Limpiar recursos
clean_resources() {
    log_warning "¿Estás seguro de que quieres limpiar recursos no utilizados? (y/N)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        log_info "Limpiando recursos..."
        podman system prune -f
        log_success "Recursos limpiados"
    else
        log_info "Operación cancelada"
    fi
}

# Backup de volúmenes
backup_volumes() {
    log_info "Creando backup de volúmenes..."
    timestamp=$(date +"%Y%m%d_%H%M%S")
    backup_dir="backups/$timestamp"
    
    mkdir -p "$backup_dir"
    
    # Backup MariaDB
    if systemctl --user is-active atareao-mariadb &>/dev/null; then
        log_info "Backup MariaDB..."
        podman exec atareao-mariadb mariadb-dump -u atareao -psecure_password atareao_wp > "$backup_dir/mariadb_backup.sql"
    fi
    
    # Backup volúmenes
    for volume in wordpress-data mariadb-data redis-data; do
        if podman volume exists "$volume" 2>/dev/null; then
            log_info "Backup $volume..."
            podman run --rm -v "$volume":/data:Z -v "$(pwd)/$backup_dir":/backup:Z alpine tar czf "/backup/${volume}_backup.tar.gz" -C /data .
        fi
    done
    
    log_success "Backup completado en: $backup_dir"
}

# Acceder al shell de un contenedor
container_shell() {
    local service=${1:-"atareao-wordpress"}
    log_info "Accediendo al shell de $service..."
    
    # Remover prefijo si está presente
    container_name=${service#atareao-}
    container_name="atareao-$container_name"
    
    podman exec -it "$container_name" /bin/bash 2>/dev/null || \
    podman exec -it "$container_name" /bin/sh
}

# Mostrar URLs
show_urls() {
    log_info "URLs de servicios disponibles:"
    echo ""
    echo "🌐 WordPress:     http://localhost:8080"
    echo "🗄️  PHPMyAdmin:   http://localhost:8081"  
    echo "🔧 Nginx:         http://localhost (si está habilitado)"
    echo ""
    log_info "Credenciales de base de datos:"
    echo "   Usuario: atareao"
    echo "   Password: secure_password"
    echo "   Base de datos: atareao_wp"
}

# Comandos principales
case "${1:-help}" in
    install)
        install_quadlets
        ;;
        
    uninstall)
        uninstall_quadlets
        ;;
        
    start)
        start_services "$2"
        ;;
        
    stop)
        stop_services "$2"
        ;;
        
    restart)
        restart_services "$2"
        ;;
        
    status)
        status_services "$2"
        ;;
        
    logs)
        show_logs "$2"
        ;;
        
    enable)
        enable_services "$2"
        ;;
        
    disable)
        disable_services "$2"
        ;;
        
    list)
        list_quadlets
        ;;
        
    validate)
        validate_quadlets
        ;;
        
    clean)
        clean_resources
        ;;
        
    backup)
        backup_volumes
        ;;
        
    shell)
        container_shell "$2"
        ;;
        
    db)
        log_info "Conectando a MariaDB..."
        podman exec -it atareao-mariadb mariadb -u atareao -psecure_password atareao_wp
        ;;
        
    redis)
        log_info "Conectando a Redis CLI..."
        podman exec -it atareao-redis redis-cli
        ;;
        
    wp)
        shift
        log_info "Ejecutando WP-CLI: wp $*"
        podman exec -it atareao-wordpress wp --allow-root --path=/var/www/html "$@"
        ;;
        
    urls)
        show_urls
        ;;
        
    help|--help|-h)
        show_help
        ;;
        
    *)
        log_error "Comando desconocido: $1"
        echo ""
        show_help
        exit 1
        ;;
esac
