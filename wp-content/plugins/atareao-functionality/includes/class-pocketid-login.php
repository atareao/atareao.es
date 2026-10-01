<?php
/**
 * Pocket ID OIDC Login
 *
 * Autenticación exclusiva con Pocket ID (passwordless con passkeys/WebAuthn).
 * Flujo OIDC Authorization Code + PKCE (S256) con descubrimiento de endpoints,
 * validación de state anti-CSRF y sin creación automática de usuarios.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class PocketIDLogin
{
    const OPTION_URL = 'atareao_pocketid_url';
    const OPTION_CLIENT_ID = 'atareao_pocketid_client_id';
    const OPTION_CLIENT_SECRET = 'atareao_pocketid_client_secret';
    const OPTION_ENFORCE = 'atareao_pocketid_enforce';
    const TRANSIENT_CONFIG = 'atareao_pocketid_oidc_config';
    private const TRANSIENT_STATE_PREFIX = 'atareao_pid_state_';
    const COOKIE_OAUTH = 'atareao_pocketid_oauth';
    const OAUTH_COOKIE_TTL = 300;

    /**
     * Acciones nativas de wp-login.php que nunca se redirigen a Pocket ID.
     * Garantizan logout correcto, recuperación de emergencia por email
     * (lostpassword/rp/resetpass) y formularios de contenido protegido.
     */
    private static $native_actions = array(
        'logout',
        'postpass',
        'lostpassword',
        'checkemail',
        'confirmaction',
        'rp',
        'resetpass',
        'register',
    );

    /**
     * Inicializar hooks del módulo
     */
    public static function init()
    {
        add_action('login_init', array(__CLASS__, 'handleLoginFlow'));
        add_filter('authenticate', array(__CLASS__, 'blockPasswordLogin'), 30, 3);
        add_action('login_footer', array(__CLASS__, 'renderLoginFooter'));
        add_action('admin_menu', array(__CLASS__, 'addSettingsPage'));
    }

    /**
     * ¿Está la configuración mínima completa?
     *
     * @return bool
     */
    public static function isConfigured()
    {
        $url = get_option(self::OPTION_URL, '');
        $client_id = get_option(self::OPTION_CLIENT_ID, '');
        $client_secret = get_option(self::OPTION_CLIENT_SECRET, '');

        return '' !== $url && '' !== $client_id && '' !== $client_secret;
    }

    /**
     * Punto de entrada del flujo OIDC en wp-login.php
     *
     * Orden estricto de decisiones:
     * 1. Acciones nativas permitidas -> flujo nativo.
     * 2. Usuario ya autenticado -> no interfiere.
     * 3. Configuración incompleta -> no interfiere.
     * 4. Parámetro `code` presente -> callback de retorno.
     * 5. Botón explícito `action=pocketid` -> inicia flujo.
     * 6. Modo "Exigir PocketID" -> inicia flujo.
     * 7. Cualquier otro caso -> login nativo intacto.
     */
    public static function handleLoginFlow()
    {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        if (in_array($action, self::$native_actions, true)) {
            return;
        }
        if (is_user_logged_in()) {
            return;
        }
        if (!self::isConfigured()) {
            return;
        }
        if (isset($_GET['code'])) {
            self::handleCallback();
            return;
        }
        if ('pocketid' === $action) {
            self::startFlow(isset($_GET['redirect_to']) ? wp_sanitize_redirect($_GET['redirect_to']) : '');
            return;
        }
        if ('1' === get_option(self::OPTION_ENFORCE, '0')) {
            self::startFlow(isset($_GET['redirect_to']) ? wp_sanitize_redirect($_GET['redirect_to']) : '');
            return;
        }
    }

    /**
     * Resolver endpoints OIDC desde el documento de descubrimiento.
     *
     * Descarga `{base}/.well-known/openid-configuration` (timeout 10 s,
     * verificación TLS), valida la presencia de los tres endpoints y cachea
     * el resultado 12 horas en un transient. Si la descarga falla, borra el
     * transient y reintenta una vez. Si persiste el fallo, usa rutas por
     * defecto derivadas de la base. El flag interno `source` indica el origen.
     *
     * El endpoint de autorización devuelto apunta SIEMPRE a la UI de Pocket ID
     * (`{base}/authorize`), nunca a la API interna `/api/oidc/authorize`.
     *
     * @param bool $force Si true, ignora la caché y vuelve a descargar.
     * @return array|false Array con authorization/token/userinfo endpoints o false.
     */
    public static function getOIDCConfig($force = false)
    {
        $base = rtrim((string) get_option(self::OPTION_URL, ''), '/');
        if ('' === $base) {
            return false;
        }

        if (!$force) {
            $cached = get_transient(self::TRANSIENT_CONFIG);
            if (is_array($cached)
                && !empty($cached['authorization_endpoint'])
                && !empty($cached['token_endpoint'])
                && !empty($cached['userinfo_endpoint'])) {
                return $cached;
            }
        }

        $config = self::fetchDiscoveryConfig($base);
        if (false === $config) {
            delete_transient(self::TRANSIENT_CONFIG);
            self::log('Discovery fallido, se reintenta la descarga.');
            $config = self::fetchDiscoveryConfig($base);
        }

        if (false === $config) {
            return array(
                'source' => 'fallback',
                'authorization_endpoint' => $base . '/authorize',
                'token_endpoint' => $base . '/api/oidc/token',
                'userinfo_endpoint' => $base . '/api/oidc/userinfo',
            );
        }

        set_transient(self::TRANSIENT_CONFIG, $config, 12 * HOUR_IN_SECONDS);
        return $config;
    }

    /**
     * Descargar y validar el documento de descubrimiento OIDC
     *
     * @param string $base URL base sin barra final.
     * @return array|false Endpoints validados o false en cualquier fallo.
     */
    private static function fetchDiscoveryConfig($base)
    {
        $url = $base . '/.well-known/openid-configuration';
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 10,
                'sslverify' => true,
            )
        );

        if (is_wp_error($response)) {
            self::log('Error de red en discovery: ' . $response->get_error_message());
            return false;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300 || !is_array($data)) {
            self::log('Discovery inválido (HTTP ' . $code . ', respuesta no parseable).');
            return false;
        }

        if (empty($data['authorization_endpoint'])
            || empty($data['token_endpoint'])
            || empty($data['userinfo_endpoint'])) {
            self::log('Discovery incompleto: faltan endpoints obligatorios.');
            return false;
        }

        // Salvaguarda: el endpoint de autorización debe apuntar a la UI
        // (${base}/authorize), nunca a la API interna del backend.
        if (false !== strpos($data['authorization_endpoint'], '/api/oidc/authorize')) {
            self::log('El discovery publicó la API interna como authorization_endpoint; se fuerza ' . $base . '/authorize.');
            $data['authorization_endpoint'] = $base . '/authorize';
        }

        // Validación de los endpoints detectados: esquema https y mismo host
        // que la URL base configurada (Pocket ID publica todos los endpoints
        // en su host público). Un fallo aquí cae en el fallback de rutas por
        // defecto seguras derivado de la propia base.
        $base_host = wp_parse_url($base, PHP_URL_HOST);
        $endpoint_keys = array(
            'authorization_endpoint' => $data['authorization_endpoint'],
            'token_endpoint' => $data['token_endpoint'],
            'userinfo_endpoint' => $data['userinfo_endpoint'],
        );
        foreach ($endpoint_keys as $key => $endpoint) {
            $endpoint_scheme = wp_parse_url($endpoint, PHP_URL_SCHEME);
            $endpoint_host = wp_parse_url($endpoint, PHP_URL_HOST);
            if ('https' !== $endpoint_scheme || $endpoint_host !== $base_host) {
                self::log('Endpoint ' . $key . ' del discovery no válido (https/host): ' . $endpoint);
                return false;
            }
        }

        return array(
            'source' => 'discovery',
            'authorization_endpoint' => $endpoint_keys['authorization_endpoint'],
            'token_endpoint' => $endpoint_keys['token_endpoint'],
            'userinfo_endpoint' => $endpoint_keys['userinfo_endpoint'],
        );
    }

    /**
     * Iniciar el flujo de autorización: state + PKCE + redirección 302.
     *
     * @param string $redirect_to_raw Destino deseado (sin validar todavía).
     */
    public static function startFlow($redirect_to_raw)
    {
        try {
            $state = bin2hex(random_bytes(16));
            $code_verifier = bin2hex(random_bytes(32));
        } catch (\Exception $e) {
            self::log('No se pudo generar material aleatorio: ' . $e->getMessage());
            wp_die(
                esc_html__('No se pudo iniciar el inicio de sesión. Inténtalo de nuevo más tarde.', 'atareao-functionality'),
                esc_html__('Error de inicio de sesión', 'atareao-functionality'),
                array('response' => 500)
            );
            return;
        }

        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
        $redirect_to = wp_validate_redirect(wp_sanitize_redirect($redirect_to_raw), admin_url());

        // Single-use server-side del state (anti-replay): el state solo es
        // válido una vez y expira a los 5 minutos, independientemente de que
        // el atacante replique la cookie.
        set_transient(
            self::TRANSIENT_STATE_PREFIX . hash('sha256', $state),
            $code_verifier,
            self::OAUTH_COOKIE_TTL
        );

        self::setOAuthCookie($state, $code_verifier, $redirect_to);

        $config = self::getOIDCConfig();
        if (false === $config || empty($config['authorization_endpoint'])) {
            self::log('No se pudo resolver el endpoint de autorización.');
            wp_die(
                esc_html__('No se pudo iniciar el inicio de sesión. Inténtalo de nuevo más tarde.', 'atareao-functionality'),
                esc_html__('Error de inicio de sesión', 'atareao-functionality'),
                array('response' => 503)
            );
            return;
        }

        $authorize_url = add_query_arg(
            array(
                'response_type' => 'code',
                'client_id' => get_option(self::OPTION_CLIENT_ID, ''),
                'redirect_uri' => wp_login_url(),
                'scope' => 'openid profile email',
                'state' => $state,
                'code_challenge' => $code_challenge,
                'code_challenge_method' => 'S256',
            ),
            $config['authorization_endpoint']
        );

        wp_redirect($authorize_url);
        exit;
    }

    /**
     * Procesar el callback OIDC (`?code`) en wp-login.php
     */
    public static function handleCallback()
    {
        $cookie_raw = isset($_COOKIE[self::COOKIE_OAUTH]) ? wp_unslash($_COOKIE[self::COOKIE_OAUTH]) : '';
        self::clearOAuthCookie();

        $saved = json_decode($cookie_raw, true);
        if (!is_array($saved) || empty($saved['state']) || empty($saved['code_verifier'])) {
            self::log('Callback sin cookie de estado válida.');
            self::dieGeneric403();
        }

        // Single-use server-side del state (anti-replay): se comprueba el
        // transient persistido en startFlow() y se consume de inmediato,
        // incluso si el flujo posterior falla.
        $state_key = self::TRANSIENT_STATE_PREFIX . hash('sha256', $saved['state']);
        $saved_verifier = get_transient($state_key);
        if (false === $saved_verifier || $saved_verifier !== $saved['code_verifier']) {
            if (false !== $saved_verifier) {
                delete_transient($state_key);
            }
            self::log('State no disponible o reutilizado (replay) en callback OIDC.');
            self::dieGeneric403();
        }
        delete_transient($state_key);

        $given_state = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
        if ('' === $given_state || !hash_equals((string) $saved['state'], $given_state)) {
            self::log('State mismatch en callback OIDC.');
            self::dieGeneric403();
        }

        $code = isset($_GET['code']) ? wp_unslash($_GET['code']) : '';
        if ('' === $code) {
            self::log('Callback sin parámetro code.');
            self::dieGeneric403();
        }

        $config = self::getOIDCConfig();
        if (false === $config
            || empty($config['token_endpoint'])
            || empty($config['userinfo_endpoint'])) {
            self::log('No se pudieron resolver los endpoints durante el callback.');
            wp_die(
                esc_html__('No se pudo completar el inicio de sesión. Inténtalo de nuevo más tarde.', 'atareao-functionality'),
                esc_html__('Error de inicio de sesión', 'atareao-functionality'),
                array('response' => 503)
            );
            return;
        }

        // Intercambio de code por tokens (client_secret_post).
        $token_response = wp_remote_post(
            $config['token_endpoint'],
            array(
                'timeout' => 15,
                'sslverify' => true,
                'body' => array(
                    'grant_type' => 'authorization_code',
                    'client_id' => get_option(self::OPTION_CLIENT_ID, ''),
                    'client_secret' => get_option(self::OPTION_CLIENT_SECRET, ''),
                    'redirect_uri' => wp_login_url(),
                    'code' => $code,
                    'code_verifier' => $saved['code_verifier'],
                ),
            )
        );

        if (is_wp_error($token_response)) {
            self::log('Error en token exchange: ' . $token_response->get_error_message());
            self::dieGenericTemporary();
        }

        $token_data = json_decode(wp_remote_retrieve_body($token_response), true);
        $token_code = intval(wp_remote_retrieve_response_code($token_response));
        if ($token_code < 200 || $token_code >= 300 || !is_array($token_data) || empty($token_data['access_token'])) {
            self::log('Token endpoint rechazó el code (HTTP ' . $token_code . ').');
            self::dieGenericTemporary();
        }

        // Userinfo con el access_token.
        $userinfo_response = wp_remote_get(
            $config['userinfo_endpoint'],
            array(
                'timeout' => 15,
                'sslverify' => true,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token_data['access_token'],
                ),
            )
        );

        if (is_wp_error($userinfo_response)) {
            self::log('Error en userinfo: ' . $userinfo_response->get_error_message());
            self::dieGenericTemporary();
        }

        $userinfo = json_decode(wp_remote_retrieve_body($userinfo_response), true);
        $userinfo_code = intval(wp_remote_retrieve_response_code($userinfo_response));
        if ($userinfo_code < 200 || $userinfo_code >= 300 || !is_array($userinfo)) {
            self::log('Userinfo endpoint devolvió una respuesta inválida (HTTP ' . $userinfo_code . ').');
            self::dieGenericTemporary();
        }

        $email = isset($userinfo['email']) ? sanitize_email($userinfo['email']) : '';
        if (!is_email($email)) {
            self::log('Userinfo sin email válido.');
            self::dieGeneric403();
        }

        if (array_key_exists('email_verified', $userinfo)) {
            $verified = $userinfo['email_verified'];
            if (false === $verified || 0 === $verified || 'false' === $verified || '0' === $verified) {
                self::log('Email no verificado para: ' . $email);
                self::dieGeneric403();
            }
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            self::log('Email autenticado sin usuario WP registrado: ' . $email);
            wp_die(
                esc_html__('Acceso denegado: el usuario no está registrado en este sitio.', 'atareao-functionality'),
                esc_html__('Acceso denegado', 'atareao-functionality'),
                array('response' => 403, 'back_link' => true)
            );
            return;
        }

        // Sesión limpia (anti session fixation) y establecimiento de la
        // sesión de WordPress.
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        $redirect_to = !empty($saved['redirect_to']) ? $saved['redirect_to'] : admin_url();
        wp_safe_redirect(wp_validate_redirect($redirect_to, admin_url()));
        exit;
    }

    /**
     * Guardar la cookie de estado OIDC (HttpOnly + Secure + SameSite=Lax, TTL 5 min)
     *
     * @param string $state         State aleatorio.
     * @param string $code_verifier Code verifier PKCE.
     * @param string $redirect_to   Destino validado tras el login.
     */
    private static function setOAuthCookie($state, $code_verifier, $redirect_to)
    {
        $value = wp_json_encode(
            array(
                'state' => $state,
                'code_verifier' => $code_verifier,
                'redirect_to' => $redirect_to,
            )
        );
        setcookie(self::COOKIE_OAUTH, $value, self::oauthCookieOptions(time() + self::OAUTH_COOKIE_TTL));
    }

    /**
     * Borrar la cookie de estado OIDC de inmediato
     */
    private static function clearOAuthCookie()
    {
        if (isset($_COOKIE[self::COOKIE_OAUTH])) {
            setcookie(self::COOKIE_OAUTH, '', self::oauthCookieOptions(time() - HOUR_IN_SECONDS));
        }
    }

    /**
     * Opciones de la cookie de estado (PHP 7.3+)
     *
     * @param int $expires Timestamp de expiración.
     * @return array
     */
    private static function oauthCookieOptions($expires)
    {
        return array(
            'expires' => $expires,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => 'https' === wp_parse_url(wp_login_url(), PHP_URL_SCHEME),
            'httponly' => true,
            'samesite' => 'Lax',
        );
    }

    /**
     * Bloquear el formulario de contraseña de wp-login.php.
     *
     * Solo cuando la configuración está completa, el toggle "Exigir PocketID"
     * está activo y se detecta el envío del formulario HTML (wp-submit + log +
     * pwd + acción vacía). Nunca interfiere con application passwords,
     * XML-RPC ni la autenticación REST.
     *
     * @param mixed  $user     WP_User|WP_Error|null.
     * @param string $username Nombre de usuario enviado.
     * @param string $password Contraseña enviada.
     * @return mixed
     */
    public static function blockPasswordLogin($user, $username, $password)
    {
        if ('1' === get_option(self::OPTION_ENFORCE, '0')
            && self::isConfigured()
            && isset($_POST['wp-submit'])
            && isset($_POST['log'])
            && isset($_POST['pwd'])
            && empty($_POST['action'])) {
            self::log('Intento de login por contraseña bloqueado (modo exigir activo).');
            return new WP_Error(
                'pocketid_required',
                __('El inicio de sesión con contraseña está deshabilitado. Usa «Iniciar sesión con PocketID».', 'atareao-functionality')
            );
        }

        return $user;
    }

    /**
     * Renderizar el botón de PocketID o el aviso de modo exigir en la pantalla de login.
     */
    public static function renderLoginFooter()
    {
        if (!self::isConfigured()) {
            return;
        }

        if ('1' === get_option(self::OPTION_ENFORCE, '0')) {
            echo '<p style="margin:1.5em 0 0;text-align:center;color:#666;">'
                . esc_html__('Se requiere PocketID para acceder.', 'atareao-functionality')
                . '</p>';
            return;
        }

        $login_url = add_query_arg('action', 'pocketid', wp_login_url());
        echo '<hr style="margin:1.5em 0 1em;border:none;border-top:1px solid #dcdcde;">';
        echo '<div style="text-align:center;">';
        echo '<a href="' . esc_url($login_url) . '" style="display:inline-block;padding:0.6em 1.4em;background:#1d2327;color:#fff;text-decoration:none;border-radius:4px;">'
            . esc_html__('Iniciar sesión con PocketID', 'atareao-functionality')
            . '</a>';
        echo '</div>';
    }

    /**
     * Registrar la página de ajustes
     */
    public static function addSettingsPage()
    {
        add_options_page(
            __('PocketID Config', 'atareao-functionality'),
            __('PocketID Login', 'atareao-functionality'),
            'manage_options',
            'pocketid-login',
            array(__CLASS__, 'renderSettingsPage')
        );
    }

    /**
     * Renderizar la página de ajustes
     */
    public static function renderSettingsPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $save_error = '';
        $save_success = false;
        $test_config = null;
        $test_failed = false;

        if (isset($_POST['atareao_pocketid_save'])) {
            check_admin_referer('atareao_pocketid_config', 'atareao_pocketid_nonce');
            $url = isset($_POST['atareao_pocketid_url']) ? esc_url_raw(wp_unslash($_POST['atareao_pocketid_url'])) : '';
            if ('' !== $url && 0 !== strpos($url, 'https://')) {
                $save_error = __('La URL debe usar HTTPS.', 'atareao-functionality');
            } else {
                update_option(self::OPTION_URL, $url);

                $client_id = isset($_POST['atareao_pocketid_client_id'])
                    ? sanitize_text_field(wp_unslash($_POST['atareao_pocketid_client_id']))
                    : '';
                update_option(self::OPTION_CLIENT_ID, $client_id);

                $client_secret = isset($_POST['atareao_pocketid_client_secret'])
                    ? sanitize_text_field(wp_unslash($_POST['atareao_pocketid_client_secret']))
                    : '';
                if ('' !== $client_secret) {
                    update_option(self::OPTION_CLIENT_SECRET, $client_secret);
                }

                $enforce = isset($_POST['atareao_pocketid_enforce']) ? '1' : '0';
                update_option(self::OPTION_ENFORCE, $enforce);

                // La URL pudo cambiar: invalidar la caché de descubrimiento.
                delete_transient(self::TRANSIENT_CONFIG);
                $save_success = true;
            }
        }

        if (isset($_POST['atareao_pocketid_test'])) {
            check_admin_referer('atareao_pocketid_config', 'atareao_pocketid_nonce');
            delete_transient(self::TRANSIENT_CONFIG);
            $config = self::getOIDCConfig(true);
            if (false === $config) {
                $test_failed = true;
            } elseif ('discovery' === $config['source']) {
                $test_config = $config;
            } else {
                $test_failed = true;
            }
        }

        $url = get_option(self::OPTION_URL, '');
        $client_id = get_option(self::OPTION_CLIENT_ID, '');
        $has_secret = '' !== get_option(self::OPTION_CLIENT_SECRET, '');
        $enforce = get_option(self::OPTION_ENFORCE, '0');

        if ('' !== $save_error) {
            // Mantener lo que el usuario intentó guardar.
            $url = isset($_POST['atareao_pocketid_url'])
                ? esc_url_raw(wp_unslash($_POST['atareao_pocketid_url']))
                : $url;
            $client_id = isset($_POST['atareao_pocketid_client_id'])
                ? sanitize_text_field(wp_unslash($_POST['atareao_pocketid_client_id']))
                : $client_id;
            $enforce = isset($_POST['atareao_pocketid_enforce']) ? '1' : '0';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configuración de Pocket ID Login', 'atareao-functionality'); ?></h1>

            <?php if ('' !== $save_error) : ?>
                <div class="error"><p><?php echo esc_html($save_error); ?></p></div>
            <?php endif; ?>
            <?php if ($save_success) : ?>
                <div class="updated"><p><?php esc_html_e('Configuración guardada.', 'atareao-functionality'); ?></p></div>
            <?php endif; ?>
            <?php if ($test_failed) : ?>
                <div class="error"><p><?php esc_html_e('No se pudo conectar con el servidor Pocket ID. Revisa la URL y los ajustes; el detalle está en el log del servidor.', 'atareao-functionality'); ?></p></div>
            <?php endif; ?>
            <?php if (is_array($test_config)) : ?>
                <div class="updated">
                    <p><?php esc_html_e('Conexión correcta. Endpoints detectados:', 'atareao-functionality'); ?></p>
                    <ul>
                        <li><?php esc_html_e('Autorización:', 'atareao-functionality'); ?> <code><?php echo esc_html($test_config['authorization_endpoint']); ?></code></li>
                        <li><?php esc_html_e('Token:', 'atareao-functionality'); ?> <code><?php echo esc_html($test_config['token_endpoint']); ?></code></li>
                        <li><?php esc_html_e('Userinfo:', 'atareao-functionality'); ?> <code><?php echo esc_html($test_config['userinfo_endpoint']); ?></code></li>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="atareao_pocketid_url"><?php esc_html_e('URL de Pocket ID', 'atareao-functionality'); ?></label></th>
                        <td>
                            <input type="url" id="atareao_pocketid_url" name="atareao_pocketid_url"
                                   value="<?php echo esc_attr($url); ?>" style="width:400px;"
                                   placeholder="https://id.example.com">
                            <p class="description"><?php esc_html_e('URL base de la instancia de Pocket ID (con https://).', 'atareao-functionality'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="atareao_pocketid_client_id"><?php esc_html_e('Client ID', 'atareao-functionality'); ?></label></th>
                        <td>
                            <input type="text" id="atareao_pocketid_client_id" name="atareao_pocketid_client_id"
                                   value="<?php echo esc_attr($client_id); ?>" style="width:400px;">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="atareao_pocketid_client_secret"><?php esc_html_e('Client Secret', 'atareao-functionality'); ?></label></th>
                        <td>
                            <input type="password" id="atareao_pocketid_client_secret" name="atareao_pocketid_client_secret"
                                   style="width:400px;"
                                   placeholder="<?php echo $has_secret ? '••••••••' : ''; ?>">
                            <?php if ($has_secret) : ?>
                                <p class="description"><?php esc_html_e('Hay un secret guardado. Déjalo en blanco para conservarlo.', 'atareao-functionality'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Exigir PocketID', 'atareao-functionality'); ?></th>
                        <td>
                            <label for="atareao_pocketid_enforce">
                                <input type="checkbox" id="atareao_pocketid_enforce" name="atareao_pocketid_enforce" value="1"
                                       <?php checked('1', $enforce); ?>>
                                <?php esc_html_e('Redirigir wp-login.php a Pocket ID y bloquear el formulario de contraseña (el acceso por application passwords, XML-RPC y REST no se ve afectado).', 'atareao-functionality'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect URI', 'atareao-functionality'); ?></th>
                        <td>
                            <code><?php echo esc_html(wp_login_url()); ?></code>
                            <p class="description"><?php esc_html_e('Registra esta URI en el cliente OIDC de Pocket ID.', 'atareao-functionality'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php wp_nonce_field('atareao_pocketid_config', 'atareao_pocketid_nonce'); ?>
                <p>
                    <input type="submit" name="atareao_pocketid_save" class="button button-primary"
                           value="<?php esc_attr_e('Guardar', 'atareao-functionality'); ?>">
                    <input type="submit" name="atareao_pocketid_test" class="button"
                           value="<?php esc_attr_e('Probar conexión', 'atareao-functionality'); ?>">
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * wp_die genérico 403 para fallos de validación (sin fuga de detalles).
     */
    private static function dieGeneric403()
    {
        wp_die(
            esc_html__('La solicitud de inicio de sesión no es válida o ha expirado. Vuelve a intentarlo.', 'atareao-functionality'),
            esc_html__('Error de inicio de sesión', 'atareao-functionality'),
            array('response' => 403)
        );
    }

    /**
     * wp_die genérico 503 para fallos temporales del proveedor.
     */
    private static function dieGenericTemporary()
    {
        wp_die(
            esc_html__('No se pudo completar el inicio de sesión. Inténtalo de nuevo más tarde.', 'atareao-functionality'),
            esc_html__('Error de inicio de sesión', 'atareao-functionality'),
            array('response' => 503)
        );
    }

    /**
     * Log server-side con prefijo identificable
     *
     * @param string $message Mensaje a registrar.
     */
    private static function log($message)
    {
        error_log('[atareao-pocketid] ' . $message);
    }
}