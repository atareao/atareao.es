/**
 * Main JavaScript
 */

(function () {
    'use strict';

    // Smooth scroll para enlaces internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#' && href !== '#0') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    // Añadir clase al header en scroll
    let lastScroll = 0;
    const header = document.querySelector('.site-header');

    if (header) {
        window.addEventListener('scroll', function () {
            var currentScroll = window.pageYOffset;

            if (currentScroll > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }

            lastScroll = currentScroll;
        });
    }

    // ========================================
    // Dark / Light theme toggle
    // ========================================
    (function () {
        const btn = document.querySelector('.nav-theme-toggle');
        if (!btn) {
            return;
        }

        function applyTheme(theme)
        {
            document.documentElement.setAttribute('data-theme', theme);
            // Persist in localStorage (same browser, fast read)
            localStorage.setItem('atareao-theme', theme);
            // Persist in cookie (read server-side by PHP on next request → zero FOUC)
            var maxAge = 365 * 24 * 60 * 60; // 1 year
            document.cookie = 'atareao-theme=' + theme + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
            btn.setAttribute('aria-label', theme === 'dark' ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
        }

        btn.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });

        // Sync if OS preference changes while page is open (only if user hasn't set a preference)
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
            // Default is dark, so only sync if the user has explicitly saved a preference
            if (localStorage.getItem('atareao-theme')) {
                return;
            }
            applyTheme(e.matches ? 'dark' : 'light');
        });
    })();

    // ========================================
    // Header search toggle (touch / keyboard)
    // CSS :hover handles pointer devices;
    // this handles tap on touch screens and keyboard nav.
    // ========================================
    (function () {
        const searchItem   = document.querySelector('.nav-item--search');
        const searchToggle = document.querySelector('.nav-search-toggle');
        const searchBox    = document.querySelector('.nav-search-box');
        const searchInput  = document.querySelector('.nav-search-input');

        if (!searchItem || !searchToggle || !searchBox) {
            return;
        }

        // Detect touch-only device (no fine pointer)
        const isTouchOnly = () => window.matchMedia('(hover: none)').matches;

        // Toggle open/close on button click (used on touch devices)
        searchToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = searchItem.classList.toggle('search-open');
            searchToggle.setAttribute('aria-expanded', String(isOpen));
            searchBox.setAttribute('aria-hidden', String(!isOpen));
            if (isOpen && searchInput) {
                // Small delay so the transition finishes before focus
                setTimeout(() => searchInput.focus(), 50);
            }
        });

        // On pointer devices keep focus-within behavior; auto-focus input on hover
        searchItem.addEventListener('mouseenter', function () {
            if (!isTouchOnly() && searchInput) {
                setTimeout(() => searchInput.focus(), 80);
            }
        });

        // Close when clicking/tapping outside
        document.addEventListener('click', function (e) {
            if (!searchItem.contains(e.target)) {
                searchItem.classList.remove('search-open');
                searchToggle.setAttribute('aria-expanded', 'false');
                searchBox.setAttribute('aria-hidden', 'true');
            }
        });

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && searchItem.classList.contains('search-open')) {
                searchItem.classList.remove('search-open');
                searchToggle.setAttribute('aria-expanded', 'false');
                searchBox.setAttribute('aria-hidden', 'true');
                searchToggle.focus();
            }
        });
    })();

    // Añadir target="_blank" a enlaces externos
    document.querySelectorAll('a').forEach(link => {
        var href = link.getAttribute('href');
        if (href && link.hostname && link.hostname !== window.location.hostname && !link.getAttribute('target')) {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        }
    });

    // ========================================
    // Clic en tarjeta de podcast → navegar al episodio
    // ========================================

    document.querySelectorAll('.podcast-card[data-url]').forEach(card => {
        card.addEventListener('click', function (e) {
            // Ignorar si el clic es en cualquier botón, enlace, reproductor o audio
            if (e.target.closest('button') ||
                e.target.closest('a') ||
                e.target.closest('audio') ||
                e.target.closest('.podcast-player-container')) {
                return;
            }
            window.location.href = this.dataset.url;
        });
    });

    // ========================================
    // Reproductor de podcast desplegable
    // ========================================
    
    // Formatear tiempo
    function formatTime(seconds)
    {
        if (isNaN(seconds)) {
            return '0:00';
        }
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }
    
    // Toggle reproductor
    document.querySelectorAll('.toggle-player-btn').forEach(button => {
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            var postId = this.getAttribute('data-post-id');
            var container = document.getElementById('player-container-' + postId);
            if (!container) {
                return;
            }
            var isExpanded = this.getAttribute('aria-expanded') === 'true';
            
            // Cerrar todos los demás reproductores
            document.querySelectorAll('.podcast-player-container').forEach(otherContainer => {
                if (otherContainer !== container) {
                    otherContainer.style.display = 'none';
                    // Pausar audio si está reproduciéndose
                    const audio = otherContainer.querySelector('audio');
                    if (audio && !audio.paused) {
                        audio.pause();
                    }
                }
            });
            
            document.querySelectorAll('.toggle-player-btn').forEach(otherBtn => {
                if (otherBtn !== this) {
                    otherBtn.setAttribute('aria-expanded', 'false');
                    otherBtn.setAttribute('aria-label', 'Mostrar reproductor');
                    otherBtn.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                }
            });
            
            // Toggle actual
            if (isExpanded) {
                container.style.display = 'none';
                this.setAttribute('aria-expanded', 'false');
                this.setAttribute('aria-label', 'Mostrar reproductor');
                this.innerHTML = '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                // Pausar audio
                const audio = container.querySelector('audio');
                if (audio && !audio.paused) {
                    audio.pause();
                }
            } else {
                container.style.display = 'block';
                this.setAttribute('aria-expanded', 'true');
                this.setAttribute('aria-label', 'Ocultar reproductor');
                this.innerHTML = '<span class="dashicons dashicons-arrow-up-alt2"></span>';
                
                // Inicializar reproductor si no se ha hecho
                initPlayer(container);
            }
        });
    });
    
    // Inicializar controles del reproductor
    function initPlayer(container)
    {
        var audio = container.querySelector('audio');
        var playBtn = container.querySelector('.podcast-play-btn');
        var progressBar = container.querySelector('.podcast-progress-bar');
        var progressFilled = container.querySelector('.podcast-progress-filled');
        var timeDisplay = container.querySelector('.podcast-time-display');

        if (!playBtn || !audio) {
            return;
        }

        if (playBtn.getAttribute('data-initialized')) {
            return;
        }
        playBtn.setAttribute('data-initialized', 'true');
        
        // Play/Pause
        playBtn.addEventListener('click', function () {
            if (audio.paused) {
                audio.play();
                this.setAttribute('data-playing', 'true');
            } else {
                audio.pause();
                this.setAttribute('data-playing', 'false');
            }
        });
        
        // Actualizar progreso
        audio.addEventListener('timeupdate', function () {
            if (audio.duration) {
                const percent = (audio.currentTime / audio.duration) * 100;
                progressFilled.style.width = percent + '%';
                timeDisplay.textContent = formatTime(audio.currentTime) + ' / ' + formatTime(audio.duration);
            }
        });
        
        // Duración cargada
        audio.addEventListener('loadedmetadata', function () {
            timeDisplay.textContent = '0:00 / ' + formatTime(audio.duration);
        });
        
        // Click en barra de progreso
        progressBar.addEventListener('click', function (e) {
            const rect = progressBar.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            audio.currentTime = percent * audio.duration;
        });
        
        // Cuando termine
        audio.addEventListener('ended', function () {
            playBtn.setAttribute('data-playing', 'false');
            progressFilled.style.width = '0%';
            audio.currentTime = 0;
        });
    }

    // Inicializar automáticamente reproductores que estén visibles al cargar la página
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.podcast-player-container').forEach(container => {
            const style = getComputedStyle(container);
            if (style.display !== 'none') {
                initPlayer(container);
            }
        });
    });

    // Mover el elemento de vistas (atareao-views) dentro de .entry-meta
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.entry-header').forEach(function (h) {
            var v = h.querySelector('.atareao-views');
            var meta = h.querySelector('.entry-meta');
            if (v && meta) {
                meta.appendChild(v);
            }
        });
    });

    // ========================================
    // Botón flotante "Volver arriba"
    // ========================================
    
    const backToTopBtn = document.getElementById('back-to-top');
    
    if (backToTopBtn) {
        // Mostrar/ocultar botón según scroll
        window.addEventListener('scroll', function () {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });
        
        // Click en el botón
        backToTopBtn.addEventListener('click', function () {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }

    // Redirect on select change (replaces inline onchange attributes)
    document.addEventListener('change', function (e) {
        var select = e.target.closest('.page-dropdown, .season-dropdown');
        if (select && select.value) {
            window.location.href = select.value;
        }
    });

})();
