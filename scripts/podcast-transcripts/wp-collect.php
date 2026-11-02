<?php
/**
 * Helper para `wp eval-file`: recolecta los episodios de podcast publicados.
 *
 * Imprime **solo** un JSON (sin ruido) con un array de registros, uno por episodio
 * publicado de tipo `podcast`:
 *
 *     [{"id":18828,"post_type":"podcast","status":"publish","mp3_url":"https://...","transcript_id":0}, ...]
 *
 * `transcript_id` es el ID del adjunto solo si es un entero positivo y su fichero es
 * legible desde el contenedor (equivalente a `\Atareao\PodcastFeed::isAccessibleTranscript`,
 * que es la fuente única de verdad del contrato). Cuando no se cumple, vale `0`.
 *
 * El filtro (`post_type` = `podcast`, `post_status` = `publish`) lo aplica la propia
 * consulta, no la lógica de Python.
 *
 * @package Atareao\Scripts
 */

$query = new WP_Query(
    array(
        'post_type'      => 'podcast',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    )
);

$records = array();

foreach ($query->posts as $post_id) {
    $post_id = (int) $post_id;

    $mp3_url = get_post_meta($post_id, 'mp3-url', true);
    $mp3_url = is_string($mp3_url) ? $mp3_url : '';

    // Normaliza la referencia al adjunto a un entero positivo (contrato del plugin).
    $raw_transcript = get_post_meta($post_id, 'transcript-srt', true);
    if (is_int($raw_transcript)) {
        $transcript_id = $raw_transcript > 0 ? $raw_transcript : 0;
    } elseif (is_string($raw_transcript) && preg_match('/^\d+$/', trim($raw_transcript)) === 1) {
        $transcript_id = (int) trim($raw_transcript);
    } else {
        $transcript_id = 0;
    }

    // Solo cuenta como hecho si el adjunto existe y su fichero es legible.
    if ($transcript_id > 0) {
        $file = get_attached_file($transcript_id);
        if (!is_string($file) || $file === '' || !is_readable($file)) {
            $transcript_id = 0;
        }
    }

    $records[] = array(
        'id'            => $post_id,
        'post_type'     => get_post_type($post_id),
        'status'        => get_post_status($post_id),
        'mp3_url'       => $mp3_url,
        'transcript_id' => $transcript_id,
    );
}

echo wp_json_encode($records);
