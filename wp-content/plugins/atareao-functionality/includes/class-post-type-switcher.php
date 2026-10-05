<?php
/**
 * Post Type Switcher
 *
 * Absorbe en el plugin la funcionalidad del plugin de terceros «Post Type
 * Switcher» para poder desinstalarlo con paridad funcional. Permite cambiar el
 * tipo de un post desde el editor de bloques, el editor clásico, la edición
 * rápida y la edición masiva, con columna «Type», endpoint AJAX propio, cambio
 * de tipo al guardar y memoria del tipo original y anterior.
 *
 * Módulo `\Atareao\PostTypeSwitcher`. Conserva palabra por palabra los hooks,
 * meta, nonces, parámetros y la acción AJAX del original para que el relevo sea
 * drop-in: filtros `pts_post_type_filter`, `pts_get_post_types_filter` y
 * `pts_allowed_pages`; acciones `post_type_switcher` y `post_type_after_switch`;
 * meta `pts_original_type` y `pts_previous_type`; nonce `post-type-selector`
 * (campo `pts-nonce-select`); parámetros GET `pts_post_type` y `post_id`; y la
 * acción AJAX `post_type_switcher`.
 *
 * Adaptaciones respecto al original:
 * - No se porta el aviso de patrocinio ni `filter_plugin_action_links`.
 * - No se porta la integración con WPML (`wpml_sync_type`) porque el sitio no es
 *   multilingüe.
 * - No se recarga el textdomain: el plugin ya lo carga en `plugins_loaded`.
 * - Las entradas de `$_GET`/`$_REQUEST` se sanean antes de usarse.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cambio de tipo de post con paridad respecto al plugin original.
 */
