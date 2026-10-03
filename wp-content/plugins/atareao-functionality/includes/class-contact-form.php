<?php
/**
 * Contact Form Processing — validates and sends to Matrix
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class ContactForm
{

    /**
     * Inicializar
     */
    public static function init()
    {
        add_action('template_redirect', array(__CLASS__, 'handleSubmission'));
    }

    /**
     * Intercept POST to the contact page, validate, send to Matrix, redirect
     */
    public static function handleSubmission()
    {
        if ('POST' !== $_SERVER['REQUEST_METHOD']) {
            return;
        }
        if (!is_page_template('page-contact.php') && !isset($_POST['atareao_contact_form'])) {
            return;
        }

        $permalink = get_permalink();
        if (!$permalink) {
            $permalink = home_url('/');
        }

        $contact_name_email = isset($_POST['contact_name_email'])
            ? sanitize_text_field(wp_unslash($_POST['contact_name_email']))
            : '';
        $contact_content = isset($_POST['contact_content'])
            ? sanitize_textarea_field(wp_unslash($_POST['contact_content']))
            : '';

        // Renamed honeypot to trap bots that look for "website" fields
        $honeypot = isset($_POST['atareao_website']) ? trim(wp_unslash($_POST['atareao_website'])) : '';
        $captcha_answer = isset($_POST['atareao_captcha_answer']) ? intval($_POST['atareao_captcha_answer']) : null;
        $captcha_a = isset($_POST['atareao_captcha_a']) ? intval($_POST['atareao_captcha_a']) : 0;
        $captcha_b = isset($_POST['atareao_captcha_b']) ? intval($_POST['atareao_captcha_b']) : 0;
        $captcha_sig = isset($_POST['atareao_captcha_sig'])
            ? sanitize_text_field(wp_unslash($_POST['atareao_captcha_sig']))
            : '';
        $form_time = isset($_POST['atareao_form_time']) ? intval($_POST['atareao_form_time']) : 0;

        $now = time();
        $min_seconds = 3;
        $max_seconds = 3600;
        $expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b . ':' . $form_time, wp_salt('nonce'));

        // Antiabuse: fixed-window rate limiting per client IP (FR-01).
        $rate_limit_key = self::getRateLimitKey();
        $rate_limit     = self::getRateLimit();
        $rate_count     = (int) get_transient($rate_limit_key);

        // Basic spam keyword check
        $spam_keywords = array('jackpot', 'casino', 'viagra', 'seo ranking', 'bitcoin', 'crypto', 'intimate');
        $contains_spam_keyword = false;
        foreach ($spam_keywords as $keyword) {
            if (stripos($contact_content, $keyword) !== false) {
                $contains_spam_keyword = true;
                break;
            }
        }

        if ($rate_count >= $rate_limit) {
            $error = __('Error al enviar el mensaje. Intentalo de nuevo mas tarde.', 'atareao-functionality');
        } elseif (!isset($_POST['atareao_contact_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atareao_contact_nonce'])), 'atareao_contact_form')
        ) {
            $error = __('Token de seguridad invalido.', 'atareao-functionality');
        } elseif (empty($contact_name_email) || empty($contact_content)) {
            $error = __('Completa todos los campos obligatorios.', 'atareao-functionality');
        } elseif (strpos($contact_name_email, '@') !== false && !is_email($contact_name_email)) {
            $error = __('Introduce un email valido.', 'atareao-functionality');
        } elseif (!empty($honeypot)) {
            $error = __('Error de validacion.', 'atareao-functionality');
        } elseif (0 === $form_time) {
            $error = __('El formulario ha expirado. Recarga la pagina.', 'atareao-functionality');
        } elseif (($now - $form_time) < $min_seconds) {
            $error = __('Formulario enviado demasiado rapido.', 'atareao-functionality');
        } elseif (($now - $form_time) > $max_seconds) {
            $error = __('El formulario ha expirado. Recarga la pagina.', 'atareao-functionality');
        } elseif (!hash_equals($expected_sig, $captcha_sig)) {
            $error = __('No se pudo validar el captcha. Recarga la pagina.', 'atareao-functionality');
        } elseif ($captcha_answer !== ($captcha_a + $captcha_b)) {
            $error = __('Captcha incorrecto. Intentalo de nuevo.', 'atareao-functionality');
        } elseif ($contains_spam_keyword) {
            // New Rule: Block specific spam keywords
            $error = __('El mensaje contiene palabras no permitidas.', 'atareao-functionality');
        } elseif (preg_match('#https?://[^\s]+#', $contact_content)
            && '' === trim(preg_replace('#https?://[^\s]+#', '', $contact_content))
        ) {
            $error = __('El mensaje no puede contener solo un enlace.', 'atareao-functionality');
        }

        if (!isset($error)) {
            set_transient($rate_limit_key, $rate_count + 1, self::getRateWindow() * 2);

            $host = parse_url(home_url(), PHP_URL_HOST) ?: 'atareao.es';
            $message = sprintf(
                "Contacto de %s en %s\n%s",
                $contact_name_email,
                $host,
                $contact_content
            );

            $result = MatrixConfig::sendMatrixMessage($message);

            if ($result === true) {
                $redirect = add_query_arg('atareao_contact', 'success', $permalink);
                wp_safe_redirect($redirect);
                exit;
            }

            $error = __('Error al enviar el mensaje. Intentalo de nuevo mas tarde.', 'atareao-functionality');
        }

        $redirect = add_query_arg(
            array(
                'atareao_contact' => 'error',
                'atareao_msg' => rawurlencode($error),
            ),
            $permalink
        );
        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Maximum number of contact submissions allowed per client IP and window.
     *
     * Adjustable through the `atareao_contact_rate_limit` filter or the
     * ATAREAO_CONTACT_RATE_LIMIT constant.
     */
    private static function getRateLimit(): int
    {
        $limit = defined('ATAREAO_CONTACT_RATE_LIMIT') ? (int) ATAREAO_CONTACT_RATE_LIMIT : 5;
        return max(1, (int) apply_filters('atareao_contact_rate_limit', $limit));
    }

    /**
     * Rate limiting window length, in seconds.
     *
     * Adjustable through the `atareao_contact_rate_window` filter or the
     * ATAREAO_CONTACT_RATE_WINDOW constant.
     */
    private static function getRateWindow(): int
    {
        $window = defined('ATAREAO_CONTACT_RATE_WINDOW') ? (int) ATAREAO_CONTACT_RATE_WINDOW : 3600;
        return max(1, (int) apply_filters('atareao_contact_rate_window', $window));
    }

    /**
     * Fixed-window transient key for the client IP.
     *
     * Uses only the server-observed REMOTE_ADDR (never proxy headers such as
     * X-Forwarded-For) and stores a salted hash, never the address in clear.
     */
    private static function getRateLimitKey(): string
    {
        $ip     = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $bucket = (int) floor(time() / self::getRateWindow());
        return 'atareao_contact_rl_' . hash('sha256', $ip . '|' . wp_salt('nonce')) . '_' . $bucket;
    }
}
