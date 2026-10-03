<?php
/**
 * Importación de respuestas de Mastodon
 *
 * Absorbe en el plugin la importación que hacía el plugin de terceros
 * «Replies Importer for Mastodon»: conexión OAuth con la instancia, ajustes y
 * cadencia propios, descubrimiento de los estados que enlazan al sitio por el
 * RSS de la cuenta, conversión de sus respuestas públicas en comentarios
 * pendientes con hilo y dedupe, migración a un clic desde el plugin legado y
 * limpieza de su cron huérfano. Todo es admin-only y no altera el sitio
 * público: los comentarios entran pendientes y pasan por la moderación actual.
 *
 * Módulo `\Atareao\MastodonReplies`, expuesto como quinta pestaña («Mastodon»)
 * del hub «Atareao».
 *
 * Las acciones (guardar, autorizar, callback OAuth, comprobar, desconectar e
 * importar del legado) se procesan en `admin_init`, antes de que se envíen las
 * cabeceras, y terminan en `wp_safe_redirect()` hacia la pestaña con el aviso
 * en el transient `atareao_mastodon_notice`. El render es de solo lectura: no
 * hace HTTP, no escribe opciones y no redirige.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gestión de la importación de respuestas de Mastodon.
 */
class MastodonReplies
{
    /**
     * Prefijo de todas las claves de opción del módulo.
     */
    public const OPTION_PREFIX = 'atareao_mastodon_';

    /**
     * Hook del cron propio de importación.
     */
    private const CRON_HOOK = 'atareao_mastodon_import';

    /**
     * Hook del cron del plugin legado, que hay que limpiar al migrar.
     */
    private const LEGACY_CRON_HOOK = 'replies_importer_for_mastodon_event';

    /**
     * Opción legada con la configuración general del plugin absorbido.
     */
    private const LEGACY_SETTINGS_OPTION = 'replies_importer_for_mastodon_settings';

    /**
     * Opción legada con la conexión (credenciales) del plugin absorbido.
     */
    private const LEGACY_CONNECTION_OPTION = 'replies_importer_for_mastodon_connection';

    /**
     * Clase del plugin legado; su presencia indica que sigue cargado.
     */
    private const LEGACY_CLASS = '\Replies_Importer_For_Mastodon_Config';

    /**
     * Meta propia con la URL del estado de Mastodon (identidad canónica).
     */
    private const STATUS_META = '_atareao_mastodon_status_url';

    /**
     * Transient con el aviso de la pestaña (sobrevive a la redirección).
     */
    private const NOTICE_TRANSIENT = 'atareao_mastodon_notice';

    /**
     * Transient con la URL de autorización pendiente (incluye el `state`).
     */
    private const AUTH_URL_TRANSIENT = 'atareao_mastodon_auth_url';

    /**
     * Transient con el `state` OAuth pendiente y su usuario.
     */
    private const OAUTH_STATE_TRANSIENT = 'atareao_mastodon_oauth_state';

    /**
     * Opción con el resumen del último run de importación.
     *
     * Se persiste en una opción propia (no en un transient) para que el resumen
     * sobreviva a recargas y expiraciones; la pestaña lo pinta en solo lectura.
     */
    private const LAST_RUN_OPTION = 'atareao_mastodon_last_run';

    /**
     * Vigencia de la autorización pendiente (segundos).
     */
    private const OAUTH_STATE_TTL = 600;

    /**
     * Timeout de las llamadas HTTP a la instancia.
     */
    private const HTTP_TIMEOUT = 15;

    /**
     * Número máximo de ítems del RSS que se recorren por ejecución.
     */
    public const RSS_LIMIT = 20;

    /**
     * Número máximo de contextos que se piden a la instancia por ejecución.
     */
    public const CONTEXT_LIMIT = 10;

    /**
     * Número máximo de comentarios que se insertan por ejecución.
     */
    public const COMMENT_LIMIT = 100;

    /**
     * Último error accionable generado por una operación del módulo.
     *
     * Se usa para trasladar el motivo real (sin secretos) a la pestaña a través
     * del aviso en el transient.
     *
     * @var string
     */
    private static $lastError = '';

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

