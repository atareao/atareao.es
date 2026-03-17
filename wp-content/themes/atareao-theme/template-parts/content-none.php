<?php
/**
 * Template part cuando no hay contenido
 *
 * @package Atareao_Theme
 */
?>

<section class="no-results not-found">
    <header class="entry-header">
        <h1 class="entry-title"><?php _e('No se encontró nada', 'atareao-theme'); ?></h1>
    </header>

    <div class="entry-content">
        <?php
        if (is_home() && current_user_can('publish_posts')) :
            ?>
            <p>
                <?php
                printf(
                    wp_kses(
                        __('¿Listo para publicar tu primera entrada? <a href="%1$s">Empieza aquí</a>.', 'atareao-theme'),
                        array(
                            'a' => array(
                                'href' => array(),
                            ),
                        )
                    ),
                    esc_url(admin_url('post-new.php'))
                );
                ?>
            </p>
            <?php
        elseif (is_search()) :
            ?>
            <p><?php _e('Lo sentimos, pero no se encontraron resultados para tu búsqueda. Intenta con otras palabras clave.', 'atareao-theme'); ?></p>
            <?php
            get_search_form();
        else :
            ?>
            <p><?php _e('Parece que no pudimos encontrar lo que buscas. Quizás una búsqueda pueda ayudar.', 'atareao-theme'); ?></p>
            <?php
            get_search_form();
        endif;
        ?>
    </div>
</section>
