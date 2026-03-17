<?php
/**
 * Clase para el bloque de reproductor de podcast
 *
 * @package Atareao_Functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Atareao_Podcast_Block {
    
    /**
     * Inicializar el bloque
     */
    public static function init() {
        // Registrar directamente sin hooks adicionales
        self::register_assets();
        self::register_block();
    }
    
    /**
     * Registrar assets del bloque
     */
    public static function register_assets() {
        // Registrar script del editor
        wp_register_script(
            'atareao-podcast-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/index.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-data', 'wp-api-fetch'),
            ATAREAO_PLUGIN_VERSION,
            false
        );
        
        // Registrar estilos del editor
        wp_register_style(
            'atareao-podcast-block-editor',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/editor.css',
            array('wp-edit-blocks'),
            ATAREAO_PLUGIN_VERSION
        );
        
        // Registrar estilos del frontend
        wp_register_style(
            'atareao-podcast-block-style',
            ATAREAO_PLUGIN_URL . 'assets/blocks/podcast-player/style.css',
            array(),
            ATAREAO_PLUGIN_VERSION
        );
    }
    
    /**
     * Registrar el bloque de podcast
     */
    public static function register_block() {
        // Verificar que la función existe (WordPress 5.8+)
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Registrar el bloque (WordPress busca automáticamente block.json en el directorio)
        register_block_type(ATAREAO_PLUGIN_DIR . 'assets/blocks/podcast-player', array(
            'editor_script' => 'atareao-podcast-block-editor',
            'editor_style' => 'atareao-podcast-block-editor',
            'style' => 'atareao-podcast-block-style',
            'render_callback' => array(__CLASS__, 'render_podcast_player'),
        ));
    }
    
    /**
     * Renderizar el bloque en el frontend
     */
    public static function render_podcast_player($attributes) {
        $audio_url = isset($attributes['audioUrl']) ? esc_url($attributes['audioUrl']) : '';
        $title = isset($attributes['title']) ? esc_html($attributes['title']) : '';
        $description = isset($attributes['description']) ? esc_html($attributes['description']) : '';
        $podcast_id = isset($attributes['podcastId']) ? intval($attributes['podcastId']) : 0;
        
        // Si hay un ID de podcast, obtener sus datos
        if ($podcast_id > 0) {
            $podcast = get_post($podcast_id);
            if ($podcast && $podcast->post_type === 'podcast') {
                $title = $title ?: get_the_title($podcast_id);
                $description = $description ?: get_the_excerpt($podcast_id);
                $audio_meta = get_post_meta($podcast_id, 'mp3-url', true);
                $audio_url = $audio_url ?: $audio_meta;
            }
        }
        
        // Si no hay URL de audio, mostrar mensaje
        if (empty($audio_url)) {
            return '<div class="atareao-podcast-player-placeholder">' . __('Por favor, selecciona un podcast o añade una URL de audio.', 'atareao-functionality') . '</div>';
        }
        
        // ID único para este reproductor
        $player_id = 'podcast-player-' . uniqid();
        
        // Construir el HTML del reproductor personalizado
        ob_start();
        ?>
        <div class="atareao-podcast-player" data-player-id="<?php echo $player_id; ?>">
            <div class="podcast-player-custom">
                <!-- Audio element (oculto) -->
                <audio id="<?php echo $player_id; ?>" preload="metadata" class="podcast-audio-element">
                    <source src="<?php echo $audio_url; ?>" type="audio/mpeg">
                </audio>
                
                <!-- Controles personalizados - Layout de 3 columnas -->
                <div class="podcast-controls">
                    <!-- Columna izquierda: Controles de reproducción en una sola fila -->
                    <div class="podcast-controls-left">
                        <div class="podcast-controls-all">
                            <!-- Velocidad de reproducción (izquierda) -->
                            <select class="podcast-speed" aria-label="<?php _e('Velocidad', 'atareao-functionality'); ?>">
                                <option value="0.5">0.5×</option>
                                <option value="0.75">0.75×</option>
                                <option value="1" selected>1×</option>
                                <option value="1.25">1.25×</option>
                                <option value="1.5">1.5×</option>
                                <option value="2">2×</option>
                            </select>
                            
                            <!-- Retroceder 30 segundos -->
                            <button class="podcast-btn podcast-skip podcast-backward" aria-label="<?php _e('Retroceder 30 segundos', 'atareao-functionality'); ?>" title="<?php _e('Retroceder 30s', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-back"></span>
                                <span class="podcast-skip-label">30</span>
                            </button>
                            
                            <!-- Play/Pause button -->
                            <button class="podcast-btn podcast-play-pause" aria-label="<?php _e('Reproducir/Pausar', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-play"></span>
                            </button>
                            
                            <!-- Avanzar 30 segundos -->
                            <button class="podcast-btn podcast-skip podcast-forward" aria-label="<?php _e('Avanzar 30 segundos', 'atareao-functionality'); ?>" title="<?php _e('Avanzar 30s', 'atareao-functionality'); ?>">
                                <span class="dashicons dashicons-controls-forward"></span>
                                <span class="podcast-skip-label">30</span>
                            </button>
                            
                            <!-- Control de volumen (derecha) -->
                            <div class="podcast-volume-control">
                                <button class="podcast-btn podcast-mute" aria-label="<?php _e('Volumen', 'atareao-functionality'); ?>">
                                    <span class="dashicons dashicons-controls-volumeon"></span>
                                </button>
                                <input type="range" class="podcast-volume" min="0" max="100" value="100" aria-label="<?php _e('Volumen', 'atareao-functionality'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna centro: Barra de progreso -->
                    <div class="podcast-controls-center">
                        <div class="podcast-progress-container">
                            <div class="podcast-progress-bar">
                                <div class="podcast-progress-filled"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Columna derecha: Tiempos -->
                    <div class="podcast-controls-right">
                        <div class="podcast-controls-time">
                            <span class="podcast-time podcast-current-time">0:00</span>
                            <span class="podcast-time-separator">/</span>
                            <span class="podcast-time podcast-duration">0:00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        (function() {
            const playerId = '<?php echo $player_id; ?>';
            const player = document.getElementById(playerId);
            if (!player) return;
            
            const container = document.querySelector('[data-player-id="' + playerId + '"]');
            const playPauseBtn = container.querySelector('.podcast-play-pause');
            const backwardBtn = container.querySelector('.podcast-backward');
            const forwardBtn = container.querySelector('.podcast-forward');
            const progressBar = container.querySelector('.podcast-progress-filled');
            const progressContainer = container.querySelector('.podcast-progress-bar');
            const currentTimeEl = container.querySelector('.podcast-current-time');
            const durationEl = container.querySelector('.podcast-duration');
            const muteBtn = container.querySelector('.podcast-mute');
            const volumeSlider = container.querySelector('.podcast-volume');
            const speedSelect = container.querySelector('.podcast-speed');
            
            // Formatear tiempo
            function formatTime(seconds) {
                if (isNaN(seconds)) return '0:00';
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return mins + ':' + (secs < 10 ? '0' : '') + secs;
            }
            
            // Play/Pause
            playPauseBtn.addEventListener('click', function() {
                if (player.paused) {
                    player.play();
                    playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-play');
                    playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-pause');
                } else {
                    player.pause();
                    playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
                    playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
                }
            });
            
            // Retroceder 30 segundos
            backwardBtn.addEventListener('click', function() {
                player.currentTime = Math.max(0, player.currentTime - 30);
                // Pequeña animación visual
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            // Avanzar 30 segundos
            forwardBtn.addEventListener('click', function() {
                player.currentTime = Math.min(player.duration, player.currentTime + 30);
                // Pequeña animación visual
                this.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
            
            // Atajos de teclado
            document.addEventListener('keydown', function(e) {
                if (!container.querySelector('.podcast-audio-element:focus') && 
                    !document.activeElement.classList.contains('podcast-speed') &&
                    !document.activeElement.classList.contains('podcast-volume')) {
                    
                    // Espacio = play/pause
                    if (e.code === 'Space' && e.target === document.body) {
                        e.preventDefault();
                        playPauseBtn.click();
                    }
                    // Flecha izquierda = -30s
                    else if (e.code === 'ArrowLeft') {
                        e.preventDefault();
                        backwardBtn.click();
                    }
                    // Flecha derecha = +30s
                    else if (e.code === 'ArrowRight') {
                        e.preventDefault();
                        forwardBtn.click();
                    }
                }
            });
            
            // Actualizar progreso
            player.addEventListener('timeupdate', function() {
                if (player.duration) {
                    const percent = (player.currentTime / player.duration) * 100;
                    progressBar.style.width = percent + '%';
                    currentTimeEl.textContent = formatTime(player.currentTime);
                }
            });
            
            // Duración cargada
            player.addEventListener('loadedmetadata', function() {
                durationEl.textContent = formatTime(player.duration);
            });
            
            // Click en barra de progreso
            progressContainer.addEventListener('click', function(e) {
                const rect = progressContainer.getBoundingClientRect();
                const percent = (e.clientX - rect.left) / rect.width;
                player.currentTime = percent * player.duration;
            });
            
            // Touch para móvil en barra de progreso
            let isSeeking = false;
            progressContainer.addEventListener('touchstart', function(e) {
                isSeeking = true;
            });
            
            progressContainer.addEventListener('touchmove', function(e) {
                if (isSeeking) {
                    const rect = progressContainer.getBoundingClientRect();
                    const touch = e.touches[0];
                    const percent = (touch.clientX - rect.left) / rect.width;
                    player.currentTime = Math.max(0, Math.min(1, percent)) * player.duration;
                }
            });
            
            progressContainer.addEventListener('touchend', function() {
                isSeeking = false;
            });
            
            // Volumen
            volumeSlider.addEventListener('input', function() {
                player.volume = this.value / 100;
                player.muted = false; // Desactivar mute al mover el slider
                updateVolumeIcon();
            });
            
            // Cerrar slider de volumen al hacer click fuera (solo si se abrió con click)
            let volumeOpenedByClick = false;
            
            document.addEventListener('click', function(e) {
                const volumeControl = container.querySelector('.podcast-volume-control');
                if (!volumeControl.contains(e.target) && volumeControl.classList.contains('show-volume') && volumeOpenedByClick) {
                    volumeControl.classList.remove('show-volume');
                    volumeOpenedByClick = false;
                }
            });
            
            // Marcar cuando se abre por click
            muteBtn.addEventListener('click', function() {
                const volumeControl = container.querySelector('.podcast-volume-control');
                if (volumeControl.classList.contains('show-volume')) {
                    volumeOpenedByClick = true;
                }
            });
            
            // Mute y toggle del slider de volumen
            muteBtn.addEventListener('click', function(e) {
                // Si es click derecho o con modificador, hacer mute
                if (e.button !== 0 || e.ctrlKey || e.shiftKey) {
                    player.muted = !player.muted;
                    updateVolumeIcon();
                } else {
                    // Click normal: toggle del slider
                    const volumeControl = container.querySelector('.podcast-volume-control');
                    volumeControl.classList.toggle('show-volume');
                }
            });
            
            function updateVolumeIcon() {
                const icon = muteBtn.querySelector('.dashicons');
                icon.classList.remove('dashicons-controls-volumeon', 'dashicons-controls-volumeoff');
                if (player.muted || player.volume === 0) {
                    icon.classList.add('dashicons-controls-volumeoff');
                } else {
                    icon.classList.add('dashicons-controls-volumeon');
                }
            }
            
            // Velocidad
            speedSelect.addEventListener('change', function() {
                player.playbackRate = parseFloat(this.value);
            });
            
            // Cuando termine
            player.addEventListener('ended', function() {
                playPauseBtn.querySelector('.dashicons').classList.remove('dashicons-controls-pause');
                playPauseBtn.querySelector('.dashicons').classList.add('dashicons-controls-play');
                progressBar.style.width = '0%';
                player.currentTime = 0;
            });
        })();
        </script>
        <?php
        
        return ob_get_clean();
    }
}
