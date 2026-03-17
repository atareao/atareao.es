/**
 * Navigation JavaScript
 */

(function() {
    'use strict';

    const navigation = document.querySelector('.main-navigation');
    
    if (!navigation) {
        return;
    }

    // Menú responsive (si es necesario añadir un botón de menú móvil en el futuro)
    const mobileMenuButton = document.createElement('button');
    mobileMenuButton.classList.add('menu-toggle');
    mobileMenuButton.setAttribute('aria-label', 'Menú');
    mobileMenuButton.innerHTML = '<span></span><span></span><span></span>';
    
    // Añadir soporte para submenús en el futuro
    const menuItems = navigation.querySelectorAll('.menu-item-has-children');
    
    menuItems.forEach(item => {
        const link = item.querySelector('a');
        const submenu = item.querySelector('.sub-menu');
        
        if (link && submenu) {
            // Crear botón para expandir submenú
            const button = document.createElement('button');
            button.classList.add('submenu-toggle');
            button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-label', 'Expandir submenú');
            button.innerHTML = '▼';
            
            link.parentNode.insertBefore(button, link.nextSibling);
            
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !expanded);
                item.classList.toggle('is-open');
            });
        }
    });

    // Cerrar submenús al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (!navigation.contains(e.target)) {
            menuItems.forEach(item => {
                item.classList.remove('is-open');
                const button = item.querySelector('.submenu-toggle');
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        }
    });

    // Accesibilidad: navegación con teclado
    const menuLinks = navigation.querySelectorAll('a');
    
    menuLinks.forEach((link, index) => {
        link.addEventListener('keydown', function(e) {
            const parentLi = this.closest('li');
            
            // Flecha derecha: abrir submenú
            if (e.key === 'ArrowRight' && parentLi.classList.contains('menu-item-has-children')) {
                e.preventDefault();
                const submenu = parentLi.querySelector('.sub-menu a');
                if (submenu) {
                    parentLi.classList.add('is-open');
                    submenu.focus();
                }
            }
            
            // Flecha izquierda: cerrar submenú
            if (e.key === 'ArrowLeft' && this.closest('.sub-menu')) {
                e.preventDefault();
                const parentItem = this.closest('.menu-item-has-children');
                if (parentItem) {
                    parentItem.classList.remove('is-open');
                    parentItem.querySelector('a').focus();
                }
            }
            
            // Flecha abajo: siguiente enlace
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (menuLinks[index + 1]) {
                    menuLinks[index + 1].focus();
                }
            }
            
            // Flecha arriba: enlace anterior
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (menuLinks[index - 1]) {
                    menuLinks[index - 1].focus();
                }
            }
            
            // Escape: cerrar submenú
            if (e.key === 'Escape' && this.closest('.sub-menu')) {
                const parentItem = this.closest('.menu-item-has-children');
                if (parentItem) {
                    parentItem.classList.remove('is-open');
                    parentItem.querySelector('a').focus();
                }
            }
        });
    });

})();
