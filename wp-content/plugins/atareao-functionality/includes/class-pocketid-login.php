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
    const OPTION_REQUIRE_VERIFIED_EMAIL = 'atareao_pocketid_require_verified_email';
    const TRANSIENT_CONFIG = 'atareao_pocketid_oidc_config';
    const CONFIG_SCHEMA = 3;
    const USER_META_SUB = 'atareao_pocketid_sub';
    const CLIENT_SECRET_CONSTANT = 'ATAREAO_POCKETID_CLIENT_SECRET';
    private const TRANSIENT_STATE_PREFIX = 'atareao_pid_state_';
    private const TRANSIENT_IDTOKEN_PREFIX = 'atareao_pocketid_idtoken_';
    const COOKIE_OAUTH = 'atareao_pocketid_oauth';

    /**
     * Algoritmos de firma admitidos para el `id_token`.
     *
     * Solo algoritmos asimétricos RSA verificables con OpenSSL. `none` y los
     * HMAC (HS*) se rechazan por diseño; los algoritmos no soportados hacen que
     * el `id_token` no se confíe y la autenticación continúe por `userinfo`.
     */
    private const ALLOWED_ID_TOKEN_ALGS = array('RS256', 'RS384', 'RS512');

    /**
     * Tolerancia de reloj (segundos) al validar el `exp` del `id_token`.
     */
    private const ID_TOKEN_LEEWAY = 60;

    /**
     * TTL único y compartido del `state` OIDC (transient anti-replay y cookie).
     *
     * 15 minutos dan margen a un prompt de passkey lento o a un reintento sin
     * ampliar en exceso la ventana de un state no consumido (que sigue siendo
     * single-use).
     */
    const STATE_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * `id_token` del usuario leído en la acción `wp_logout`.
     *
     * La acción `wp_logout` corre ANTES que el filtro `logout_redirect`, pero
     * en ese punto ya no hay sesión que consultar. Por eso el `id_token` se
     * guarda aquí y el filtro lo consume para construir la URL de cierre de
     * sesión del proveedor.
     *
     * @var string
     */
    private static $pending_id_token = '';

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
        add_action('wp_logout', array(__CLASS__, 'handleWpLogout'), 10, 1);
        add_filter('logout_redirect', array(__CLASS__, 'handleLogoutRedirect'), 10, 3);
        add_filter('allowed_redirect_hosts', array(__CLASS__, 'allowPocketIdHost'), 10, 1);
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
        $client_secret = self::getClientSecret();

        return '' !== $url && '' !== $client_id && '' !== $client_secret;
    }

    /**
     * Leer el `client_secret` con precedencia de constante/entorno.
     *
     * Orden: constante `ATAREAO_POCKETID_CLIENT_SECRET`, variable de entorno con
     * el mismo nombre y, por último, la opción `wp_options` (guardada sin
     * autoload). Cuando la constante o el entorno están definidos, el secreto
     * NUNCA se guarda en la base de datos.
     *
     * @return string
     */
    private static function getClientSecret()
    {
        if (defined(self::CLIENT_SECRET_CONSTANT)) {
            $constant = (string) constant(self::CLIENT_SECRET_CONSTANT);
            if ('' !== $constant) {
                return $constant;
            }
        }

        $env = getenv(self::CLIENT_SECRET_CONSTANT);
        if (false !== $env && '' !== (string) $env) {
            return (string) $env;
        }

        return (string) get_option(self::OPTION_CLIENT_SECRET, '');
    }

    /**
     * ¿El secreto proviene de una constante o variable de entorno?
     *
     * @return bool
     */
    private static function hasExternalClientSecret()
    {
        if (defined(self::CLIENT_SECRET_CONSTANT) && '' !== (string) constant(self::CLIENT_SECRET_CONSTANT)) {
            return true;
        }

        $env = getenv(self::CLIENT_SECRET_CONSTANT);

        return false !== $env && '' !== (string) $env;
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
        // Estado post-logout: WordPress redirige a `wp-login.php?loggedout=true`
        // mediante un 302 GET tras destruir la sesión. Sin `action` ni sesión, no
        // debe reiniciarse el flujo OIDC (PocketID conservaría su SSO y
        // reautenticaría en silencio). Se deja renderizar la pantalla nativa
        // "Has cerrado la sesión".
        //
        // Se restringe a GET: el `default` de wp-login.php llama a `wp_signon()`
        // sin exigir `wp-submit`, y `blockPasswordLogin()` solo bloquea cuando
        // existe `wp-submit`. Un POST con `log`+`pwd` y `?loggedout=1` quedaría
        // exento del modo exigir y autenticaría por contraseña.
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ('GET' === $request_method && !empty($_GET['loggedout'])) {
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
     * El `end_session_endpoint` es opcional: se conserva cuando el discovery lo
     * publica y supera la validación https/host; no forma parte de la validez
     * de la caché ni del fallback.
     *
     * La caché incluye una versión de esquema (`self::CONFIG_SCHEMA`). Una
     * entrada sin la versión actual (escrita por una versión anterior del
     * plugin) se considera inválida y se refresca, de modo que los campos
     * nuevos —en particular `end_session_endpoint`— se repueblen. La versión de
     * esquema sí condiciona la validez de la caché; `end_session_endpoint` no.
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
                && isset($cached['config_schema'])
                && (int) $cached['config_schema'] === self::CONFIG_SCHEMA
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
                'config_schema' => self::CONFIG_SCHEMA,
                'source' => 'fallback',
                'authorization_endpoint' => $base . '/authorize',
                'token_endpoint' => $base . '/api/oidc/token',
                'userinfo_endpoint' => $base . '/api/oidc/userinfo',
                'end_session_endpoint' => '',
                'issuer' => $base,
                'jwks_uri' => '',
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

        // Validación de los endpoints obligatorios: esquema https y mismo host
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

        // `end_session_endpoint` es OPCIONAL (RP-initiated logout): se conserva
        // solo si supera la misma validación https/host; si falta o no es
        // válido, se ignora y el logout se resuelve en local.
        $end_session_endpoint = '';
        if (!empty($data['end_session_endpoint'])) {
            $end_session_scheme = wp_parse_url($data['end_session_endpoint'], PHP_URL_SCHEME);
            $end_session_host = wp_parse_url($data['end_session_endpoint'], PHP_URL_HOST);
            if ('https' === $end_session_scheme && $end_session_host === $base_host) {
                $end_session_endpoint = $data['end_session_endpoint'];
            } else {
                self::log(
                    'end_session_endpoint del discovery no válido (https/host): ' . $data['end_session_endpoint']
                );
            }
        }

        // `issuer` y `jwks_uri` se usan para validar el `id_token`. El JWKS se
        // conserva solo si supera la validación https/host de la base; si no,
        // el `id_token` no podrá validarse y no se usará (decisión razonada en
        // el README: la autenticación se apoya en `userinfo` sobre TLS).
        $issuer = !empty($data['issuer']) ? (string) $data['issuer'] : '';

        $jwks_uri = '';
        if (!empty($data['jwks_uri'])) {
            $jwks_scheme = wp_parse_url($data['jwks_uri'], PHP_URL_SCHEME);
            $jwks_host = wp_parse_url($data['jwks_uri'], PHP_URL_HOST);
            if ('https' === $jwks_scheme && $jwks_host === $base_host) {
                $jwks_uri = (string) $data['jwks_uri'];
            } else {
                self::log('jwks_uri del discovery no válido (https/host); se ignora.');
            }
        }

        return array(
            'config_schema' => self::CONFIG_SCHEMA,
            'source' => 'discovery',
            'authorization_endpoint' => $endpoint_keys['authorization_endpoint'],
            'token_endpoint' => $endpoint_keys['token_endpoint'],
            'userinfo_endpoint' => $endpoint_keys['userinfo_endpoint'],
            'end_session_endpoint' => $end_session_endpoint,
            'issuer' => $issuer,
            'jwks_uri' => $jwks_uri,
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
            $nonce = bin2hex(random_bytes(32));
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
        // válido una vez y expira con el TTL compartido STATE_TTL (15 min),
        // independientemente de que el atacante replique la cookie. El `nonce`
        // viaja con el state (transient y cookie) y se consume en el callback
        // para ligar el `id_token` a esta petición de autorización.
        set_transient(
            self::TRANSIENT_STATE_PREFIX . hash('sha256', $state),
            array(
                'code_verifier' => $code_verifier,
                'nonce' => $nonce,
            ),
            self::STATE_TTL
        );

        self::setOAuthCookie($state, $code_verifier, $redirect_to, $nonce);

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
                'nonce' => $nonce,
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

        if ('' === $cookie_raw) {
            self::log('Callback rechazado: cookie de estado ausente.');
            self::dieGeneric403();
        }

        $saved = json_decode($cookie_raw, true);
        if (!is_array($saved) || empty($saved['state']) || empty($saved['code_verifier'])) {
            self::log('Callback rechazado: cookie de estado ilegible (JSON inválido o sin state/code_verifier).');
            self::dieGeneric403();
        }

        // Single-use server-side del state (anti-replay): se comprueba el
        // transient persistido en startFlow() y se consume de inmediato,
        // incluso si el flujo posterior falla. El transient guarda el
        // `code_verifier` y el `nonce` de esta autorización.
        $state_key = self::TRANSIENT_STATE_PREFIX . hash('sha256', $saved['state']);
        $saved_state = get_transient($state_key);
        if (false === $saved_state) {
            self::log('Callback rechazado: state ausente o expirado (transient no encontrado).');
            self::dieGeneric403();
        }
        $saved_verifier = is_array($saved_state)
            ? (string) (isset($saved_state['code_verifier']) ? $saved_state['code_verifier'] : '')
            : (string) $saved_state;
        $saved_nonce = is_array($saved_state)
            ? (string) (isset($saved_state['nonce']) ? $saved_state['nonce'] : '')
            : '';
        if (!hash_equals($saved_verifier, (string) $saved['code_verifier'])) {
            delete_transient($state_key);
            self::log('Callback rechazado: replay de un state ya consumido (verifier no coincide).');
            self::dieGeneric403();
        }
        delete_transient($state_key);

        $given_state = isset($_GET['state']) ? wp_unslash($_GET['state']) : '';
        if ('' === $given_state || !hash_equals((string) $saved['state'], $given_state)) {
            self::log('Callback rechazado: state mismatch (no coincide con la query).');
            self::dieGeneric403();
        }

        $code = isset($_GET['code']) ? wp_unslash($_GET['code']) : '';
        if ('' === $code) {
            self::log('Callback rechazado: falta el parámetro code.');
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
                    'client_secret' => self::getClientSecret(),
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
                if ('1' === get_option(self::OPTION_REQUIRE_VERIFIED_EMAIL, '1')) {
                    self::log('Email no verificado (política estricta): ' . $email);
                    self::dieGeneric403();
                }
                self::log('Email no verificado aceptado (política estricta desactivada): ' . $email);
            }
        }

        // Validar el `id_token` (si el proveedor lo devuelve) antes de confiar
        // en él. Un token no validado no se persiste ni se usa como
        // `id_token_hint`; la autenticación se apoya entonces en el `userinfo`
        // obtenido sobre TLS (decisión razonada en el README).
        $id_token = !empty($token_data['id_token']) ? (string) $token_data['id_token'] : '';
        $id_token_claims = array();
        if ('' !== $id_token) {
            $validated = self::validateIdToken($id_token, $config, $saved_nonce);
            if (is_array($validated)) {
                $id_token_claims = $validated;
            } else {
                $id_token = '';
            }
        }

        // Identidad por `sub` (de userinfo o del `id_token` validado).
        $sub = '';
        if (isset($userinfo['sub']) && is_scalar($userinfo['sub'])) {
            $sub = (string) $userinfo['sub'];
        }
        if ('' === $sub && !empty($id_token_claims['sub']) && is_scalar($id_token_claims['sub'])) {
            $sub = (string) $id_token_claims['sub'];
        }
        if ('' === $sub) {
            self::log('Denegado: el proveedor no devolvió el claim sub (fail-safe).');
            self::dieGeneric403();
        }

        $user = self::resolveUserByIdentity($email, $sub);
        if (!$user) {
            self::dieGeneric403();
        }

        // Persistir el `id_token` server-side, ligado al usuario, para poder
        // iniciar el cierre de sesión del proveedor (RP-initiated logout).
        // Nunca se expone en cookies ni en la interfaz y solo se persiste si
        // ha superado la validación.
        if ('' !== $id_token) {
            $id_token_ttl = (int) apply_filters('auth_cookie_expiration', 2 * DAY_IN_SECONDS, $user->ID, true);
            if ($id_token_ttl <= 0) {
                $id_token_ttl = 2 * DAY_IN_SECONDS;
            }
            set_transient(
                self::transientIdTokenKey($user->ID),
                $id_token,
                $id_token_ttl
            );
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
     * Clave del transient que almacena el `id_token` de un usuario.
     *
     * @param int $user_id ID del usuario de WordPress.
     * @return string
     */
    private static function transientIdTokenKey($user_id)
    {
        return self::TRANSIENT_IDTOKEN_PREFIX . (int) $user_id;
    }

    /**
     * Resolver el usuario de WordPress por vinculación `sub`↔usuario.
     *
     * Orden fail-safe:
     * 1. Usuario ya vinculado a ese `sub` (aunque su email haya cambiado).
     * 2. Primer acceso: email con cuenta WP sin `sub` ligado -> se vincula.
     * 3. `sub` distinto al ya ligado a la cuenta del email -> se deniega sin
     *    reasignar la cuenta.
     *
     * @param string $email Email verificado del `userinfo`.
     * @param string $sub   Claim `sub` del proveedor (no vacío).
     * @return \WP_User|false
     */
    private static function resolveUserByIdentity($email, $sub)
    {
        $bound = get_users(
            array(
                'meta_key' => self::USER_META_SUB,
                'meta_value' => $sub,
                'number' => 1,
            )
        );
        if (!empty($bound) && isset($bound[0]) && $bound[0] instanceof \WP_User) {
            return $bound[0];
        }

        $user = get_user_by('email', $email);
        if (!$user) {
            self::log('Denegado: email sin cuenta WP y sin vinculación previa por sub.');
            return false;
        }

        $stored = (string) get_user_meta($user->ID, self::USER_META_SUB, true);
        if ('' === $stored) {
            update_user_meta($user->ID, self::USER_META_SUB, $sub);
            self::log('Vinculación sub↔usuario creada para el usuario ' . (int) $user->ID . '.');
            return $user;
        }

        if (!hash_equals($stored, $sub)) {
            self::log('Denegado: el sub no coincide con la vinculación previa del usuario ' . (int) $user->ID . '.');
            return false;
        }

        return $user;
    }

    /**
     * Validar un `id_token` JWT conforme al discovery.
     *
     * Comprueba `alg` permitido, firma contra el JWKS (`jwks_uri`), `iss`,
     * `aud`, `exp` (con tolerancia) y `nonce`. Devuelve los claims si es válido
     * o `false` si falla, registrando siempre el motivo con el prefijo
     * `[atareao-pocketid]`.
     *
     * @param string $id_token JWT sin validar.
     * @param array  $config   Configuración OIDC (issuer, jwks_uri).
     * @param string $nonce    Nonce enviado en la autorización.
     * @return array|false Claims validados o false.
     */
    private static function validateIdToken($id_token, $config, $nonce)
    {
        $parts = explode('.', $id_token);
        if (3 !== count($parts)) {
            self::log('id_token rechazado: formato JWT inválido.');
            return false;
        }

        $header = json_decode((string) self::base64UrlDecode($parts[0]), true);
        $claims = json_decode((string) self::base64UrlDecode($parts[1]), true);
        $signature = self::base64UrlDecode($parts[2]);
        if (!is_array($header) || !is_array($claims) || false === $signature || '' === $signature) {
            self::log('id_token rechazado: cabecera, payload o firma no decodificables.');
            return false;
        }

        $alg = isset($header['alg']) ? (string) $header['alg'] : '';
        if (!in_array($alg, self::ALLOWED_ID_TOKEN_ALGS, true)) {
            self::log('id_token rechazado: algoritmo no permitido (' . $alg . ').');
            return false;
        }

        $issuer = isset($config['issuer']) ? (string) $config['issuer'] : '';
        if ('' === $issuer || !isset($claims['iss']) || !hash_equals($issuer, (string) $claims['iss'])) {
            self::log('id_token rechazado: iss no coincide con el issuer del discovery.');
            return false;
        }

        $client_id = (string) get_option(self::OPTION_CLIENT_ID, '');
        $aud = isset($claims['aud']) ? $claims['aud'] : '';
        $aud_ok = is_array($aud)
            ? in_array($client_id, array_map('strval', $aud), true)
            : ((string) $aud === $client_id);
        if ('' === $client_id || !$aud_ok) {
            self::log('id_token rechazado: aud no coincide con el client_id.');
            return false;
        }

        $exp = isset($claims['exp']) ? (int) $claims['exp'] : 0;
        if ($exp <= (time() - self::ID_TOKEN_LEEWAY)) {
            self::log('id_token rechazado: exp caducado.');
            return false;
        }

        if ('' === $nonce || !isset($claims['nonce']) || !hash_equals($nonce, (string) $claims['nonce'])) {
            self::log('id_token rechazado: nonce no coincide.');
            return false;
        }

        $jwks_uri = isset($config['jwks_uri']) ? (string) $config['jwks_uri'] : '';
        if ('' === $jwks_uri
            || !self::verifyJwtSignature($parts[0] . '.' . $parts[1], $signature, $header, $jwks_uri)) {
            self::log('id_token rechazado: firma no válida contra el JWKS.');
            return false;
        }

        return $claims;
    }

    /**
     * Verificar la firma RS* de un JWT contra el JWKS publicado.
     *
     * @param string $signing_input Cabecera y payload tal cual (base64url).
     * @param string $signature     Firma decodificada.
     * @param array  $header        Cabecera JWT (alg, kid).
     * @param string $jwks_uri      URL del JWKS (ya validada https/host).
     * @return bool
     */
    private static function verifyJwtSignature($signing_input, $signature, $header, $jwks_uri)
    {
        $jwks_response = wp_remote_get($jwks_uri, array('timeout' => 10, 'sslverify' => true));
        if (is_wp_error($jwks_response)) {
            self::log('No se pudo descargar el JWKS: ' . $jwks_response->get_error_message());
            return false;
        }

        $code = intval(wp_remote_retrieve_response_code($jwks_response));
        $jwks = json_decode(wp_remote_retrieve_body($jwks_response), true);
        if ($code < 200 || $code >= 300 || !is_array($jwks) || empty($jwks['keys']) || !is_array($jwks['keys'])) {
            self::log('JWKS inválido (HTTP ' . $code . ').');
            return false;
        }

        $kid = isset($header['kid']) ? (string) $header['kid'] : '';
        $alg = isset($header['alg']) ? (string) $header['alg'] : '';
        $openssl_algs = array(
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
        );

        foreach ($jwks['keys'] as $key) {
            if (!is_array($key) || 'RSA' !== (isset($key['kty']) ? $key['kty'] : '')) {
                continue;
            }
            if ('' !== $kid && isset($key['kid']) && (string) $key['kid'] !== $kid) {
                continue;
            }
            if (isset($key['alg']) && '' !== $key['alg'] && (string) $key['alg'] !== $alg) {
                continue;
            }
            if (!isset($key['n'], $key['e'])) {
                continue;
            }

            $pem = self::rsaPublicKeyPem((string) $key['n'], (string) $key['e']);
            if (false === $pem) {
                continue;
            }

            if (1 === openssl_verify($signing_input, $signature, $pem, $openssl_algs[$alg])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decodificar base64url (JWT) sin padding.
     *
     * @param string $data Cadena base64url.
     * @return string|false
     */
    private static function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if (0 !== $remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    /**
     * Construir la clave pública RSA en PEM a partir del módulo (`n`) y el
     * exponente (`e`) en base64url del JWKS.
     *
     * @param string $n_b64 Módulo base64url.
     * @param string $e_b64 Exponente base64url.
     * @return string|false PEM o false.
     */
    private static function rsaPublicKeyPem($n_b64, $e_b64)
    {
        $modulus = self::base64UrlDecode($n_b64);
        $exponent = self::base64UrlDecode($e_b64);
        if (false === $modulus || false === $exponent || '' === $modulus || '' === $exponent) {
            return false;
        }

        $modulus = "\x02" . self::asn1Length(strlen($modulus) + 1) . "\x00" . $modulus;
        $exponent = "\x02" . self::asn1Length(strlen($exponent)) . $exponent;

        $sequence = "\x30" . self::asn1Length(strlen($modulus . $exponent)) . $modulus . $exponent;
        $bit_string = "\x03" . self::asn1Length(strlen($sequence) + 1) . "\x00" . $sequence;

        $rsa_algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $public_key = "\x30" . self::asn1Length(strlen($rsa_algorithm . $bit_string)) . $rsa_algorithm . $bit_string;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($public_key), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /**
     * Codificar una longitud ASN.1 DER.
     *
     * @param int $length Longitud.
     * @return string
     */
    private static function asn1Length($length)
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Permitir el host de Pocket ID en `wp_safe_redirect()` (solo en logout).
     *
     * `wp-login.php` aplica `allowed_redirect_hosts` y después
     * `wp_safe_redirect()`, que rechaza hosts externos y cae al fallback
     * local. Sin añadir el host del proveedor, la redirección al
     * `end_session_endpoint` nunca se completaría.
     *
     * El filtro se evalúa durante el `wp_safe_redirect()` del `case 'logout'`
     * de `wp-login.php`, donde `$GLOBALS['pagenow'] === 'wp-login.php'` y
     * `$_GET['action'] === 'logout'`. Se acota a ese contexto para no ampliar
     * globalmente la allowlist de hosts de `wp_safe_redirect()`.
     *
     * @param array $hosts Lista de hosts permitidos.
     * @return array
     */
    public static function allowPocketIdHost($hosts)
    {
        if (!is_array($hosts)) {
            return $hosts;
        }

        $pagenow = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';
        $action = isset($_GET['action']) ? (string) $_GET['action'] : '';
        if ('wp-login.php' !== $pagenow || 'logout' !== $action) {
            return $hosts;
        }

        if (!self::isConfigured()) {
            return $hosts;
        }

        $host = wp_parse_url(get_option(self::OPTION_URL, ''), PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            return $hosts;
        }

        if (!in_array($host, $hosts, true)) {
            $hosts[] = $host;
        }

        return $hosts;
    }

    /**
     * Leer y borrar el `id_token` del usuario al cerrar sesión.
     *
     * Se engancha a `wp_logout` (que recibe el `$user_id`) porque la cookie de
     * sesión ya está destruida en ese punto. El `id_token` se conserva en una
     * propiedad estática para que `logout_redirect`, que corre después, pueda
     * construir la URL del proveedor.
     *
     * @param int $user_id ID del usuario que cierra sesión.
     */
    public static function handleWpLogout($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }

        $id_token = get_transient(self::transientIdTokenKey($user_id));
        if (false !== $id_token && '' !== $id_token) {
            self::$pending_id_token = (string) $id_token;
        }

        delete_transient(self::transientIdTokenKey($user_id));
    }

    /**
     * Redirigir al `end_session_endpoint` del proveedor (RP-initiated logout).
     *
     * Fail-safe: si falta la configuración, el `end_session_endpoint` o el
     * `id_token`, se devuelve el destino local de WordPress y el logout local
     * se completa igualmente.
     *
     * Si la configuración disponible no trae `end_session_endpoint` (p. ej. una
     * caché antigua), se refresca el discovery UNA sola vez antes de recurrir
     * al logout local. `getOIDCConfig(true)` ya reintenta internamente, así que
     * no se introduce ningún bucle de descargas.
     *
     * @param string       $redirect_to           Destino de logout por defecto.
     * @param string       $requested_redirect_to Destino solicitado por el usuario.
     * @param \WP_User|int $user                  Usuario o ID que cierra sesión.
     * @return string
     */
    public static function handleLogoutRedirect($redirect_to, $requested_redirect_to, $user)
    {
        if (!self::isConfigured()) {
            return $redirect_to;
        }

        $config = self::getOIDCConfig();
        if (false === $config || empty($config['end_session_endpoint'])) {
            // Refresco puntual: el proveedor pudo publicar `end_session_endpoint`
            // después de escribir la caché. No se itera: `getOIDCConfig(true)`
            // ignora la caché y reintenta la descarga internamente.
            self::log('Logout sin end_session_endpoint en la configuración; se refresca el discovery.');
            $config = self::getOIDCConfig(true);
        }

        if (false === $config || empty($config['end_session_endpoint'])) {
            self::log('Logout sin end_session_endpoint tras refrescar el discovery; se completa el logout local.');
            return $redirect_to;
        }

        // El `id_token` se leyó y borró en `wp_logout` (acción anterior). Como
        // red de seguridad, si no llegó por ahí se intenta leer del transient.
        $id_token = self::$pending_id_token;
        if ('' === $id_token) {
            $user_id = 0;
            if ($user instanceof \WP_User) {
                $user_id = (int) $user->ID;
            } elseif (function_exists('get_current_user_id')) {
                $user_id = (int) get_current_user_id();
            }
            if ($user_id > 0) {
                $stored = get_transient(self::transientIdTokenKey($user_id));
                if (false !== $stored && '' !== $stored) {
                    $id_token = (string) $stored;
                }
            }
        }

        if ('' === $id_token) {
            self::log('Logout sin id_token; se completa el logout local.');
            return $redirect_to;
        }

        // Solo se construye desde `wp_login_url()`; nunca desde entrada del
        // usuario. El `id_token` es un JWT y no debe pasar por sanitize_text_field.
        $post_logout_redirect_uri = add_query_arg('loggedout', 'true', wp_login_url());

        return add_query_arg(
            array(
                'id_token_hint' => $id_token,
                'client_id' => get_option(self::OPTION_CLIENT_ID, ''),
                'post_logout_redirect_uri' => $post_logout_redirect_uri,
            ),
            $config['end_session_endpoint']
        );
    }

    /**
     * Guardar la cookie de estado OIDC host-only
     * (HttpOnly + Secure + SameSite=Lax, TTL 15 min, sin atributo Domain)
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
        setcookie(self::COOKIE_OAUTH, $value, self::oauthCookieOptions(time() + self::STATE_TTL));
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
     * Host-only real: para que la cookie NO se envíe a los subdominios hay que
     * OMITIR el atributo `Domain` (aquí `'domain' => ''`). Pasar el host exacto
     * (`atareao.es`) como domain la convertiría en una cookie de dominio y, por
     * RFC 6265, se enviaría también a subdominios como `pocketid.<dominio>`.
     *
     * @param int $expires Timestamp de expiración.
     * @return array
     */
    private static function oauthCookieOptions($expires)
    {
        return array(
            'expires' => $expires,
            'path' => COOKIEPATH,
            'domain' => '',
            'secure' => 'https' === wp_parse_url(wp_login_url(), PHP_URL_SCHEME),
            'httponly' => true,
            'samesite' => 'Lax',
        );
    }

    /**
     * Bloquear la autenticación por contraseña interactiva de wp-login.php y
     * XML-RPC.
     *
     * Solo cuando la configuración está completa y el toggle "Exigir PocketID"
     * está activo. Se aplica en el punto común de autenticación (filtro
     * `authenticate`) y decide por las credenciales recibidas
     * (`$username`/`$password`), NO por `$_POST['log']`/`$_POST['pwd']`, para
     * cubrir igualmente XML-RPC y no poder eludirse omitiendo `wp-submit` o el
     * campo `action`.
     *
     * Preserva explícitamente los Application Passwords de WordPress y la
     * autenticación REST (`application_password_is_api_request`) así como las
     * acciones nativas exentas (`postpass`, `lostpassword`, `rp`, `resetpass`,
     * `register`, `logout`). No se intercepta `wp_authenticate_application_password`.
     *
     * @param mixed  $user     WP_User|WP_Error|null.
     * @param string $username Nombre de usuario enviado.
     * @param string $password Contraseña enviada.
     * @return mixed
     */
    public static function blockPasswordLogin($user, $username, $password)
    {
        if ('1' !== get_option(self::OPTION_ENFORCE, '0') || !self::isConfigured()) {
            return $user;
        }

        // Application Passwords / REST: mecanismo de credencial distinto y
        // acotado a la API REST. No debe verse afectado por la política
        // passwordless.
        if (self::isApplicationPasswordAuthentication()) {
            return $user;
        }

        // Acciones nativas de recuperación y formularios de contenido: nunca
        // se bloquean.
        if (self::isExemptPasswordAction()) {
            return $user;
        }

        $username = is_string($username) ? trim($username) : '';
        $password = is_string($password) ? $password : '';
        if ('' === $username || '' === $password) {
            return $user;
        }

        self::log('Intento de login por contraseña interactiva bloqueado (modo exigir activo).');
        return new \WP_Error(
            'pocketid_required',
            __('El inicio de sesión con contraseña está deshabilitado. Usa el botón «Iniciar sesión».', 'atareao-functionality')
        );
    }

    /**
     * ¿La autenticación en curso proviene de un Application Password / REST?
     *
     * @return bool
     */
    private static function isApplicationPasswordAuthentication()
    {
        return function_exists('application_password_is_api_request')
            && application_password_is_api_request();
    }

    /**
     * ¿La petición corresponde a una acción nativa exenta del bloqueo?
     *
     * @return bool
     */
    private static function isExemptPasswordAction()
    {
        $action = '';
        if (isset($_REQUEST['action']) && is_scalar($_REQUEST['action'])) {
            $action = (string) $_REQUEST['action'];
        }

        return in_array($action, self::$native_actions, true);
    }

    /**
     * Renderizar el botón "Iniciar sesión" y limpiar la pantalla post-logout.
     *
     * - Modo exigir + `loggedout`: oculta el formulario de contraseña inerte y
     *   el resto de enlaces nativos, y muestra el botón que reinicia el flujo
     *   OIDC. El aviso nativo "Has cerrado la sesión" lo pinta el core.
     * - Modo exigir sin `loggedout` (p. ej. `lostpassword`): no añade nada; la
     *   página nativa queda tal cual.
     * - Modo no exigir: botón "Iniciar sesión" sobre el formulario normal, sin
     *   CSS inyectado.
     */
    public static function renderLoginFooter()
    {
        if (!self::isConfigured()) {
            return;
        }

        if ('1' === get_option(self::OPTION_ENFORCE, '0')) {
            // Fuera de la pantalla post-logout no se inyecta aviso ni botón.
            if (empty($_GET['loggedout'])) {
                return;
            }

            // El formulario de contraseña sigue en el DOM pero es inerte: el
            // bloqueo real lo aplica blockPasswordLogin() en el servidor. Aquí
            // solo se oculta (formulario, navegación y recuperación de
            // contraseña) y se ofrece el botón para volver a iniciar sesión.
            echo '<style>#loginform,#nav,#backtoblog,#nav a{display:none}</style>';
            self::renderLoginButton();
            return;
        }

        echo '<hr style="margin:1.5em 0 1em;border:none;border-top:1px solid #dcdcde;">';
        self::renderLoginButton();
    }

    /**
     * Imprimir el botón/enlace que inicia el flujo OIDC ("Iniciar sesión").
     *
     * La etiqueta es siempre "Iniciar sesión": ningún texto de la UI pública
     * nombra al proveedor de identidad.
     */
    private static function renderLoginButton()
    {
        $login_url = add_query_arg('action', 'pocketid', wp_login_url());
        echo '<div style="text-align:center;">';
        echo '<a href="' . esc_url($login_url) . '" style="display:inline-block;padding:0.6em 1.4em;background:#1d2327;color:#fff;text-decoration:none;border-radius:4px;">'
            . esc_html__('Iniciar sesión', 'atareao-functionality')
            . '</a>';
        echo '</div>';
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
                if ('' !== $client_secret && !self::hasExternalClientSecret()) {
                    // Autoload desactivado: el secreto no debe cargarse en cada
                    // petición ni exponerse en exportaciones.
                    update_option(self::OPTION_CLIENT_SECRET, $client_secret, false);
                }

                $enforce = isset($_POST['atareao_pocketid_enforce']) ? '1' : '0';
                update_option(self::OPTION_ENFORCE, $enforce);

                $require_verified = isset($_POST['atareao_pocketid_require_verified_email']) ? '1' : '0';
                update_option(self::OPTION_REQUIRE_VERIFIED_EMAIL, $require_verified);

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
        $has_secret = '' !== self::getClientSecret();
        $enforce = get_option(self::OPTION_ENFORCE, '0');
        $require_verified_email = get_option(self::OPTION_REQUIRE_VERIFIED_EMAIL, '1');

        if ('' !== $save_error) {
            // Mantener lo que el usuario intentó guardar.
            $url = isset($_POST['atareao_pocketid_url'])
                ? esc_url_raw(wp_unslash($_POST['atareao_pocketid_url']))
                : $url;
            $client_id = isset($_POST['atareao_pocketid_client_id'])
                ? sanitize_text_field(wp_unslash($_POST['atareao_pocketid_client_id']))
                : $client_id;
            $enforce = isset($_POST['atareao_pocketid_enforce']) ? '1' : '0';
            $require_verified_email = isset($_POST['atareao_pocketid_require_verified_email']) ? '1' : '0';
        }
        ?>
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
                            <?php esc_html_e('Redirigir wp-login.php a Pocket ID y bloquear el inicio de sesión con contraseña (formulario web y XML-RPC). Los Application Passwords y la publicación por REST siguen funcionando.', 'atareao-functionality'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Correo verificado', 'atareao-functionality'); ?></th>
                    <td>
                        <label for="atareao_pocketid_require_verified_email">
                            <input type="checkbox"
                                   id="atareao_pocketid_require_verified_email"
                                   name="atareao_pocketid_require_verified_email"
                                   value="1" <?php checked('1', $require_verified_email); ?>>
                            <?php esc_html_e('Exigir correo verificado', 'atareao-functionality'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Permite correo sin verificar.', 'atareao-functionality'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Redirect URI', 'atareao-functionality'); ?></th>
                    <td>
                        <code><?php echo esc_html(wp_login_url()); ?></code>
                        <p class="description"><?php esc_html_e('Registra esta URI en el cliente OIDC de Pocket ID.', 'atareao-functionality'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Post Logout Redirect URI', 'atareao-functionality'); ?></th>
                    <td>
                        <code><?php echo esc_html(add_query_arg('loggedout', 'true', wp_login_url())); ?></code>
                        <p class="description"><?php esc_html_e('Registra esta URI como Post Logout Redirect URI en el cliente OIDC de Pocket ID para el cierre de sesión en el proveedor.', 'atareao-functionality'); ?></p>
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