<?php
/**
 * Comment Security — captcha, honeypot, timing validation
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class CommentSecurity
{

    /**
     * Inicializar
     */
    public static function init()
    {
        add_filter('preprocess_comment', array(__CLASS__, 'validateComment'));
    }

    /**
     * Validate comment submission: captcha, honeypot and timing
     */
    public static function validateComment($commentdata)
    {
        if (is_admin()) {
            return $commentdata;
        }

        $error = '';

        $user_captcha = isset($_POST['atareao_comment_captcha'])
            ? intval($_POST['atareao_comment_captcha'])
            : null;
        $captcha_a = isset($_POST['atareao_comment_captcha_a'])
            ? intval($_POST['atareao_comment_captcha_a'])
            : 0;
        $captcha_b = isset($_POST['atareao_comment_captcha_b'])
            ? intval($_POST['atareao_comment_captcha_b'])
            : 0;
        $captcha_sig = isset($_POST['atareao_comment_captcha_sig'])
            ? sanitize_text_field(wp_unslash($_POST['atareao_comment_captcha_sig']))
            : '';
        $expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b, wp_salt('nonce'));
        $honeypot = isset($_POST['atareao_comment_hp'])
            ? trim(wp_unslash($_POST['atareao_comment_hp']))
            : '';
        $form_time = isset($_POST['atareao_comment_form_time'])
            ? intval($_POST['atareao_comment_form_time'])
            : 0;
        $now = time();

        if (!empty($honeypot)) {
            $error = __('Error de validación.', 'atareao-functionality');
        } elseif (null === $user_captcha || $user_captcha !== ($captcha_a + $captcha_b)) {
            $error = __('Captcha incorrecto. Inténtalo de nuevo.', 'atareao-functionality');
        } elseif (!hash_equals($expected_sig, $captcha_sig)) {
            $error = __('No se pudo validar el captcha. Recarga la página.', 'atareao-functionality');
        } elseif ($form_time && ($now - $form_time) < 2) {
            $error = __('Formulario enviado demasiado rápido.', 'atareao-functionality');
        }

        if (!empty($error)) {
            $ref = wp_get_referer() ? wp_get_referer() : home_url('/');
            $ref = add_query_arg('atareao_comment_error', rawurlencode($error), $ref);
            wp_safe_redirect($ref . '#respond');
            exit;
        }

        return $commentdata;
    }

    /**
     * Process AJAX comment submission: validate, insert, return result.
     * Called from the theme's AJAX handler.
     *
     * @return array Array with keys: status, message, comment (WP_Comment|null),
     *               new_a, new_b, new_time, parent
     */
    public static function processAjaxComment()
    {
        $new_a = rand(1, 9);
        $new_b = rand(1, 9);
        $new_sig = hash_hmac('sha256', $new_a . ':' . $new_b, wp_salt('nonce'));
        $new_time = time();

        $captcha_response = array(
            'new_a' => $new_a,
            'new_b' => $new_b,
            'new_sig' => $new_sig,
            'new_time' => $new_time,
        );

        if (!check_ajax_referer('atareao_comment_nonce', 'nonce', false)) {
            return array_merge(
                array('status' => 'error', 'message' => __('Token de seguridad invalido.', 'atareao-functionality')),
                $captcha_response
            );
        }

        $post_id = isset($_POST['comment_post_ID']) ? intval($_POST['comment_post_ID']) : 0;
        $parent  = isset($_POST['comment_parent']) ? intval($_POST['comment_parent']) : 0;
        $author  = isset($_POST['author']) ? sanitize_text_field(wp_unslash($_POST['author'])) : '';
        $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $url     = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';
        $user_captcha = isset($_POST['atareao_comment_captcha'])
            ? intval($_POST['atareao_comment_captcha'])
            : null;
        $captcha_a = isset($_POST['atareao_comment_captcha_a'])
            ? intval($_POST['atareao_comment_captcha_a'])
            : 0;
        $captcha_b = isset($_POST['atareao_comment_captcha_b'])
            ? intval($_POST['atareao_comment_captcha_b'])
            : 0;
        $captcha_sig = isset($_POST['atareao_comment_captcha_sig'])
            ? sanitize_text_field(wp_unslash($_POST['atareao_comment_captcha_sig']))
            : '';
        $expected_sig = hash_hmac('sha256', $captcha_a . ':' . $captcha_b, wp_salt('nonce'));
        $honeypot = isset($_POST['atareao_comment_hp'])
            ? trim(wp_unslash($_POST['atareao_comment_hp']))
            : '';
        $form_time = isset($_POST['atareao_comment_form_time'])
            ? intval($_POST['atareao_comment_form_time'])
            : 0;
        $now = time();

        $error = '';
        if (!empty($honeypot)) {
            $error = __('Error de validación.', 'atareao-functionality');
        } elseif (null === $user_captcha || $user_captcha !== ($captcha_a + $captcha_b)) {
            $error = __('Captcha incorrecto. Inténtalo de nuevo.', 'atareao-functionality');
        } elseif (!hash_equals($expected_sig, $captcha_sig)) {
            $error = __('No se pudo validar el captcha. Recarga la página.', 'atareao-functionality');
        } elseif ($form_time && ($now - $form_time) < 2) {
            $error = __('Formulario enviado demasiado rápido.', 'atareao-functionality');
        }

        if (!empty($error)) {
            return array_merge(array('status' => 'error', 'message' => $error), $captcha_response);
        }

        $commentdata = array(
            'comment_post_ID' => $post_id,
            'comment_parent'  => $parent,
            'comment_author'  => $author,
            'comment_author_email' => $email,
            'comment_author_url' => $url,
            'comment_content' => $comment,
            'user_ID'         => get_current_user_id(),
        );

        $comment_id = wp_new_comment($commentdata);
        if (is_wp_error($comment_id)) {
            return array_merge(
                array('status' => 'error', 'message' => $comment_id->get_error_message()),
                $captcha_response
            );
        }

        return array_merge(
            array(
                'status' => 'success',
                'comment' => get_comment($comment_id),
            ),
            $captcha_response
        );
    }
}