        add_action(self::CRON_HOOK, array(__CLASS__, 'fetchAndImport'));
        add_action('admin_init', array(__CLASS__, 'maybeSaveSettings'));
        add_action('admin_init', array(__CLASS__, 'handleAuthorize'));
        add_action('admin_init', array(__CLASS__, 'handleOAuthCallback'));
        add_action('admin_init', array(__CLASS__, 'handleCheckNow'));
        add_action('admin_init', array(__CLASS__, 'handleDisconnect'));
        add_action('admin_init', array(__CLASS__, 'handleImportLegacy'));
        // El relevo del cron va el último: así ve la conexión recién importada.
        add_action('admin_init', array(__CLASS__, 'ensureSchedule'));
        add_action('deactivated_plugin', array(__CLASS__, 'handleLegacyDeactivation'));
    }

    /**
     * Valores por defecto de las seis claves.
     *
     * @return array<string, mixed>
     */
    public static function defaults()
    {
        return array(
            'instance_url' => '',
            'client_id' => '',
            'client_secret' => '',
            'access_token' => '',
            'schedule_period' => 'hourly',
            'debug_mode' => 0,
        );
    }

    /**
     * Claves tratadas como URL.
     *
     * @return string[]
     */
    private static function urlKeys()
    {
        return array('instance_url');
    }

    /**
     * Claves tratadas como texto libre.
     *
     * @return string[]
     */
    private static function textKeys()
    {
        return array('client_id', 'client_secret', 'access_token');
    }

    /**
     * Normaliza un valor de bandera a 0 o 1.
     *
     * @param mixed $value Valor de entrada.
     * @return int
     */
    private static function toFlag($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return ((int) $value) ? 1 : 0;
        }
        if (!is_scalar($value)) {
            return 0;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, array('1', 'on', 'true', 'yes'), true)) {
            return 1;
        }
        return 0;
    }

    /**
     * Sanea una única clave según su tipo (URL, texto, cadencia o bandera).
     *
     * @param string $key   Clave corta.
     * @param mixed  $value Valor de entrada.
     * @return int|string
     */
    private static function sanitizeValue($key, $value)
    {
        if (in_array($key, self::urlKeys(), true)) {
            return is_scalar($value) ? esc_url_raw(trim((string) $value)) : '';
        }
        if (in_array($key, self::textKeys(), true)) {
            if ($key === 'client_id') {
                return is_scalar($value) ? sanitize_text_field((string) $value) : '';
            }
            return self::sanitizeSecret($value);
        }
        if ($key === 'schedule_period') {
            $period = is_scalar($value) ? sanitize_text_field((string) $value) : '';
            return in_array($period, array('hourly', 'daily'), true) ? $period : 'hourly';
        }
        return self::toFlag($value);
    }

    /**
     * Sanea un secreto (token o `client_secret`) conservando su valor real.
     *
     * Recorta el espacio exterior y devuelve '' si contiene espacios internos o
     * caracteres de control (señal de un valor corrupto). Conserva el resto de
     * caracteres atípicos válidos en lugar de mutilarlos.
     *
     * @param mixed $value Valor de entrada.
     * @return string
     */
    private static function sanitizeSecret($value)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $secret = trim(wp_unslash((string) $value));
        if ($secret === '') {
            return '';
        }
        if (preg_match('/\s/', $secret) === 1) {
            return '';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $secret) === 1) {
            return '';
        }
        return $secret;
    }

    /**
     * Lee los ajustes almacenados, completados con los defaults y normalizados.
     *
     * @return array<string, mixed>
     */
    public static function getSettings()
    {
        $settings = self::defaults();
        foreach ($settings as $key => $default) {
            $stored = get_option(self::OPTION_PREFIX . $key, $default);
            $settings[$key] = self::sanitizeValue($key, is_scalar($stored) ? $stored : $default);
        }
        return $settings;
    }

    /**
     * Sanea una entrada según el tipo de cada clave.
     *
     * Las claves ausentes conservan el default; las banderas se normalizan.
     *
     * @param array $input Datos de entrada (claves cortas).
     * @return array<string, mixed>
     */
    public static function sanitizeSettings(array $input)
    {
        $settings = self::defaults();
        foreach ($settings as $key => $default) {
            if (array_key_exists($key, $input)) {
                $settings[$key] = self::sanitizeValue($key, $input[$key]);
            }
        }
        return $settings;
    }

    /**
     * ¿Está la conexión completa (instancia https y token)?
     *
     * @return bool
     */
    public static function isConnected()
    {
        $settings = self::getSettings();
        return self::isValidInstance($settings['instance_url']) && $settings['access_token'] !== '';
    }

    /**
     * ¿Está cargado el plugin legado «Replies Importer for Mastodon»?
     *
     * @return bool
     */
    public static function legacyPluginLoaded()
    {
        return class_exists(self::LEGACY_CLASS);
    }

    /**
     * Configuración legada (settings + connection) tal cual está en la BD.
     *
     * @return array{settings: mixed, connection: mixed}
     */
    private static function legacyOptions()
    {
        return array(
            'settings' => get_option(self::LEGACY_SETTINGS_OPTION),
            'connection' => get_option(self::LEGACY_CONNECTION_OPTION),
        );
    }

    /**
     * ¿El plugin legado está cargado Y va a importar por su cuenta?
     *
     * Solo entonces nos abstenemos de programar nuestro cron para no duplicar.
     *
     * @return bool
     */
    public static function legacyWillImport()
    {
        if (!self::legacyPluginLoaded()) {
            return false;
        }
        $legacy = self::legacyOptions();
        if (!is_array($legacy['settings']) || !is_array($legacy['connection'])) {
            return false;
        }
        return !empty($legacy['settings']['mastodon_instance_url'])
            && !empty($legacy['connection']['access_token']);
    }

    /**
     * Importa la configuración legada sin borrarla y limpia el cron legado.
     *
     * @return int Número de ajustes importados (0 si no había nada).
     */
    public static function importLegacy()
    {
        wp_clear_scheduled_hook(self::LEGACY_CRON_HOOK);

        $legacy = self::legacyOptions();
        $map = array();
        if (is_array($legacy['settings'])) {
            $map['instance_url'] = $legacy['settings']['mastodon_instance_url'] ?? null;
            $map['schedule_period'] = $legacy['settings']['schedule_period'] ?? null;
            $map['debug_mode'] = $legacy['settings']['debug_mode'] ?? null;
        }
        if (is_array($legacy['connection'])) {
            $map['client_id'] = $legacy['connection']['client_id'] ?? null;
            $map['client_secret'] = $legacy['connection']['client_secret'] ?? null;
            $map['access_token'] = $legacy['connection']['access_token'] ?? null;
        }

        $count = 0;
        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }
            update_option(self::OPTION_PREFIX . $key, self::sanitizeValue($key, $value));
            $count++;
        }

        if ($count > 0) {
            self::syncSchedule(get_option(self::OPTION_PREFIX . 'schedule_period', 'hourly'), null);
        }

        return $count;
    }

    /**
     * URI de redirección OAuth, anclada a la pestaña «Mastodon» del hub.
     *
     * @return string
     */
    private static function redirectUri()
    {
        return admin_url('options-general.php?page=atareao-settings&tab=mastodon');
    }

    /**
     * Comprueba que una instancia es una URL `https://` con host utilizable.
     *
     * @param mixed $url URL candidata.
     * @return bool
     */
    private static function isValidInstance($url)
    {
        if (!is_string($url)) {
            return false;
        }
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $parsed = wp_parse_url($url);
        return is_array($parsed)
            && isset($parsed['scheme'], $parsed['host'])
            && strtolower((string) $parsed['scheme']) === 'https'
            && (string) $parsed['host'] !== '';
    }

    /**
     * Registra un evento de depuración si `debug_mode` está activo.
     *
     * Nunca incluye credenciales.
     *
     * @param string $message Mensaje ya redactado.
     * @return void
     */
    private static function debugLog($message)
    {
        $settings = self::getSettings();
        if (empty($settings['debug_mode'])) {
            return;
        }
        error_log('[atareao-mastodon] ' . $message);
    }

    /**
     * Registra un error (siempre) con el prefijo del módulo y sin secretos.
     *
     * @param string $message Mensaje ya redactado.
     * @return void
     */
    private static function logError($message)
    {
        error_log('[atareao-mastodon] ' . $message);
    }

    /**
     * Valida una respuesta HTTP: WP_Error o código fuera de 2xx.
     *
     * @param mixed  $response Respuesta de `wp_remote_*`.
     * @param string $label    Etiqueta del endpoint (sin credenciales).
     * @return string Mensaje accionable, o '' si la respuesta es correcta.
     */
    private static function checkResponse($response, $label)
    {
        if (is_wp_error($response)) {
            self::logError('Error de red en ' . $label . ': ' . $response->get_error_message());
            return sprintf(
                /* translators: %1$s: endpoint label, %2$s: network error message. */
                __(
                    'Mastodon: error de red en %1$s (%2$s). Revisa la conexión con la instancia.',
                    'atareao-functionality'
                ),
                $label,
                $response->get_error_message()
            );
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            self::logError('La instancia respondió ' . $code . ' en ' . $label . '.');
            return sprintf(
                /* translators: %1$d: HTTP status code, %2$s: endpoint label. */
                __(
                    'Mastodon: la instancia respondió %1$d en %2$s. Revisa la conexión y el token.',
                    'atareao-functionality'
                ),
                $code,
                $label
            );
        }
        return '';
    }

    /**
     * Registra la aplicación en la instancia y devuelve sus credenciales.
     *
     * Sin `redirection` (0) para que la respuesta de la instancia no se siga a
     * otro host. Deja el motivo del fallo en `$lastError` (sin secretos).
     *
     * @param string $instanceUrl Instancia ya validada.
     * @return array|false Datos de la app o false si falla.
     */
    private static function createApp($instanceUrl)
    {
        self::$lastError = '';

        $response = wp_safe_remote_post(
            untrailingslashit($instanceUrl) . '/api/v1/apps',
            array(
                'timeout' => self::HTTP_TIMEOUT,
                'redirection' => 0,
                'body' => array(
                    'client_name' => 'Atareao Functionality (Mastodon)',
                    'redirect_uris' => self::redirectUri(),
                    'scopes' => 'read',
                    'website' => get_site_url(),
                ),
            )
        );

        $error = self::checkResponse($response, 'api/v1/apps');
        if ($error !== '') {
            self::$lastError = $error;
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['client_id']) || empty($body['client_secret'])) {
            self::logError('Respuesta inválida al registrar la aplicación en la instancia.');
            self::$lastError = __(
                'Mastodon: la instancia no devolvió las credenciales de la aplicación.',
                'atareao-functionality'
            );
            return false;
        }
        return $body;
    }

    /**
     * Construye la URL de autorización. Es pura: no hace HTTP ni escribe nada.
     *
     * El `state` es responsabilidad de quien la invoca; si no se aporta, la URL
     * se genera sin `state` (no se usa en el flujo real, que siempre lo pasa).
     *
     * @param string $instanceUrl Instancia de Mastodon.
     * @param string $state       Valor de `state` a incluir (opcional).
     * @return string|false URL de autorización, o false si no es utilizable.
     */
    public static function getAuthorizationUrl($instanceUrl, $state = '')
    {
        if (!self::isValidInstance($instanceUrl)) {
            self::logError('Instancia no válida: se requiere una URL https://.');
            return false;
        }
        $instanceUrl = untrailingslashit(trim((string) $instanceUrl));

        $settings = self::getSettings();
        $clientId = (string) $settings['client_id'];
        if ($clientId === '') {
            return false;
        }

        $params = array(
            'client_id' => $clientId,
            'redirect_uri' => self::redirectUri(),
            'response_type' => 'code',
            'scope' => 'read',
        );
        if (is_string($state) && $state !== '') {
            $params['state'] = $state;
        }

        return $instanceUrl . '/oauth/authorize?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Genera un `state` OAuth aleatorio e impredecible.
     *
     * @return string
     */
    private static function generateState()
    {
        return wp_generate_password(64, false);
    }

    /**
     * Canjea el código de autorización y guarda el token de acceso.
     *
     * Deja el motivo del fallo en `$lastError` (sin secretos).
     *
     * @param string $code Código devuelto por la instancia.
     * @return string|false Token de acceso, o false si falla.
     */
    public static function exchangeCode($code)
    {
        self::$lastError = '';

        $settings = self::getSettings();
        $instanceUrl = untrailingslashit($settings['instance_url']);
        $incomplete = !self::isValidInstance($instanceUrl)
            || $settings['client_id'] === ''
            || $settings['client_secret'] === '';
        if ($incomplete) {
            self::logError('No se puede canjear el código: la conexión está incompleta.');
            self::$lastError = __(
                'Mastodon: no se puede canjear el código porque la conexión está incompleta.',
                'atareao-functionality'
            );
            return false;
        }

        $response = wp_safe_remote_post(
            $instanceUrl . '/oauth/token',
            array(
                'timeout' => self::HTTP_TIMEOUT,
                'redirection' => 0,
                'body' => array(
                    'grant_type' => 'authorization_code',
                    'code' => (string) $code,
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'redirect_uri' => self::redirectUri(),
                ),
            )
        );

        $error = self::checkResponse($response, 'oauth/token');
        if ($error !== '') {
            self::$lastError = $error;
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['access_token'])) {
            self::logError('Respuesta inválida al canjear el código de autorización.');
            self::$lastError = __(
                'Mastodon: la instancia no devolvió un token de acceso válido.',
                'atareao-functionality'
            );
            return false;
        }
        $token = self::sanitizeSecret((string) $body['access_token']);
        update_option(self::OPTION_PREFIX . 'access_token', $token);
        self::syncSchedule(get_option(self::OPTION_PREFIX . 'schedule_period', 'hourly'), null);
        return $token;
    }

    /**
     * Revoca el token y borra solo nuestras opciones de conexión.
     *
     * No toca las opciones del plugin legado (sirven de respaldo). Si la
     * revocación falla, deja el motivo en `$lastError` pero elimina igualmente
     * las credenciales locales.
     *
     * @return void
     */
    public static function disconnect()
    {
        self::$lastError = '';

        $settings = self::getSettings();
        $instanceUrl = untrailingslashit($settings['instance_url']);

        if (self::isValidInstance($instanceUrl) && $settings['access_token'] !== '') {
            $response = wp_safe_remote_post(
                $instanceUrl . '/oauth/revoke',
                array(
                    'timeout' => self::HTTP_TIMEOUT,
                    'redirection' => 0,
                    'body' => array(
                        'client_id' => $settings['client_id'],
                        'client_secret' => $settings['client_secret'],
                        'access_token' => $settings['access_token'],
                    ),
                )
            );
            if (is_wp_error($response)) {
                self::logError('Error de red al revocar el token de acceso.');
                self::$lastError = __(
                    'Mastodon: no se pudo revocar el token en la instancia (fallo de red), '
                    . 'pero la conexión local se ha eliminado.',
                    'atareao-functionality'
                );
            }
        }

        delete_option(self::OPTION_PREFIX . 'access_token');
        delete_option(self::OPTION_PREFIX . 'client_id');
        delete_option(self::OPTION_PREFIX . 'client_secret');
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Sincroniza el cron propio: agenda, reprograma si cambia la cadencia o
     * se abstiene mientras el legado vaya a importar. Sin duplicar eventos.
     *
     * @param string      $period         Cadencia deseada (`hourly`/`daily`).
     * @param string|null $previousPeriod Cadencia anterior (null = desconocida).
     * @return void
     */
    private static function syncSchedule($period, $previousPeriod)
    {
        if (!in_array($period, array('hourly', 'daily'), true)) {
            $period = 'hourly';
        }
        if (!self::isConnected() || self::legacyWillImport()) {
            return;
        }

        $scheduled = wp_next_scheduled(self::CRON_HOOK);
        if ($scheduled === false) {
            wp_schedule_event(time() + 60, $period, self::CRON_HOOK);
            return;
        }
        if ($previousPeriod !== null && $previousPeriod !== $period) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
            wp_schedule_event(time() + 60, $period, self::CRON_HOOK);
        }
    }

    /**
     * Relevo del cron: agenda la importación propia si procede.
     *
     * Se engancha al final de `admin_init` para que vea la conexión recién
     * importada en la misma petición. Solo agenda si hay conexión propia, el
     * legado no va a importar por su cuenta y no hay ya un evento programado.
     *
     * @param bool $force Ignora la abstención por el legado (desactivación).
     * @return void
     */
    public static function ensureSchedule($force = false)
    {
        if (!self::isConnected()) {
            return;
        }
        if (!$force && self::legacyWillImport()) {
            return;
        }
        if (wp_next_scheduled(self::CRON_HOOK) !== false) {
            return;
        }

        $period = get_option(self::OPTION_PREFIX . 'schedule_period', 'hourly');
        if (!in_array($period, array('hourly', 'daily'), true)) {
            $period = 'hourly';
        }
        wp_schedule_event(time() + 60, $period, self::CRON_HOOK);
    }

    /**
     * Al desactivarse el plugin legado, releva el cron de inmediato.
     *
     * En `deactivated_plugin` la clase legada sigue cargada, así que forzamos
     * el agendado para que el relevo no espere a la siguiente petición.
     *
     * @param string $plugin Archivo del plugin que se desactiva.
     * @return void
     */
    public static function handleLegacyDeactivation($plugin)
    {
        if (!is_string($plugin) || strpos($plugin, 'replies-importer-for-mastodon') !== 0) {
            return;
        }
        self::ensureSchedule(true);
    }

    /**
     * Deja el aviso de la pestaña en el transient (sobrevive a la redirección).
     *
     * @param string $message Mensaje legible (sin secretos).
     * @return void
     */
    private static function setNotice($message)
    {
        set_transient(self::NOTICE_TRANSIENT, $message, 30);
    }

    /**
     * Guarda los ajustes enviados por POST (nonce + manage_options).
     *
     * Solo persiste las claves presentes en el POST, de modo que las
     * credenciales gestionadas por OAuth/migración se conservan. Termina en
     * `wp_safe_redirect()` con el aviso en el transient.
     *
     * @return void
     */
    public static function maybeSaveSettings()
    {
        if (!isset($_POST['atareao_mastodon_save'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_mastodon_save', 'atareao_mastodon_nonce');

        $input = array();
        foreach (array_keys(self::defaults()) as $key) {
            $field = self::OPTION_PREFIX . $key;
            if (array_key_exists($field, $_POST)) {
                $input[$key] = wp_unslash($_POST[$field]);
            }
        }

        $previousPeriod = get_option(self::OPTION_PREFIX . 'schedule_period', 'hourly');
        $settings = self::sanitizeSettings($input);
        foreach (array_keys($input) as $key) {
            update_option(self::OPTION_PREFIX . $key, $settings[$key]);
        }

        if (array_key_exists('schedule_period', $input)) {
            self::syncSchedule($settings['schedule_period'], $previousPeriod);
        }

        self::setNotice('saved');
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * «Autorizar»: registra la app (si hace falta) e inicia el flujo OAuth.
     *
     * El registro de la aplicación ocurre aquí, nunca en el render. Genera un
     * `state` aleatorio, lo guarda (junto con el usuario) en un transient y deja
     * la URL de autorización en otro transient para que la pestaña la enlace.
     *
     * @return void
     */
    public static function handleAuthorize()
    {
        if (!isset($_POST['atareao_mastodon_authorize'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_mastodon_authorize', 'atareao_mastodon_authorize_nonce');

        $posted = isset($_POST['atareao_mastodon_instance_url'])
            ? esc_url_raw(wp_unslash((string) $_POST['atareao_mastodon_instance_url']))
            : '';
        $settings = self::getSettings();
        $instanceUrl = ($posted !== '') ? $posted : $settings['instance_url'];

        if (!self::isValidInstance($instanceUrl)) {
            self::setNotice(__(
                'Mastodon: guarda primero una instancia https:// válida antes de autorizar.',
                'atareao-functionality'
            ));
            wp_safe_redirect(self::redirectUri());
            exit;
        }
        $instanceUrl = untrailingslashit(trim((string) $instanceUrl));

        if ($posted !== '' && $posted !== $settings['instance_url']) {
            update_option(self::OPTION_PREFIX . 'instance_url', $instanceUrl);
        }

        if ($settings['client_id'] === '' || $settings['client_secret'] === '') {
            $app = self::createApp($instanceUrl);
            if ($app === false) {
                self::setNotice(self::$lastError);
                wp_safe_redirect(self::redirectUri());
                exit;
            }
            update_option(self::OPTION_PREFIX . 'client_id', sanitize_text_field((string) $app['client_id']));
            update_option(
                self::OPTION_PREFIX . 'client_secret',
                self::sanitizeSecret((string) $app['client_secret'])
            );
        }

        $state = self::generateState();
        $authUrl = self::getAuthorizationUrl($instanceUrl, $state);
        if (!is_string($authUrl) || $authUrl === '') {
            self::setNotice(__(
                'Mastodon: no se pudo construir la URL de autorización. Revisa la instancia y vuelve a intentarlo.',
                'atareao-functionality'
            ));
            wp_safe_redirect(self::redirectUri());
            exit;
        }

        set_transient(self::AUTH_URL_TRANSIENT, $authUrl, self::OAUTH_STATE_TTL);
        set_transient(
            self::OAUTH_STATE_TRANSIENT,
            array('state' => $state, 'user' => get_current_user_id()),
            self::OAUTH_STATE_TTL
        );
        self::setNotice(__(
            'Mastodon: autorización iniciada. Pulsa «Continuar la autorización» para conceder el acceso.',
            'atareao-functionality'
        ));
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * Callback OAuth: valida el `state` (de un solo uso) y canjea el código.
     *
     * No puede llevar nonce: el retorno de la instancia lo protege el `state`.
     * Si falta, no coincide, está caducado o ya se usó, no se canjea y se avisa.
     *
     * @return void
     */
    public static function handleOAuthCallback()
    {
        if (!isset($_GET['code']) || !is_scalar($_GET['code']) || (string) $_GET['code'] === '') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        $code = sanitize_text_field(wp_unslash((string) $_GET['code']));
        $state = isset($_GET['state']) && is_scalar($_GET['state'])
            ? sanitize_text_field(wp_unslash((string) $_GET['state']))
            : '';

        $stored = get_transient(self::OAUTH_STATE_TRANSIENT);
        $valid = is_array($stored)
            && isset($stored['state']) && is_string($stored['state'])
            && $state !== ''
            && hash_equals($stored['state'], $state)
            && (int) ($stored['user'] ?? -1) === (int) get_current_user_id();

        delete_transient(self::OAUTH_STATE_TRANSIENT);
        delete_transient(self::AUTH_URL_TRANSIENT);

        if (!$valid) {
            self::setNotice(__(
                'Mastodon: la autorización no se pudo verificar (state ausente, caducado o ya usado). '
                . 'Vuelve a pulsar «Autorizar».',
                'atareao-functionality'
            ));
            wp_safe_redirect(self::redirectUri());
            exit;
        }

        if (self::exchangeCode($code) !== false) {
            self::setNotice(__('Mastodon: cuenta autorizada correctamente.', 'atareao-functionality'));
        } else {
            self::setNotice(self::$lastError);
        }
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * «Comprobar ahora»: lanza una importación y deja el resultado como aviso.
     *
     * @return void
     */
    public static function handleCheckNow()
    {
        if (!isset($_POST['atareao_mastodon_check_now'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_mastodon_check_now', 'atareao_mastodon_check_nonce');

        self::setNotice(self::fetchAndImport());
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * «Desconectar»: revoca el token y borra las credenciales locales.
     *
     * @return void
     */
    public static function handleDisconnect()
    {
        if (!isset($_POST['atareao_mastodon_disconnect'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_mastodon_disconnect', 'atareao_mastodon_disconnect_nonce');

        self::disconnect();
        if (self::$lastError !== '') {
            self::setNotice(self::$lastError);
        } else {
            self::setNotice(__(
                'Mastodon: conexión eliminada y token revocado.',
                'atareao-functionality'
            ));
        }
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * «Importar la configuración del plugin legado».
     *
     * @return void
     */
    public static function handleImportLegacy()
    {
        if (!isset($_POST['atareao_mastodon_import'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('atareao_mastodon_import', 'atareao_mastodon_import_nonce');

        $count = self::importLegacy();
        if ($count > 0) {
            self::setNotice(sprintf(
                /* translators: %d: number of imported settings. */
                __('Se importaron %d ajustes desde Replies Importer for Mastodon.', 'atareao-functionality'),
                $count
            ));
        } else {
            self::setNotice(__(
                'No se encontró configuración de Replies Importer for Mastodon que importar.',
                'atareao-functionality'
            ));
        }
        wp_safe_redirect(self::redirectUri());
        exit;
    }

    /**
     * ¿Existe ya un comentario para la URL del estado dado?
     *
     * Se comprueba la meta propia (identidad canónica) y `comment_author_url`
     * (compatibilidad con lo importado por el plugin legado).
     *
     * @param string $url URL del estado de Mastodon.
     * @return bool
     */
    private static function commentExistsByUrl($url)
    {
        $byUrl = get_comments(array('author_url' => $url, 'number' => 1));
        if (!empty($byUrl)) {
            return true;
        }
        $byMeta = get_comments(
            array(
                'meta_key' => self::STATUS_META,
                'meta_value' => $url,
                'number' => 1,
            )
        );
        return !empty($byMeta);
    }

    /**
     * Importa los descendientes de un estado como comentarios pendientes.
     *
     * Resuelve el hilo con el mapa `in_reply_to_id`, descarta `private`/`direct`,
     * sanea el contenido con KSES conservando enlaces y deduplica.
     *
     * @param array $descendants Lista de respuestas de `/context`.
     * @param int   $postId      Entrada del sitio enlazada por el estado.
     * @param int   $limit       Máximo de comentarios a insertar en este lote.
     * @return array{inserted: int, skipped: int} Contadores del lote.
     */
    private static function importDescendants(array $descendants, $postId, $limit = self::COMMENT_LIMIT)
    {
        $map = array();
        $inserted = 0;
        $skipped = 0;
        foreach ($descendants as $reply) {
            if ($inserted >= $limit) {
                break;
            }
            if (!is_array($reply)) {
                $skipped++;
                continue;
            }
            $visibility = isset($reply['visibility']) ? (string) $reply['visibility'] : '';
            if ($visibility === 'private' || $visibility === 'direct') {
                $skipped++;
                continue;
            }

            $url = isset($reply['url']) ? esc_url_raw((string) $reply['url']) : '';
            if ($url === '' || self::commentExistsByUrl($url)) {
                $skipped++;
                continue;
            }

            $parent = 0;
            $inReplyTo = isset($reply['in_reply_to_id']) ? (string) $reply['in_reply_to_id'] : '';
            if ($inReplyTo !== '' && isset($map[$inReplyTo])) {
                $parent = $map[$inReplyTo];
            }

            $author = isset($reply['account']['display_name'])
                ? sanitize_text_field((string) $reply['account']['display_name'])
                : '';
            $content = isset($reply['content'])
                ? wp_kses((string) $reply['content'], wp_kses_allowed_html('comment'))
                : '';
            $created = isset($reply['created_at']) ? strtotime((string) $reply['created_at']) : false;
            $gmtDate = ($created === false) ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $created);
            $localDate = get_date_from_gmt($gmtDate);

            $commentId = wp_insert_comment(
                array(
                    'comment_post_ID' => (int) $postId,
                    'comment_author' => $author,
                    'comment_author_url' => $url,
                    'comment_content' => $content,
                    'comment_type' => '',
                    'comment_parent' => (int) $parent,
                    'user_id' => 0,
                    'comment_author_IP' => '',
                    'comment_agent' => 'Mastodon',
                    'comment_date' => $localDate,
                    'comment_date_gmt' => $gmtDate,
                    'comment_approved' => 0,
                )
            );

            if ($commentId) {
                add_comment_meta($commentId, self::STATUS_META, $url, true);
                $map[(string) $reply['id']] = (int) $commentId;
                $inserted++;
            } else {
                $skipped++;
            }
        }
        return array('inserted' => $inserted, 'skipped' => $skipped);
    }

    /**
     * Ejecuta una importación y devuelve el resultado desglosado.
     *
     * @return array{message: string, inserted: int, skipped: int, error: string}
     */
    private static function runImport()
    {
        $settings = self::getSettings();
        if (!self::isValidInstance($settings['instance_url']) || $settings['access_token'] === '') {
            self::logError('Falta la instancia https:// o el token de acceso.');
            $message = __('Mastodon: falta la instancia o el token de acceso.', 'atareao-functionality');
            return array('message' => $message, 'inserted' => 0, 'skipped' => 0, 'error' => $message);
        }

        $instanceUrl = untrailingslashit($settings['instance_url']);
        $token = $settings['access_token'];
        $headers = array('Authorization' => 'Bearer ' . $token);

        self::debugLog('Iniciando la importación de respuestas.');

        $userResponse = wp_safe_remote_get(
            $instanceUrl . '/api/v1/accounts/verify_credentials',
            array('headers' => $headers, 'timeout' => self::HTTP_TIMEOUT, 'redirection' => 0)
        );
        $error = self::checkResponse($userResponse, 'verify_credentials');
        if ($error !== '') {
            return array('message' => $error, 'inserted' => 0, 'skipped' => 0, 'error' => $error);
        }
        $userData = json_decode(wp_remote_retrieve_body($userResponse), true);
        if (!is_array($userData) || empty($userData['url'])) {
            self::logError('Respuesta inválida de verify_credentials.');
            $message = __('Mastodon: la cuenta no devolvió una URL válida.', 'atareao-functionality');
            return array('message' => $message, 'inserted' => 0, 'skipped' => 0, 'error' => $message);
        }

        $accountParsed = wp_parse_url((string) $userData['url']);
        $instanceParsed = wp_parse_url($instanceUrl);
        $sameHost = is_array($accountParsed)
            && is_array($instanceParsed)
            && isset($accountParsed['scheme'], $accountParsed['host'], $instanceParsed['host'])
            && strtolower((string) $accountParsed['scheme']) === 'https'
            && strtolower((string) $accountParsed['host']) === strtolower((string) $instanceParsed['host']);
        if (!$sameHost) {
            self::logError('El host de verify_credentials no coincide con la instancia configurada; se omite.');
            $message = __(
                'Mastodon: la cuenta no pertenece a la instancia configurada; se omite la importación.',
                'atareao-functionality'
            );
            return array('message' => $message, 'inserted' => 0, 'skipped' => 0, 'error' => $message);
        }

        $rssUrl = (string) $userData['url'] . '.rss';
        $rssResponse = wp_safe_remote_get(
            $rssUrl,
            array('timeout' => self::HTTP_TIMEOUT, 'redirection' => 0)
        );
        $error = self::checkResponse($rssResponse, 'rss');
        if ($error !== '') {
            return array('message' => $error, 'inserted' => 0, 'skipped' => 0, 'error' => $error);
        }
        $rss = simplexml_load_string(wp_remote_retrieve_body($rssResponse));
        if ($rss === false || !isset($rss->channel->item)) {
            self::logError('No se pudo interpretar el RSS de la cuenta.');
            $message = __('Mastodon: no se pudo interpretar el RSS de la cuenta.', 'atareao-functionality');
            return array('message' => $message, 'inserted' => 0, 'skipped' => 0, 'error' => $message);
        }

        $parsed = wp_parse_url($rssUrl);
        $baseApiUrl = (isset($parsed['scheme'], $parsed['host']))
            ? $parsed['scheme'] . '://' . $parsed['host']
            : $instanceUrl;
        $websiteUrl = home_url();
        $imported = 0;
        $skipped = 0;
        $rssItems = 0;
        $contextsRequested = 0;

        foreach ($rss->channel->item as $item) {
            if ($rssItems >= self::RSS_LIMIT) {
                self::debugLog('Tope de ' . self::RSS_LIMIT . ' ítems del RSS alcanzado; se trunca.');
                break;
            }
            $rssItems++;

            $content = (string) $item->description;
            if (strpos($content, $websiteUrl) === false) {
                $skipped++;
                continue;
            }
            preg_match_all(
                '/href=["\'](' . preg_quote($websiteUrl, '/') . '[^"\']+)["\']/',
                $content,
                $matches
            );
            $urls = array_unique($matches[1]);
            foreach ($urls as $url) {
                if (strpos($url, $websiteUrl) !== 0) {
                    continue;
                }
                if ($contextsRequested >= self::CONTEXT_LIMIT) {
                    self::debugLog('Tope de ' . self::CONTEXT_LIMIT . ' contextos alcanzado; se trunca.');
                    break 2;
                }
                $postId = url_to_postid($url);
                if (!$postId) {
                    $skipped++;
                    continue;
                }

                $statusId = basename((string) wp_parse_url((string) $item->link, PHP_URL_PATH));
                if ($statusId === '') {
                    $skipped++;
                    continue;
                }

                $contextResponse = wp_safe_remote_get(
                    $baseApiUrl . '/api/v1/statuses/' . $statusId . '/context',
                    array('headers' => $headers, 'timeout' => self::HTTP_TIMEOUT, 'redirection' => 0)
                );
                $contextsRequested++;
                if (self::checkResponse($contextResponse, 'statuses/context') !== '') {
                    $skipped++;
                    continue;
                }
                $context = json_decode(wp_remote_retrieve_body($contextResponse), true);
                $validContext = is_array($context)
                    && isset($context['descendants'])
                    && is_array($context['descendants']);
                if (!$validContext) {
                    self::logError('Contexto inválido para el estado ' . $statusId . '.');
                    $skipped++;
                    continue;
                }

                $counts = self::importDescendants(
                    $context['descendants'],
                    $postId,
                    max(0, self::COMMENT_LIMIT - $imported)
                );
                $imported += $counts['inserted'];
                $skipped += $counts['skipped'];
            }
        }

        if ($imported >= self::COMMENT_LIMIT) {
            self::debugLog('Tope de ' . self::COMMENT_LIMIT . ' comentarios alcanzado; se trunca.');
        }

        self::debugLog('Importación completada. Comentarios insertados: ' . $imported . '.');
        $message = sprintf(
            /* translators: %d: number of imported comments. */
            __('Mastodon: importación completada (%d comentarios).', 'atareao-functionality'),
            $imported
        );
        return array('message' => $message, 'inserted' => $imported, 'skipped' => $skipped, 'error' => '');
    }

    /**
     * Persiste el resumen del último run (fecha, insertados, omitidos, error).
     *
     * @param array $result Resultado devuelto por `runImport()`.
     * @return void
     */
    private static function recordRun(array $result)
    {
        update_option(
            self::LAST_RUN_OPTION,
            array(
                'time' => time(),
                'inserted' => (int) ($result['inserted'] ?? 0),
                'skipped' => (int) ($result['skipped'] ?? 0),
                'error' => (string) ($result['error'] ?? ''),
            )
        );
    }

    /**
     * Descubre los estados que enlazan al sitio y convierte sus respuestas en
     * comentarios pendientes. Registra el resumen del run para la pestaña.
     *
     * @return string Mensaje con el resultado (no vacío en caso de error).
     */
    public static function fetchAndImport()
    {
        $result = self::runImport();
        self::recordRun($result);
        return $result['message'];
    }

    /**
     * Pinta un campo de texto del formulario.
     *
     * @param string $key   Clave corta.
     * @param string $label Etiqueta visible.
     * @param string $type  Tipo de input (`text` o `url`).
     * @param mixed  $value Valor actual.
     * @return void
     */
    private static function renderTextField($key, $label, $type, $value)
    {
        $option = self::OPTION_PREFIX . $key;
        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label)
            . '</label></th><td><input type="' . esc_attr($type) . '" class="regular-text" id="'
            . esc_attr($option) . '" name="' . esc_attr($option) . '" value="' . esc_attr($value)
            . '" /></td></tr>';
    }

    /**
     * Pinta el resumen del último run de importación, si lo hay.
     *
     * @return void
     */
    private static function renderRunSummary()
    {
        $last = get_option(self::LAST_RUN_OPTION);
        if (!is_array($last) || empty($last['time'])) {
            return;
        }
        echo '<p class="description">' . esc_html(sprintf(
            /* translators: %1$s: date, %2$d: inserted count, %3$d: skipped count. */
            __('Última importación: %1$s; %2$d insertados, %3$d omitidos.', 'atareao-functionality'),
            date_i18n('Y-m-d H:i:s', (int) $last['time']),
            (int) ($last['inserted'] ?? 0),
            (int) ($last['skipped'] ?? 0)
        )) . '</p>';
        if (!empty($last['error'])) {
            echo '<p class="description">' . esc_html(sprintf(
                /* translators: %s: last run error message. */
                __('Último error: %s', 'atareao-functionality'),
                (string) $last['error']
            )) . '</p>';
        }
    }

    /**
     * Renderiza el contenido de la pestaña «Mastodon» del hub.
     *
     * No imprime `.wrap` ni `<h1>`: el envoltorio es del hub. Es de solo
     * lectura: no hace HTTP, no escribe opciones y no redirige. Muestra el
     * estado, los avisos (transient), el enlace de autorización pendiente, la
     * cadencia, los botones y el resumen del último run.
     *
     * @return void
     */
    public static function renderSettingsPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::getSettings();
        $notice = get_transient(self::NOTICE_TRANSIENT);
        if ($notice !== false) {
            delete_transient(self::NOTICE_TRANSIENT);
        }

        if (is_string($notice) && $notice !== '' && $notice !== 'saved') {
            echo '<div class="notice notice-info"><p>' . esc_html($notice) . '</p></div>';
        } elseif ($notice === 'saved') {
            echo '<div class="notice notice-success"><p>'
                . esc_html__('Ajustes guardados.', 'atareao-functionality') . '</p></div>';
        }

        if ($settings['instance_url'] !== '' && !self::isValidInstance($settings['instance_url'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__(
                'La instancia debe empezar por https:// (por ejemplo, https://mastodon.social). Corrige la URL y '
                . 'vuelve a guardar.',
                'atareao-functionality'
            ) . '</p></div>';
        }

        if (self::legacyWillImport()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__(
                'El plugin legado «Replies Importer for Mastodon» sigue activo y conectado, así que esta '
                . 'importación no programará su tarea hasta que lo retires. Importa la configuración, comprueba '
                . 'el resultado y desactiva el plugin legado para que este módulo tome el relevo.',
                'atareao-functionality'
            ) . '</p></div>';
        } elseif (self::legacyPluginLoaded()) {
            echo '<div class="notice notice-warning"><p>' . esc_html__(
                'El plugin legado «Replies Importer for Mastodon» sigue activo. Impórtalo y desactívalo para que '
                . 'este módulo tome el relevo.',
                'atareao-functionality'
            ) . '</p></div>';
        }

        $connected = self::isConnected();
        if ($connected) {
            echo '<p>' . esc_html__(
                'Conexión activa con la instancia de Mastodon.',
                'atareao-functionality'
            ) . '</p>';
        } else {
            echo '<p>' . esc_html__(
                'Sin conexión. Guarda la instancia y autoriza el acceso para empezar a importar.',
                'atareao-functionality'
            ) . '</p>';
        }

        echo '<form method="post">';
        wp_nonce_field('atareao_mastodon_save', 'atareao_mastodon_nonce');
        echo '<table class="form-table" role="presentation"><tbody>';
        self::renderTextField(
            'instance_url',
            __('Instancia (https://…)', 'atareao-functionality'),
            'url',
            $settings['instance_url']
        );
        echo '<tr><th scope="row"><label for="atareao_mastodon_schedule_period">'
            . esc_html__('Cadencia de importación', 'atareao-functionality')
            . '</label></th><td><select id="atareao_mastodon_schedule_period" '
            . 'name="atareao_mastodon_schedule_period">';
        echo '<option value="hourly"' . selected($settings['schedule_period'], 'hourly', false) . '>'
            . esc_html__('Cada hora', 'atareao-functionality') . '</option>';
        echo '<option value="daily"' . selected($settings['schedule_period'], 'daily', false) . '>'
            . esc_html__('Cada día', 'atareao-functionality') . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th scope="row">' . esc_html__('Depuración', 'atareao-functionality') . '</th><td>';
        echo '<label><input type="checkbox" name="atareao_mastodon_debug_mode" value="1"'
            . checked(((int) $settings['debug_mode']) === 1, true, false) . ' /> '
            . esc_html__('Registrar eventos de depuración (sin credenciales)', 'atareao-functionality')
            . '</label></td></tr>';
        echo '</tbody></table>';
        echo '<p><input type="submit" name="atareao_mastodon_save" class="button button-primary" value="'
            . esc_attr__('Guardar', 'atareao-functionality') . '"></p>';
        echo '</form>';

        if ($connected) {
            echo '<form method="post">';
            wp_nonce_field('atareao_mastodon_check_now', 'atareao_mastodon_check_nonce');
            echo '<p><input type="submit" name="atareao_mastodon_check_now" class="button" value="'
                . esc_attr__('Comprobar ahora', 'atareao-functionality') . '"></p>';
            echo '</form>';

            echo '<form method="post">';
            wp_nonce_field('atareao_mastodon_disconnect', 'atareao_mastodon_disconnect_nonce');
            echo '<p><input type="submit" name="atareao_mastodon_disconnect" class="button" value="'
                . esc_attr__('Desconectar', 'atareao-functionality') . '"></p>';
            echo '</form>';
        } else {
            echo '<form method="post">';
            wp_nonce_field('atareao_mastodon_authorize', 'atareao_mastodon_authorize_nonce');
            echo '<input type="hidden" name="atareao_mastodon_instance_url" value="'
                . esc_attr($settings['instance_url']) . '" />';
            echo '<p><input type="submit" name="atareao_mastodon_authorize" class="button button-primary" value="'
                . esc_attr__('Autorizar con Mastodon', 'atareao-functionality') . '"></p>';
            echo '</form>';

            $pendingUrl = get_transient(self::AUTH_URL_TRANSIENT);
            if (is_string($pendingUrl) && $pendingUrl !== '') {
                echo '<p><a class="button" href="' . esc_url($pendingUrl) . '">'
                    . esc_html__('Continuar la autorización con Mastodon', 'atareao-functionality')
                    . '</a></p>';
            }
        }

        echo '<form method="post">';
        wp_nonce_field('atareao_mastodon_import', 'atareao_mastodon_import_nonce');
        echo '<p><input type="submit" name="atareao_mastodon_import" class="button" value="'
            . esc_attr__('Importar la configuración del plugin legado', 'atareao-functionality') . '"></p>';
        echo '</form>';

        self::renderRunSummary();

        echo '<p class="description">' . esc_html__(
            'Los comentarios importados quedan pendientes de moderación. Desactivar el plugin no borra los '
            . 'ajustes: las opciones atareao_mastodon_* permanecen.',
            'atareao-functionality'
        ) . '</p>';
    }
}
