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

        $honeypot = isset($_POST['atareao_hp']) ? trim(wp_unslash($_POST['atareao_hp'])) : '';
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

        if (!isset($_POST['atareao_contact_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atareao_contact_nonce'])), 'atareao_contact_form')
        ) {
            $error = __('Token de seguridad invalido.', 'atareao-functionality');
        } elseif (empty($contact_name_email) || empty($contact_content)) {
            $error = __('Completa todos los campos obligatorios.', 'atareao-functionality');
        } elseif (!empty($honeypot)) {
            $error = __('Error de validacion.', 'atareao-functionality');
        } elseif (!$form_time || ($now - $form_time) < $min_seconds) {
            $error = __('Formulario enviado demasiado rapido.', 'atareao-functionality');
        } elseif (($now - $form_time) > $max_seconds) {
            $error = __('El formulario ha expirado. Recarga la pagina.', 'atareao-functionality');
        } elseif (!hash_equals($expected_sig, $captcha_sig)) {
            $error = __('No se pudo validar el captcha. Recarga la pagina.', 'atareao-functionality');
        } elseif ($captcha_answer !== ($captcha_a + $captcha_b)) {
            $error = __('Captcha incorrecto. Intentalo de nuevo.', 'atareao-functionality');
        }

        if (!isset($error)) {
            $matrix_url = esc_url_raw(get_option('atareao_matrix_url'));
            $matrix_token = sanitize_text_field(get_option('atareao_matrix_token'));
            $matrix_room = sanitize_text_field(get_option('atareao_matrix_room'));

            if (empty($matrix_url) || empty($matrix_token) || empty($matrix_room)) {
                $error = __('El formulario no esta configurado correctamente.', 'atareao-functionality');
            } else {
                $host = parse_url(home_url(), PHP_URL_HOST)
                    ? parse_url(home_url(), PHP_URL_HOST)
                    : 'atareao.es';
                $message = sprintf(
                    "Contacto de %s en %s\n%s",
                    $contact_name_email,
                    $host,
                    $contact_content
                );
                $txn_id = uniqid('wp_', true);
                $endpoint = rtrim($matrix_url, '/')
                    . "/_matrix/client/v3/rooms/$matrix_room/send/m.room.message/$txn_id";

                $response = wp_remote_request(
                    $endpoint,
                    array(
                        'method' => 'PUT',
                        'body' => wp_json_encode(
                            array(
                                'msgtype' => 'm.text',
                                'body' => $message,
                            )
                        ),
                        'headers' => array(
                            'Authorization' => 'Bearer ' . $matrix_token,
                            'Content-Type' => 'application/json',
                        ),
                        'timeout' => 10,
                    )
                );

                if (is_wp_error($response)) {
                    $error = $response->get_error_message();
                } else {
                    $response_code = intval(wp_remote_retrieve_response_code($response));
                    if ($response_code >= 200 && $response_code < 300) {
                        $redirect = add_query_arg('atareao_contact', 'success', $permalink);
                        wp_safe_redirect($redirect);
                        exit;
                    } else {
                        $error = wp_remote_retrieve_body($response);
                    }
                }
            }
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
