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
        $expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b, wp_salt('nonce'));

        // Basic spam keyword check
        $spam_keywords = array('jackpot', 'casino', 'viagra', 'seo ranking', 'bitcoin', 'crypto', 'intimate');
        $contains_spam_keyword = false;
        foreach ($spam_keywords as $keyword) {
            if (stripos($contact_content, $keyword) !== false) {
                $contains_spam_keyword = true;
                break;
            }
        }

        if (!isset($_POST['atareao_contact_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atareao_contact_nonce'])), 'atareao_contact_form')
        ) {
            $error = __('Token de seguridad invalido.', 'atareao-functionality');
        } elseif (empty($contact_name_email) || empty($contact_content)) {
            $error = __('Completa todos los campos obligatorios.', 'atareao-functionality');
        } elseif (!is_email($contact_name_email)) {
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
}