class PostTypeSwitcher
{
    /**
     * Instancia única que se entrega como argumento de la acción
     * `post_type_switcher` (los callbacks son estáticos y no disponen de
     * `$this`).
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Inicializar: solo engancha hooks. Idempotente.
     *
     * @return void
     */
    public static function init()
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }
        $initialized = true;

        add_action('admin_init', array(__CLASS__, 'adminInit'));
        add_action('admin_init', array(__CLASS__, 'adminDone'));
    }

    /**
     * Devuelve la instancia única del módulo.
     *
     * @return self
     */
    private static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicialización de admin.
     *
     * Solo actúa en páginas permitidas. Registra las columnas por cada tipo
     * conmutable y todos los hooks de paridad con el original.
     *
     * @return void
     */
    public static function adminInit()
    {
        if (!self::isAllowedPage()) {
            return;
        }

        // Columna «Type» (soporte de edición rápida) por cada tipo conmutable.
        $postTypeNames = self::getPostTypes('names');
        if (!empty($postTypeNames)) {
            foreach ($postTypeNames as $name) {
                add_filter("manage_{$name}_posts_columns", array(__CLASS__, 'addColumn'));
                add_action("manage_{$name}_posts_custom_column", array(__CLASS__, 'manageColumn'), 10, 2);
            }
        }

        // La columna «post_type» se oculta por defecto.
        add_filter('default_hidden_columns', array(__CLASS__, 'defaultHiddenColumns'));

        // UI del editor clásico y de la lista.
        add_action('admin_head', array(__CLASS__, 'adminHead'));
        add_action('post_submitbox_misc_actions', array(__CLASS__, 'metabox'));
        add_action('quick_edit_custom_box', array(__CLASS__, 'quickEdit'));
        add_action('bulk_edit_custom_box', array(__CLASS__, 'quickEditBulk'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'quickEditScript'));

        // UI del editor de bloques.
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'blockEditorAssets'));

        // Endpoint AJAX.
        add_action('wp_ajax_post_type_switcher', array(__CLASS__, 'handleAjax'));

        // Override del tipo al insertar en el área de admin, cuando se pide.
        add_filter('wp_insert_attachment_data', array(__CLASS__, 'overrideType'), 10, 2);
        add_filter('wp_insert_post_data', array(__CLASS__, 'overrideType'), 10, 2);
    }

    /**
     * Admin inicializado.
     *
     * Dispara la acción `post_type_switcher` en cada petición de admin (no solo
     * en las páginas permitidas), igual que el original.
     *
     * @return void
     */
    public static function adminDone()
    {
        do_action('post_type_switcher', self::instance());
    }

    /**
     * Pinta el bloque del selector de tipo en el editor clásico.
     *
     * @return void
     */
    public static function metabox()
    {
        $postTypes = self::getPostTypes();
        $postType  = get_post_type();
        $cptObject = get_post_type_object($postType);

        if (!($cptObject instanceof \WP_Post_Type)) {
            return;
        }

        // Fuerza el tipo actual aunque no cumpla los criterios de visibilidad o
        // permiso, para representar el tipo de partida.
        if (!in_array($cptObject, $postTypes, true)) {
            $postTypes[$postType] = $cptObject;
        }
        ?>
        <div class="misc-pub-section misc-pub-section-last post-type-switcher">
            <label for="pts_post_type"><?php esc_html_e('Post Type:', 'atareao-functionality'); ?></label>
            <span id="post-type-display"><?php echo esc_html($cptObject->labels->singular_name); ?></span>

            <?php if (current_user_can($cptObject->cap->publish_posts)) : ?>
                <a href="#" id="edit-post-type-switcher" class="hide-if-no-js"><?php
                    esc_html_e('Edit', 'atareao-functionality');
                ?></a>
                <div id="post-type-select">
                    <select name="pts_post_type" id="pts_post_type">
                        <?php foreach ($postTypes as $_postType => $pt) : ?>
                            <?php if (!current_user_can($pt->cap->publish_posts)) : ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <?php
                                echo '<option value="' . esc_attr($pt->name) . '" ';
                                selected($postType, $_postType);
                                echo '>' . esc_html($pt->labels->singular_name) . '</option>';
                            ?>
                        <?php endforeach; ?>
                    </select>
                    <a href="#" id="save-post-type-switcher" class="hide-if-no-js button"><?php
                        esc_html_e('OK', 'atareao-functionality');
                    ?></a>
                    <a href="#" id="cancel-post-type-switcher" class="hide-if-no-js"><?php
                        esc_html_e('Cancel', 'atareao-functionality');
                    ?></a>
                </div>
                <?php wp_nonce_field('post-type-selector', 'pts-nonce-select'); ?>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Añade la columna «Type».
     *
     * @param array $columns Columnas registradas.
     * @return array
     */
    public static function addColumn($columns = array())
    {
        if (empty($columns) || !is_array($columns)) {
            $columns = array();
        }

        $newColumn = array('post_type' => esc_html__('Type', 'atareao-functionality'));

        return array_merge($columns, $newColumn);
    }

    /**
     * Añade «post_type» a la lista de columnas ocultas por defecto.
     *
     * @param array $hidden Columnas ocultas.
     * @return array
     */
    public static function defaultHiddenColumns($hidden = array())
    {
        if (empty($hidden) || !is_array($hidden)) {
            $hidden = array();
        }

        $hidden[] = 'post_type';

        return $hidden;
    }

    /**
     * Pinta la columna «Type».
     *
     * @param string $columnName Nombre de la columna.
     * @param int    $postId     ID del post.
     * @return void
     */
    public static function manageColumn($columnName = '', $postId = 0)
    {
        if ('post_type' !== $columnName) {
            return;
        }

        $postType = get_post_type_object(get_post_type($postId));
        if (!($postType instanceof \WP_Post_Type)) {
            return;
        }

        echo '<span data-post-type="' . esc_attr($postType->name) . '">'
            . esc_html($postType->labels->singular_name) . '</span>';
    }

    /**
     * Añade el selector de tipo a la caja de edición rápida.
     *
     * @param string $columnName Nombre de la columna.
     * @return void
     */
    public static function quickEdit($columnName = '')
    {
        if ('post_type' !== $columnName) {
            return;
        }
        ?>
        <div id="pts_quick_edit" class="inline-edit-group wp-clearfix">
            <label class="alignleft">
                <span class="title"><?php esc_html_e('Post Type', 'atareao-functionality'); ?></span>
                <?php
                wp_nonce_field('post-type-selector', 'pts-nonce-select');
                self::selectBox();
                ?>
            </label>
        </div>
        <?php
    }

    /**
     * Añade el selector de tipo a la caja de edición masiva.
     *
     * @param string $columnName Nombre de la columna.
     * @return void
     */
    public static function quickEditBulk($columnName = '')
    {
        if ('post_type' !== $columnName) {
            return;
        }
        ?>
        <label id="pts_bulk_edit" class="alignleft">
            <span class="title"><?php esc_html_e('Post Type', 'atareao-functionality'); ?></span>
            <?php
            wp_nonce_field('post-type-selector', 'pts-nonce-select');
            self::selectBox(true);
            ?>
        </label>
        <?php
    }

    /**
     * Pinta el `<select>` de tipos conmutables.
     *
     * @param bool $bulk Si es la edición masiva, añade la opción «No Change».
     * @return void
     */
    public static function selectBox($bulk = false)
    {
        $postTypes = self::getPostTypes();
        $postType  = get_post_type();

        echo '<select name="pts_post_type" id="pts_post_type">';

        if (true === $bulk) {
            echo '<option value="-1">'
                . esc_html__('&mdash; No Change &mdash;', 'atareao-functionality') . '</option>';
        }

        foreach ($postTypes as $_postType => $pt) {
            if (!current_user_can($pt->cap->publish_posts)) {
                continue;
            }

            $selected = (false === $bulk) ? selected($postType, $_postType, false) : '';
            echo '<option value="' . esc_attr($pt->name) . '"' . $selected . '>'
                . esc_html($pt->labels->singular_name) . '</option>';
        }

        echo '</select>';
    }

    /**
     * Encola el script de edición rápida/masiva, solo en `edit.php`.
     *
     * @param string $hook Hook de la pantalla actual.
     * @return void
     */
    public static function quickEditScript($hook = '')
    {
        if ('edit.php' !== $hook) {
            return;
        }

        wp_enqueue_script(
            'atareao-pts-quickedit',
            ATAREAO_PLUGIN_URL . 'assets/js/post-type-switcher-quickedit.js',
            array('jquery'),
            ATAREAO_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Encola el script del editor de bloques y localiza sus datos.
     *
     * No se encola si el usuario no puede publicar el tipo actual o no hay
     * tipos conmutables.
     *
     * @return void
     */
    public static function blockEditorAssets()
    {
        $currentPostType       = get_post_type();
        $currentPostTypeObject = get_post_type_object($currentPostType);

        if (!($currentPostTypeObject instanceof \WP_Post_Type)) {
            return;
        }

        if (!current_user_can($currentPostTypeObject->cap->publish_posts)) {
            return;
        }

        $switchableTypes = self::getPostTypes();
        if (empty($switchableTypes)) {
            return;
        }

        $availablePostTypes = array();
        foreach ($switchableTypes as $postType) {
            if (!current_user_can($postType->cap->publish_posts)) {
                continue;
            }
            $availablePostTypes[] = array(
                'value' => $postType->name,
                'label' => $postType->labels->singular_name,
            );
        }

        $changeUrl = add_query_arg(
            array(
                'action'           => 'post_type_switcher',
                'pts-nonce-select' => wp_create_nonce('post-type-selector'),
                'post_id'          => get_the_ID(),
            ),
            admin_url('admin-ajax.php')
        );

        wp_enqueue_script(
            'atareao-pts-block',
            ATAREAO_PLUGIN_URL . 'assets/js/post-type-switcher-block.js',
            array('wp-components', 'wp-element', 'wp-i18n', 'wp-plugins', 'wp-editor', 'wp-edit-post'),
            ATAREAO_PLUGIN_VERSION,
            false
        );

        wp_localize_script(
            'atareao-pts-block',
            'atareaoPts',
            array(
                'availablePostTypes'   => $availablePostTypes,
                'currentPostType'      => $currentPostType,
                'currentPostTypeLabel' => $currentPostTypeObject->labels->singular_name,
                'changeUrl'            => $changeUrl,
            )
        );
    }

    /**
     * Endpoint AJAX para cambiar el tipo de un post.
     *
     * Usa `$_GET` a propósito, para evitar colisiones con la petición de
     * guardado del editor.
     *
     * @return void
     */
    public static function handleAjax()
    {
        $rawPostId   = isset($_GET['post_id']) ? wp_unslash($_GET['post_id']) : '';
        $rawPostType = isset($_GET['pts_post_type']) ? wp_unslash($_GET['pts_post_type']) : '';
        $rawNonce    = isset($_GET['pts-nonce-select']) ? wp_unslash($_GET['pts-nonce-select']) : '';

        // Bail si faltan datos.
        if (empty($rawPostId) || empty($rawPostType) || empty($rawNonce)) {
            wp_die(esc_html__('Missing data.', 'atareao-functionality'));
        }

        $postId           = absint($rawPostId);
        $postType         = sanitize_key($rawPostType);
        $nonce            = sanitize_text_field($rawNonce);
        $postTypeObject   = get_post_type_object($postType);

        // Bail si el tipo destino no existe, el nonce no verifica, o el usuario
        // no puede editar el post o publicar el tipo destino.
        $isInvalid = !($postTypeObject instanceof \WP_Post_Type)
            || !wp_verify_nonce($nonce, 'post-type-selector')
            || !current_user_can('edit_post', $postId)
            || !current_user_can($postTypeObject->cap->publish_posts);

        if ($isInvalid) {
            wp_die(esc_html__('Sorry, you are not allowed to edit this post.', 'atareao-functionality'));
        }

        // Tipo de partida, para la acción posterior.
        $originalPostType = get_post_type($postId);

        // Cambia el tipo y guarda la memoria en meta.
        self::setPostType($postId, $postType);

        /**
         * Permite acciones después del cambio de tipo.
         *
         * @since 1.0.0
         *
         * @param string $postType         Tipo nuevo.
         * @param string $originalPostType Tipo anterior.
         * @param int    $postId           ID del post.
         */
        do_action('post_type_after_switch', $postType, $originalPostType, $postId);

        wp_safe_redirect(get_edit_post_link($postId, 'raw'));
        exit;
    }

    /**
     * Override del `post_type` en `wp_insert_post()`.
     *
     * Se aplican todas las salvaguardas para cambiar el tipo solo cuando el
     * usuario lo pide de forma explícita: no en autosave, nonce válido,
     * capacidades, campo presente, tipo destino existente y distinto, no
     * revisión y coincidencia con el ID que se está guardando.
     *
     * @param array $data    Datos depurados del post.
     * @param array $postarr Array sin depurar del post.
     * @return array Datos, posiblemente con el tipo modificado.
     */
    public static function overrideType($data = array(), $postarr = array())
    {
        $rawPostType = isset($_REQUEST['pts_post_type']) ? wp_unslash($_REQUEST['pts_post_type']) : '';
        $rawNonce    = isset($_REQUEST['pts-nonce-select']) ? wp_unslash($_REQUEST['pts-nonce-select']) : '';

        // Bail si falta el campo del formulario.
        if (empty($rawPostType) || empty($rawNonce)) {
            return $data;
        }

        // Bail si no se está guardando un ID concreto.
        if (empty($postarr['post_ID'])) {
            return $data;
        }

        $postId         = absint($postarr['post_ID']);
        $postType       = sanitize_key($rawPostType);
        $postTypeObject = get_post_type_object($postType);
        $currentType    = isset($data['post_type']) ? $data['post_type'] : '';

        // Bail si el post o el tipo destino están vacíos o no existen.
        if (empty($postId) || empty($postType) || !($postTypeObject instanceof \WP_Post_Type)) {
            return $data;
        }

        // Bail si no hay cambio.
        if ($postType === $currentType) {
            return $data;
        }

        // Bail si el ID enviado no coincide con el que se está cambiando (evita
        // alterar posts hijos o relacionados).
        if ($postId !== $postarr['ID']) {
            return $data;
        }

        // Bail si el usuario no puede editar el post actual.
        if (!current_user_can('edit_post', $postarr['ID'])) {
            return $data;
        }

        // Bail si el usuario no puede publicar el tipo destino.
        if (!current_user_can($postTypeObject->cap->publish_posts)) {
            return $data;
        }

        // Bail si el nonce no verifica.
        if (!wp_verify_nonce(sanitize_text_field($rawNonce), 'post-type-selector')) {
            return $data;
        }

        // Bail en autosave o revisión.
        if (wp_is_post_autosave($postarr['ID'])) {
            return $data;
        }
        if (wp_is_post_revision($postarr['ID'])) {
            return $data;
        }

        $postarrType = isset($postarr['post_type']) ? $postarr['post_type'] : '';
        if (in_array($postarrType, array($postType, 'revision'), true)) {
            return $data;
        }

        // Cambia el tipo.
        $data['post_type'] = $postType;

        /**
         * Permite acciones después del cambio de tipo.
         *
         * @since 1.0.0
         *
         * @param string $postType   Tipo nuevo.
         * @param string $postarrType Tipo anterior.
         * @param int    $postId     ID del post.
         */
        do_action('post_type_after_switch', $postType, $postarrType, $postarr['ID']);

        return $data;
    }

    /**
     * Añade el JS y el CSS necesarios a la cabecera de admin.
     *
     * @return void
     */
    public static function adminHead()
    {
        ?>
        <script type="text/javascript">
            jQuery( document ).ready( function( $ ) {
                jQuery( '.misc-pub-section.curtime.misc-pub-section-last' ).removeClass( 'misc-pub-section-last' );
                jQuery( '#edit-post-type-switcher' ).on( 'click', function(e) {
                    jQuery( this ).hide();
                    jQuery( '#post-type-select' ).slideDown();
                    e.preventDefault();
                });
                jQuery( '#save-post-type-switcher' ).on( 'click', function(e) {
                    jQuery( '#post-type-select' ).slideUp();
                    jQuery( '#edit-post-type-switcher' ).show();
                    jQuery( '#post-type-display' ).text( jQuery( '#pts_post_type :selected' ).text() );
                    e.preventDefault();
                });
                jQuery( '#cancel-post-type-switcher' ).on( 'click', function(e) {
                    jQuery( '#post-type-select' ).slideUp();
                    jQuery( '#edit-post-type-switcher' ).show();
                    e.preventDefault();
                });
            });
        </script>
        <style type="text/css">
            #wpbody-content .inline-edit-row .inline-edit-col-right .alignleft + .alignleft {
                float: right;
            }
            #post-type-select {
                line-height: 2.5em;
                margin-top: 3px;
                display: none;
            }
            #post-type-select select#pts_post_type {
                margin-right: 2px;
            }
            #post-type-select a#save-post-type-switcher {
                vertical-align: middle;
                margin-right: 2px;
            }
            #post-type-display {
                font-weight: bold;
            }
            #post-body .post-type-switcher::before {
                content: '\f109';
                font: 400 20px/1 dashicons;
                speak: never;
                display: inline-block;
                padding: 0 2px 0 0;
                top: 0;
                left: -1px;
                position: relative;
                vertical-align: top;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
                text-decoration: none !important;
                color: #888;
            }
            .wp-list-table .column-post_type {
                width: 10%;
            }
            .edit-post-post-type {
                display: flex;
                -webkit-box-align: center;
                align-items: center;
                flex-direction: row;
                gap: calc(8px);
                -webkit-box-pack: justify;
                justify-content: space-between;
                margin-top: -8px;
                width: 100%;
            }
            .edit-post-post-type span {
                display: inline-block;
                flex-shrink: 0;
                padding: 6px 0;
                width: 45%;
            }
            .components-button.edit-post-post-type__toggle {
                height: auto;
                text-align: left;
                white-space: normal;
                word-break: break-word;
            }
            .editor-post-type__dialog-fieldset {
                margin: 8px;
                min-width: 248px;
            }
            .editor-post-type__dialog-fieldset .editor-post-type__dialog-legend {
                line-height: 1.2;
                margin-top: 0px;
                margin-bottom: 16px;
                color: rgb(30, 30, 30);
                font-size: calc(13px);
                font-weight: 600;
                display: block;
            }
            .editor-post-type__dialog-fieldset .editor-post-type__choice {
                margin: 8px;
                display: block;
            }
            .editor-post-type__dialog-fieldset .editor-post-type__choice:last-child {
                margin-bottom: 0;
            }
            .editor-post-type__dialog-fieldset .editor-post-type__dialog-radio[type=radio] {
                display: inline-block;
            }
            .editor-post-type__dialog-fieldset .editor-post-type__dialog-label {
                margin: -3px 0 0 8px;
                display: inline-block;
            }
        </style>
        <?php
    }

    /**
     * ¿Está permitida la página actual para cargar el switcher?
     *
     * @return bool
     */
    private static function isAllowedPage()
    {
        $action = (isset($_REQUEST['action']) && is_string($_REQUEST['action']))
            ? sanitize_key(wp_unslash($_REQUEST['action']))
            : '';

        $isAjax = wp_doing_ajax() && in_array($action, array('inline-save', 'post_type_switcher'), true);

        if (!is_blog_admin() && !$isAjax) {
            return false;
        }

        /**
         * Nombres de fichero de las páginas de admin permitidas.
         *
         * @since 1.0.0
         *
         * @param array $pages Nombres de fichero.
         */
        $pages = apply_filters('pts_allowed_pages', array('post.php', 'edit.php', 'admin-ajax.php'));

        $pagenow = isset($GLOBALS['pagenow']) ? $GLOBALS['pagenow'] : '';

        return (bool) in_array($pagenow, (array) $pages, true);
    }

    /**
     * Fija el `post_type` de un ID y guarda la memoria del tipo anterior.
     *
     * Guarda `pts_original_type` solo la primera vez, `pts_previous_type` con el
     * tipo inmediatamente anterior, y borra cada meta al revertir al tipo
     * correspondiente.
     *
     * @param int    $postId   ID del post.
     * @param string $postType Tipo destino.
     * @return int Número de filas actualizadas (1 o 0).
     */
    private static function setPostType($postId = 0, $postType = '')
    {
        // Tipo actual (queda como anterior tras el cambio).
        $current = get_post_type($postId);

        // Tipos original y anterior ya memorizados.
        $original = get_post_meta($postId, 'pts_original_type', true);
        $previous = get_post_meta($postId, 'pts_previous_type', true);

        // Intenta fijar el tipo.
        $retval = set_post_type($postId, $postType);

        if (!empty($retval)) {
            // El tipo original solo se guarda una vez.
            if (empty($original)) {
                add_post_meta($postId, 'pts_original_type', $current);

            // Solo se borra la meta original al revertir a ese tipo.
            } elseif ($postType === $original) {
                delete_post_meta($postId, 'pts_original_type');
            }

            // Actualiza el tipo anterior.
            if ($postType !== $previous) {
                update_post_meta($postId, 'pts_previous_type', $current);

            // Solo se borra la meta anterior al revertir a ese tipo.
            } else {
                delete_post_meta($postId, 'pts_previous_type');
            }
        }

        return $retval;
    }

    /**
     * Tipos conmutables (objetos o nombres), según los argumentos.
     *
     * Excluye `attachment` porque su soporte está roto en el original.
     *
     * @param string $output Salida de `get_post_types()`: `names` u `objects`.
     * @return array
     */
    private static function getPostTypes($output = 'objects')
    {
        $types = get_post_types(self::getPostTypeArgs(), $output);

        if (isset($types['attachment'])) {
            unset($types['attachment']);
        }

        /**
         * Filtra los tipos conmutables.
         *
         * @since 4.0.0
         *
         * @param array  $types  Tipos (normalmente objetos).
         * @param string $output Salida devuelta.
         * @return array
         */
        return (array) apply_filters('pts_get_post_types_filter', $types, $output);
    }

    /**
     * Argumentos usados para acotar los tipos conmutables.
     *
     * @return array
     */
    private static function getPostTypeArgs()
    {
        $args = array(
            'public'  => true,
            'show_ui' => true,
        );

        /**
         * Filtra los argumentos que recibe `get_post_types()`.
         *
         * @since 1.0.0
         *
         * @param array $args Argumentos.
         * @return array
         */
        return (array) apply_filters('pts_post_type_filter', $args);
    }
}
