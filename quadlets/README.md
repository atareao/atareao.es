# Atareao Podman Quadlets

Sistema de contenedores para WordPress usando **Podman Quadlets** con integración nativa de systemd.

## 🎯 ¿Por qué Quadlets?

Los **Podman Quadlets** son la evolución natural de docker-compose para el ecosistema Podman, ofreciendo:

- **Integración nativa con systemd**: Gestión como servicios del sistema
- **Inicio automático**: Los contenedores se inician con el sistema
- **Mejor seguridad**: Ejecución rootless por defecto
- **Gestión unificada**: Un solo comando para todos los servicios
- **Logs centralizados**: Integración con journald
- **Dependencias automáticas**: Orden correcto de inicio

## 🏗️ Arquitectura

### Servicios incluidos:

```
atareao-network.network     ← Red compartida
├── wordpress-data.volume   ← Volumen WordPress
├── mariadb-data.volume       ← Volumen MariaDB
├── redis-data.volume       ← Volumen Redis
├── atareao-mariadb.container     ← Base de datos
├── atareao-redis.container     ← Cache Redis
├── atareao-wordpress.container ← Aplicación WordPress
├── atareao-wp-cli.container    ← Herramienta CLI para WordPress
├── atareao-nginx.container     ← Proxy/Load balancer
├── atareao-phpmyadmin.container ← Gestión BD
├── atareao-mailhog.container   ← Testing email
└── atareao-node.container      ← Build tools
```

## 🚀 Uso Rápido

### Instalación inicial:

```bash
# Instalar y configurar todo el entorno
./install.sh

# Verificar estado
./status.sh
```

### Comandos diarios:

```bash
./status.sh                   # Ver estado completo
./update.sh                   # Actualizar configuración
systemctl --user status atareao-wordpress  # Estado específico
journalctl --user -fu atareao-wordpress    # Logs en tiempo real
./uninstall.sh               # Desinstalar completamente
wp --info                     # WP-CLI: Información de WordPress
```

### WP-CLI:

El script `wp` está disponible globalmente después de la instalación:

```bash
# Información del sitio
wp option get siteurl
wp option get home

# Gestión de plugins
wp plugin list
wp plugin activate mi-plugin

# Gestión de temas
wp theme list
wp theme activate atareao-twentytwentyfive

# Base de datos
wp db check
wp db optimize

# Usuarios
wp user list

# Cache
wp cache flush

# Más comandos
wp --help
```

## 📋 Scripts Disponibles

### Gestión principal:

- `./install.sh` - **Instalación completa**: Crea enlace simbólico del tema, instala quadlets, inicia servicios
- `./uninstall.sh` - **Desinstalación completa**: Para servicios, elimina configuración y enlace simbólico
- `./update.sh` - **Actualización**: Sincroniza cambios de configuración y reinicia servicios si es necesario
- `./status.sh` - **Estado del sistema**: Muestra estado detallado de servicios, enlaces y puertos

### Características de los scripts:

- ✅ **Gestión automática** del enlace simbólico del tema
- ✅ **Sincronización** de archivos de quadlets
- ✅ **Verificación** de estado y dependencias
- ✅ **Logs informativos** con emojis y colores
- ✅ **Backup automático** de configuraciones existentes
- ✅ **Rollback seguro** en caso de problemas

## 🔗 Gestión del Tema

### Enlace simbólico automático:

El script de instalación crea un **enlace simbólico** desde el directorio esperado por WordPress hacia el tema en desarrollo:

```
~/.local/share/atareao-theme → /data/php/new.atareao.es/wp-content/themes/atareao-twentytwentyfive
```

### Ventajas:

- ✅ **Sincronización automática**: Los cambios en el código se reflejan inmediatamente
- ✅ **No hay duplicación**: Un solo lugar para el código fuente
- ✅ **Desarrollo fluido**: Editar archivos directamente en el repositorio
- ✅ **Git-friendly**: Todos los cambios se trackean correctamente

### Verificación:

```bash
# Ver estado del enlace
ls -la ~/.local/share/atareao-theme

# Verificar conexión
./status.sh | grep "Enlace simbólico"
```

## 🌐 URLs de Servicios

Una vez iniciados los servicios:

- **WordPress**: http://localhost:8080
- **PHPMyAdmin**: http://localhost:8081
- **Mailhog**: http://localhost:8025
- **Nginx**: http://localhost (si está habilitado)

## ⚙️ Configuración

### Credenciales por defecto:

```
MariaDB:
  Usuario: atareao
  Password: secure_password
  Base de datos: atareao_wp
  Root password: root_password
```

### Archivos de configuración:

- `quadlets/wordpress/php.conf.ini` - Configuración PHP
- `quadlets/nginx/nginx.conf` - Configuración Nginx
- `quadlets/nginx/default.conf` - Virtual host
- `quadlets/mariadb/init.sql` - Inicialización MariaDB

## 🔧 Requisitos del Sistema

### Obligatorios:

```bash
# Podman
sudo apt-get install podman

# systemd user habilitado
sudo loginctl enable-linger $USER
```

