<?php
/**
 * Matrix API Configuration Page
 * @package Atareao_Theme
 */

add_action('admin_menu', 'atareao_matrix_config_menu');
function atareao_matrix_config_menu() {
    add_options_page(
        __('Matrix API Configuración', 'atareao-theme'),
        __('Matrix API', 'atareao-theme'),
        'manage_options',
        'atareao-matrix-config',
        'atareao_matrix_config_page'
    );
}

function atareao_matrix_config_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['atareao_matrix_save'])) {
        update_option('atareao_matrix_url', sanitize_text_field($_POST['atareao_matrix_url']));
        update_option('atareao_matrix_token', sanitize_text_field($_POST['atareao_matrix_token']));
        update_option('atareao_matrix_room', sanitize_text_field($_POST['atareao_matrix_room']));
        echo '<div class="updated"><p>' . esc_html__('Configuración guardada.', 'atareao-theme') . '</p></div>';
    }
    $url = get_option('atareao_matrix_url', '');
    $token = get_option('atareao_matrix_token', '');
    $room = get_option('atareao_matrix_room', '');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Configuración Matrix API', 'atareao-theme'); ?></h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th><label for="atareao_matrix_url">Matrix URL</label></th>
                    <td><input type="text" id="atareao_matrix_url" name="atareao_matrix_url" value="<?php echo esc_attr($url); ?>" style="width:400px;"></td>
                </tr>
                <tr>
                    <th><label for="atareao_matrix_token">Access Token</label></th>
                    <td><input type="text" id="atareao_matrix_token" name="atareao_matrix_token" value="<?php echo esc_attr($token); ?>" style="width:400px;"></td>
                </tr>
                <tr>
                    <th><label for="atareao_matrix_room">Room ID</label></th>
                    <td><input type="text" id="atareao_matrix_room" name="atareao_matrix_room" value="<?php echo esc_attr($room); ?>" style="width:400px;"></td>
                </tr>
                
            </table>
            <p><input type="submit" name="atareao_matrix_save" class="button button-primary" value="<?php esc_attr_e('Guardar', 'atareao-theme'); ?>"></p>
        </form>
    </div>
    <?php
}
