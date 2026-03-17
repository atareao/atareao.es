# 🎙️ Bloque Reproductor de Podcast - Guía Rápida

## ✅ Instalación Completada

El bloque **Reproductor de Podcast** ha sido añadido exitosamente al plugin `atareao-functionality`.

## 🚀 Uso Inmediato

### En el Editor de WordPress:

1. **Abre el editor de Gutenberg** en cualquier página o entrada
2. **Haz clic en el botón [+]** para añadir un bloque
3. **Busca**: "Reproductor de Podcast" o "Podcast Player"
4. **Inserta el bloque**

### Configuración:

El bloque tiene dos modos de uso:

#### Modo 1: Seleccionar Podcast Existente

```
1. En el panel derecho, abre "Configuración del Podcast"
2. Despliega el selector "Seleccionar Podcast"
3. Elige un episodio de tus podcasts publicados
4. Los datos se cargan automáticamente
```

#### Modo 2: URL Personalizada

```
1. En "URL del Audio", introduce la URL directa del MP3
   Ejemplo: https://ejemplo.com/episodio.mp3
2. Escribe un "Título" personalizado
3. Añade una "Descripción" (opcional)
```

## 📋 Características del Bloque

✅ **Selector de podcasts** - Lista todos tus podcasts publicados
✅ **Campo de URL** - Para archivos de audio externos
✅ **Título personalizable** - Editable manualmente
✅ **Descripción** - Texto descriptivo del episodio
✅ **Reproductor HTML5** - Controles nativos del navegador
✅ **Responsive** - Se adapta a todos los dispositivos
✅ **Enlace automático** - Link a la página del podcast
✅ **Alineaciones** - Normal, Wide, Full Width

## 🎨 Personalización

### Colores

- **Fondo**: Panel → Color → Fondo
- **Texto**: Panel → Color → Texto

### Alineación

- Barra superior: botones de alineación
- Normal / Ancho extendido / Ancho completo

### Espaciado

- Panel → Espaciado
- Ajusta padding y margin

## 📱 Vista en Frontend

El reproductor mostrará:

```
┌─────────────────────────────────┐
│ 🎙️ Título del Episodio          │
│                                 │
│ Descripción breve del episodio  │
│                                 │
│ ▶️ [━━━━━━━━━━━○───] 00:00     │
│                                 │
│ Ver página del podcast →        │
└─────────────────────────────────┘
```

## 🔧 Archivos Creados

```
wp-content/plugins/atareao-functionality/
├── includes/
│   └── class-podcast-block.php         # Clase principal
├── assets/
│   └── blocks/
│       └── podcast-player/
│           ├── block.json              # Configuración
│           ├── index.js                # JavaScript
│           ├── style.css               # Estilos frontend
│           ├── editor.css              # Estilos editor
│           └── README.md               # Documentación detallada
└── atareao-functionality.php           # Actualizado
```

## 📚 Documentación Completa

Para más información detallada, consulta:

- [README del bloque](../assets/blocks/podcast-player/README.md)

## 🎯 Casos de Uso

### 1. Página de "Últimos Episodios"

Crea una página con múltiples bloques, uno por episodio.

### 2. Insertar en Entradas

Añade el bloque en medio de tu contenido textual.

### 3. Widget en Sidebar

Usa el bloque en áreas de widgets compatible con bloques.

### 4. Audio Externo

Reproduce archivos MP3 alojados en otros servidores.

## ✨ Próximos Pasos

1. **Abre el editor** de WordPress
2. **Busca el bloque** "Reproductor de Podcast"
3. **Añádelo** a tu contenido
4. **Configúralo** según tus necesidades
5. **Publica** y verifica el resultado

## 🐛 Soporte

Si el bloque no aparece:

- Limpia la caché del navegador (Ctrl+Shift+R)
- Verifica que el plugin está activado
- Comprueba que tienes WordPress 5.8+

---

**¡El bloque está listo para usar! 🎉**
