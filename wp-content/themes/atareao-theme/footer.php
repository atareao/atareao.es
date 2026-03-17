    </main>

    <footer class="site-footer">
        <div class="footer-content">
            <?php
            if (is_active_sidebar('footer-1') || is_active_sidebar('footer-2')) {
                ?>
                <div class="footer-widgets">
                    <?php if (is_active_sidebar('footer-1')) : ?>
                        <div class="footer-widget-area">
                            <?php dynamic_sidebar('footer-1'); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (is_active_sidebar('footer-2')) : ?>
                        <div class="footer-widget-area">
                            <?php dynamic_sidebar('footer-2'); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }
            
            // Menú del footer
            if (has_nav_menu('footer')) {
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'menu_id'        => 'footer-menu',
                    'container'      => 'nav',
                    'container_class' => 'footer-navigation',
                    'depth'          => 1,
                ));
            }
            ?>
            
            <div class="site-info">
                <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. 
                <?php _e('Todos los derechos reservados.', 'atareao-theme'); ?>
                </p>
            </div>
        </div>
    </footer>
</div>

<!-- Botón flotante para subir -->
<button id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e('Volver arriba', 'atareao-theme'); ?>" title="<?php esc_attr_e('Volver arriba', 'atareao-theme'); ?>">
    <span class="dashicons dashicons-arrow-up-alt2"></span>
</button>

<?php wp_footer(); ?>
</body>
</html>
