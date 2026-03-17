# 🎙️ Bloque Reproductor de Podcast

## Descripción

El bloque **Reproductor de Podcast** permite insertar un reproductor de audio personalizado en tus páginas y entradas de WordPress. Puedes reproducir episodios de podcast directamente desde el contenido.

## 📦 Características

- ✅ **Selector de podcasts** - Elige cualquier podcast publicado
- ✅ **URL de audio personalizada** - O introduce una URL directa
- ✅ **Título y descripción** - Personalizables
- ✅ **Reproductor HTML5** - Compatible con todos los navegadores modernos
- ✅ **Responsive** - Se adapta a móvil, tablet y desktop
- ✅ **Enlace al podcast** - Link automático a la página del podcast
- ✅ **Alineaciones** - Soporta ancho completo y ancho extendido

## 🚀 Cómo usar

### 1. Añadir el bloque

En el editor de Gutenberg:

1. Haz clic en el botón **[+]** para añadir un nuevo bloque
2. Busca "**Reproductor de Podcast**" o busca en la categoría **Media**
3. Haz clic en el bloque para insertarlo

### 2. Configurar el bloque

En el **panel de configuración** (barra lateral derecha), encontrarás:

#### Opción A: Seleccionar un podcast existente

1. Despliega el selector **"Seleccionar Podcast"**
2. Elige un episodio de la lista
3. Los datos (título, descripción, audio) se cargarán automáticamente

#### Opción B: Configuración manual

1. **URL del Audio**: Introduce la URL directa del archivo MP3
   ```
   https://ejemplo.com/mi-podcast.mp3
   ```
2. **Título**: Escribe el título del episodio
3. **Descripción**: Añade una breve descripción

### 3. Vista previa

El bloque mostrará:

- 🎵 Icono de audio
- 📝 Título del episodio
- 📄 Descripción
- ▶️ Reproductor de audio (controles nativos del navegador)
- 🔗 Enlace a la página del podcast (si seleccionaste uno)

## 🎨 Personalización

### Alineación

El bloque soporta tres alineaciones:

- **Normal**: Ancho del contenido
- **Ancho extendido** (Wide): Más ancho que el contenido normal
- **Ancho completo** (Full): De borde a borde

Para cambiar la alineación:

1. Selecciona el bloque
2. Usa los botones de alineación en la barra superior
3. O usa el panel de configuración

### Colores

Puedes personalizar:

- **Color de fondo**: En Configuración → Color → Fondo
- **Color de texto**: En Configuración → Color → Texto

### Espaciado

Ajusta márgenes y padding:

1. Ve al panel **Configuración**
2. Expande **Espaciado**
3. Ajusta padding y margin como necesites

## 📱 Aspectos técnicos

### Formatos de audio soportados

El reproductor es compatible con:

- ✅ **MP3** (recomendado)
- ✅ **OGG**
- ✅ **WAV**
- ✅ **M4A**

### Atributos del bloque

```json
{
  "audioUrl": "URL del archivo de audio",
  "title": "Título del episodio",
  "description": "Descripción breve",
  "podcastId": "ID del podcast (0 si es manual)"
}
```

### API REST

El bloque utiliza la API REST de WordPress para:

- Obtener la lista de podcasts (`/wp/v2/podcast`)
- Acceder a los metadatos (`_audio_url`, `_duration`)

## 🔧 Ejemplos de uso

### Caso 1: Página de "Últimos Episodios"

```
1. Crea una página nueva
2. Añade un título: "Últimos Episodios"
3. Inserta varios bloques de "Reproductor de Podcast"
4. Selecciona un podcast diferente en cada uno
```

### Caso 2: Insertar en una entrada

```
1. Edita cualquier entrada
2. En el lugar donde quieras el reproductor, añade el bloque
3. Puedes combinar con otros bloques (texto, imágenes, etc.)
```

### Caso 3: Audio externo

```
1. Si tienes un archivo MP3 en otro servidor
2. Añade el bloque
3. NO selecciones ningún podcast
4. Introduce la URL directa en "URL del Audio"
5. Escribe título y descripción manualmente
```

## 🎨 Estilos CSS

Los estilos están en:

- `style.css` - Estilos del frontend y editor
- `editor.css` - Estilos solo del editor

Clases CSS principales:

```css
.atareao-podcast-player          /* Contenedor principal */
.podcast-player-title            /* Título */
.podcast-player-description      /* Descripción */
.podcast-player-controls         /* Controles de audio */
.podcast-audio                   /* Elemento <audio> */
.podcast-player-link             /* Enlace al podcast */
```

### Personalizar estilos

Añade en el CSS adicional de tu tema:

```css
/* Cambiar color de fondo */
.atareao-podcast-player {
  background: #your-color;
}

/* Cambiar estilo del título */
.podcast-player-title {
  color: #your-color;
  font-size: 24px;
}

/* Personalizar el enlace */
.podcast-link {
  color: #your-color;
  text-transform: uppercase;
}
```

## 🐛 Solución de problemas

### El audio no se reproduce

- ✅ Verifica que la URL del audio es correcta y accesible
- ✅ Comprueba que el formato es MP3 u otro compatible
- ✅ Asegúrate de que el servidor permite CORS si es externo
- ✅ Prueba la URL directamente en el navegador

### No aparecen podcasts en el selector

- ✅ Verifica que tienes podcasts publicados
- ✅ Comprueba que el custom post type 'podcast' está registrado
- ✅ Reactiva el plugin si es necesario

### Los metadatos no se cargan

- ✅ Asegúrate de que el campo `_audio_url` está registrado en REST API
- ✅ Verifica permisos de usuario (debe poder editar posts)
- ✅ Comprueba la consola del navegador por errores

### El bloque no aparece en el editor

- ✅ Limpia la caché del navegador
- ✅ Verifica que el plugin está activado
- ✅ Comprueba que WordPress es 5.8 o superior

## 📚 Archivos del bloque

```
assets/blocks/podcast-player/
├── block.json        # Configuración del bloque
├── index.js          # JavaScript del editor
├── style.css         # Estilos del frontend
└── editor.css        # Estilos del editor

includes/
└── class-podcast-block.php  # Clase PHP del bloque
```

## 🔄 Actualizaciones

Versión actual: **1.0.0**

Características futuras planeadas:

- [ ] Visualizador de forma de onda
- [ ] Lista de reproducción
- [ ] Velocidad de reproducción ajustable
- [ ] Marcadores de tiempo
- [ ] Descarga del episodio

## 📖 Recursos

- [Gutenberg Block Editor Handbook](https://developer.wordpress.org/block-editor/)
- [register_block_type()](https://developer.wordpress.org/reference/functions/register_block_type/)
- [HTML Audio Element](https://developer.mozilla.org/en-US/docs/Web/HTML/Element/audio)

---

**¡Disfruta creando contenido de podcast con tu nuevo bloque! 🎙️🎉**
