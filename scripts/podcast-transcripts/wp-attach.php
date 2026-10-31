<?php
/**
 * Helper para `wp eval-file`: importa SRT a la biblioteca y enlaza la transcripción.
 *
 * Uso:
 *
 *     wp eval-file wp-attach.php <ruta_al_manifiesto.json>
 *
 * El manifiesto es un objeto JSON `{"<episode_id>": "<ruta_del_srt>", ...}`. Para cada
 * entrada:
 *
 *   1. importa el `.srt` a la biblioteca de medios (`wp_upload_bits` +
 *      `wp_insert_attachment` + `wp_generate_attachment_metadata`);
 *   2. escribe el ID del adjunto en la meta `transcript-srt` del episodio.
 *
 * Devuelve **solo** un JSON con el resultado por episodio:
 *
 *     {"18828": {"attachment_id": 123}, "18829": {"error": "..."}}
 *
 * Si la importación falla para un episodio, **no** se escribe su meta y se reporta el
 * error. Nunca se escribe un ID que no corresponda a un adjunto realmente creado y
 * legible.
 *
 * WP-CLI expone los argumentos posicionales en la variable `$args`.
 *
 * @package Atareao\Scripts
 */

if (!defined('ABSPATH')) {
    echo wp_json_encode(array('error' => 'ABSPATH no definido: ejecutar con wp eval-file'));
    return;
}

$manifest_path = isset($args[0]) ? (string) $args[0] : '';
if ($manifest_path === '') {
    echo wp_json_encode(array('error' => 'falta la ruta del manifiesto como argumento'));
    return;
}

if (!is_readable($manifest_path)) {
    echo wp_json_encode(array('error' => 'el manifiesto no es legible: ' . $manifest_path));
    return;
}

$manifest_raw = file_get_contents($manifest_path);
$manifest     = json_decode((string) $manifest_raw, true);
if (!is_array($manifest)) {
    echo wp_json_encode(array('error' => 'el manifiesto no es un objeto JSON válido'));
    return;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$results = array();

foreach ($manifest as $episode_id => $srt_path) {
    $episode_id = (int) $episode_id;
    $srt_path   = (string) $srt_path;

    if ($episode_id <= 0) {
        $results[(string) $episode_id] = array('error' => 'ID de episodio inválido');
        continue;
    }

    if ($srt_path === '' || !is_readable($srt_path)) {
        $results[(string) $episode_id] = array('error' => 'el SRT no es legible: ' . $srt_path);
        continue;
    }

    $contents = file_get_contents($srt_path);
    if ($contents === false) {
        $results[(string) $episode_id] = array('error' => 'no se pudo leer el SRT: ' . $srt_path);
        continue;
    }

    $upload = wp_upload_bits('podcast-' . $episode_id . '.srt', null, $contents);
    if (!empty($upload['error'])) {
        $results[(string) $episode_id] = array('error' => 'subida fallida: ' . (string) $upload['error']);
        continue;
    }

    $attachment = array(
        'post_mime_type' => 'application/x-subrip',
        'post_title'     => 'Transcripción del episodio ' . $episode_id,
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $episode_id);
    if (is_wp_error($attachment_id) || !$attachment_id) {
        $message = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'insert vacío';
        $results[(string) $episode_id] = array('error' => 'no se pudo crear el adjunto: ' . $message);
        continue;
    }
    $attachment_id = (int) $attachment_id;

    wp_update_attachment_metadata(
        $attachment_id,
        wp_generate_attachment_metadata($attachment_id, $upload['file'])
    );

    // Verifica que el adjunto existe y es legible antes de enlazarlo.
    $attached_file = get_attached_file($attachment_id);
    if (!is_string($attached_file) || $attached_file === '' || !is_readable($attached_file)) {
        $results[(string) $episode_id] = array('error' => 'el adjunto no es legible tras importarlo');
        continue;
    }

    update_post_meta($episode_id, 'transcript-srt', $attachment_id);
    $results[(string) $episode_id] = array('attachment_id' => $attachment_id);
}

echo wp_json_encode($results);