### Verificación:

```bash
podman --version                    # Debe mostrar versión
systemctl --user status            # Debe funcionar sin errores
```

## 🔄 Migración desde docker-compose

Si vienes de docker-compose, los cambios principales:

```bash
# Antes (docker-compose)
docker-compose up -d
docker-compose logs wordpress
docker-compose exec wordpress bash

# Ahora (Quadlets)
./quadlets.sh start
./quadlets.sh logs wordpress
./quadlets.sh shell wordpress
```

## 🏥 Health Checks

Todos los contenedores incluyen health checks automáticos:

- **WordPress**: Verificación HTTP
- **MariaDB**: mysqladmin ping
- **Redis**: redis-cli ping
- **Nginx**: Endpoint /nginx-health
- **PHPMyAdmin**: Verificación HTTP

Ver estado: `./quadlets.sh status`

## 📊 Monitoring

### Logs centralizados:

```bash
# Logs de todos los servicios
journalctl --user -f

# Logs de servicio específico
journalctl --user -u atareao-wordpress -f

# Logs con filtros
journalctl --user -u atareao-mariadb --since "1 hour ago"
```

### Métricas de contenedores:

```bash
podman stats                    # Uso de recursos
podman ps -a                   # Estado de contenedores
podman network ls              # Redes
podman volume ls               # Volúmenes
```

## 🔐 Seguridad

### Características de seguridad:

- **Rootless**: Contenedores sin privilegios root
- **User namespaces**: Aislamiento de usuarios
- **SELinux**: Etiquetas de seguridad (`:Z`)
- **Network isolation**: Red privada para servicios
- **Resource limits**: Límites de CPU/memoria via systemd

### Configuración adicional:

```bash
# Habilitar podman socket (opcional)
systemctl --user enable podman.socket

# Configurar firewall
sudo firewall-cmd --add-port=8080/tcp --permanent
sudo firewall-cmd --reload
```

## 🚨 Troubleshooting

### Problemas comunes:

**Servicios no inician:**

```bash
# Verificar estado completo
./status.sh

# Ver logs específicos
journalctl --user -u atareao-wordpress -f

# Reiniciar systemd
systemctl --user daemon-reload
```

**Problemas con enlace simbólico:**

```bash
# Verificar enlace del tema
ls -la ~/.local/share/atareao-theme

# Recrear enlace si es necesario
rm ~/.local/share/atareao-theme
ln -s /data/php/new.atareao.es/wp-content/themes/atareao-twentytwentyfive ~/.local/share/atareao-theme

# O usar el script de actualización
./update.sh
```

**Error: "statfs: no such file or directory":**

```bash
# El directorio del tema no existe, ejecutar:
./install.sh

# O crear enlace manualmente:
mkdir -p ~/.local/share
ln -s /data/php/new.atareao.es/wp-content/themes/atareao-twentytwentyfive ~/.local/share/atareao-theme
```

**Permisos de archivos:**

```bash
# Verificar permisos de scripts
ls -la *.sh

# Hacer ejecutables si es necesario
chmod +x *.sh

# Verificar contexto SELinux
ls -lZ quadlets/

# Restaurar contexto
restorecon -R quadlets/
```

**Networking:**

```bash
# Verificar red
podman network inspect atareao-network

# Recrear red si es necesario
./quadlets.sh stop
podman network rm atareao-network
./quadlets.sh start
```

## 📝 Desarrollo

### Flujo de trabajo:

1. Modificar código en el directorio del theme
2. Los archivos se sincronizan automáticamente via bind mounts
3. Recargar la página para ver cambios
4. Para cambios de configuración: `./quadlets.sh restart`

### Build de assets:

```bash
# Node.js container para builds
./quadlets.sh logs atareao-node    # Ver proceso de build

# O ejecutar manualmente
npm run build
npm run watch
```

## 📦 Backup y Restore

### Crear backup:

```bash
./quadlets.sh backup
# Crea: backups/YYYYMMDD_HHMMSS/
```

### Restaurar backup:

```bash
# Desde backup específico
podman exec -i atareao-mariadb mariadb -u root -proot_password atareao_wp < backup.sql

# Restaurar volúmenes
podman run --rm -v mariadb-data:/data -v $(pwd)/backups/latest:/backup alpine \
  tar xzf /backup/mariadb-data_backup.tar.gz -C /data
```

## 🎯 Próximas Mejoras

- [ ] Podman pods para mejor aislamiento
- [ ] Configuración de recursos via systemd
- [ ] Auto-update de imágenes
- [ ] Integración con systemd-networkd
- [ ] Backup automatizado via systemd timers
- [ ] Monitoring con Prometheus

## 📞 Soporte

Para problemas específicos de Quadlets:

1. Verificar logs: `journalctl --user -u [servicio]`
2. Validar quadlets: `./quadlets.sh validate`
3. Comprobar dependencias: `./quadlets.sh status`
4. Reinstalar si es necesario: `./quadlets.sh uninstall && ./quadlets.sh install`
