<?php
/**
 * Formulario de búsqueda
 *
 * @package Atareao_Theme
 */
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label>
        <span class="screen-reader-text"><?php _e('Buscar:', 'atareao-theme'); ?></span>
        <input type="search" 
               class="search-field" 
               placeholder="<?php echo esc_attr_x('Buscar...', 'placeholder', 'atareao-theme'); ?>" 
               value="<?php echo get_search_query(); ?>" 
               name="s" />
    </label>
    <button type="submit" class="search-submit">
        <span class="screen-reader-text"><?php _e('Buscar', 'atareao-theme'); ?></span>
        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M19 19L13 13M15 8C15 11.866 11.866 15 8 15C4.13401 15 1 11.866 1 8C1 4.13401 4.13401 1 8 1C11.866 1 15 4.13401 15 8Z" 
                  stroke="currentColor" 
                  stroke-width="2" 
                  stroke-linecap="round" 
                  stroke-linejoin="round"/>
        </svg>
    </button>
</form>
