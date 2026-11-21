<?php
/**
 * Helper para `wp eval-file`: lee, reemplaza o restaura el SRT de una transcripción.
 *
 * Uso:
 *
 *     wp eval-file wp-backfill.php get <episode_id>
 *     wp eval-file wp-backfill.php put <episode_id> <ruta_al_srt_nuevo>
 *     wp eval-file wp-backfill.php revert <episode_id>
 *
 * Comportamiento por acción (siempre imprime **solo** un objeto JSON):
 *
 *   - `get`    -> {"episode_id":N,"attachment_id":A,"content":"<base64>"}
 *                 o {"episode_id":N,"attachment_id":0} si no hay transcripción.
 *   - `put`    -> {"episode_id":N,"attachment_id":A,"changed":bool}
 *                 Conserva, la primera vez, una copia del fichero actual como
 *                 `<fichero>.orig.srt` **junto al adjunto** y después sobrescribe
 *                 el propio fichero del adjunto. No crea adjuntos nuevos y no
 *                 toca la meta `transcript-srt`: su ID y su valor no cambian.
 *   - `revert` -> {"episode_id":N,"attachment_id":A,"reverted":bool}
 *                 Restaura `<fichero>.orig.srt` sobre el fichero del adjunto.
 *                 `reverted` es `false` si no existe la copia del original.
 *
 * Cualquier error se devuelve como {"error":"..."} y nunca modifica nada a medias.
 *
 * WP-CLI expone los argumentos posicionales en la variable `$args`.
 *
 * @package Atareao\Scripts
 */

if (!defined('ABSPATH')) {
    echo wp_json_encode(array('error' => 'ABSPATH no definido: ejecutar con wp eval-file'));
    return;
}

$action = isset($args[0]) ? (string) $args[0] : '';
$episode_id = isset($args[1]) ? (int) $args[1] : 0;

if ($episode_id <= 0) {
    echo wp_json_encode(array('error' => 'ID de episodio inválido'));
    return;
}

/**
 * Resuelve el fichero del adjunto enlazado por la meta `transcript-srt`.
 *
 * @param int $episode_id ID del episodio.
 * @return array{0:int,1:string} ID del adjunto y ruta de su fichero, o [0, ''].
 */
function atareao_backfill_resolve_attachment($episode_id)
{
    $raw = get_post_meta($episode_id, 'transcript-srt', true);
    $attachment_id = is_numeric($raw) ? (int) $raw : 0;
    if ($attachment_id <= 0) {
        return array(0, '');
    }

    $file = get_attached_file($attachment_id);
    if (!is_string($file) || $file === '') {
        return array(0, '');
    }

    return array($attachment_id, $file);
}

if (in_array($action, array('get', 'put', 'revert'), true)) {
    list($attachment_id, $file) = atareao_backfill_resolve_attachment($episode_id);
} else {
    echo wp_json_encode(array('error' => 'acción no reconocida: ' . $action));
    return;
}

if ($action === 'get') {
    if ($attachment_id <= 0 || !is_readable($file)) {
        echo wp_json_encode(array('episode_id' => $episode_id, 'attachment_id' => 0));
        return;
    }
    $contents = file_get_contents($file);
    if ($contents === false) {
        echo wp_json_encode(array('error' => 'no se pudo leer el SRT del adjunto'));
        return;
    }
    echo wp_json_encode(
        array(
            'episode_id'    => $episode_id,
            'attachment_id' => $attachment_id,
            'content'       => base64_encode($contents),
        )
    );
    return;
}

if ($action === 'put') {
    $source = isset($args[2]) ? (string) $args[2] : '';
    if ($attachment_id <= 0 || !is_readable($file)) {
        echo wp_json_encode(array('error' => 'el adjunto de la transcripción no es legible'));
        return;
    }
    if ($source === '' || !is_readable($source)) {
        echo wp_json_encode(array('error' => 'el SRT nuevo no es legible: ' . $source));
        return;
    }

    $contents = file_get_contents($source);
    if ($contents === false) {
        echo wp_json_encode(array('error' => 'no se pudo leer el SRT nuevo'));
        return;
    }

    $original = $file . '.orig.srt';
    // La copia del original se guarda una sola vez: si ya existe, se preserva la
    // primera (el original real), de modo que revertir siempre vuelve al estado
    // previo al backfill y una segunda pasada no la pisa.
    if (!file_exists($original)) {
        if (!copy($file, $original)) {
            echo wp_json_encode(array('error' => 'no se pudo conservar el original'));
            return;
        }
    }

    $changed = $contents !== file_get_contents($file);
    if (file_put_contents($file, $contents) === false) {
        echo wp_json_encode(array('error' => 'no se pudo sobrescribir el SRT del adjunto'));
        return;
    }

    clearstatcache(true, $file);
    echo wp_json_encode(
        array(
            'episode_id'    => $episode_id,
            'attachment_id' => $attachment_id,
            'changed'       => $changed,
        )
    );
    return;
}

// $action === 'revert'
$original = $file . '.orig.srt';
if ($attachment_id <= 0 || !is_readable($original)) {
    echo wp_json_encode(
        array('episode_id' => $episode_id, 'attachment_id' => $attachment_id, 'reverted' => false)
    );
    return;
}

if (!copy($original, $file)) {
    echo wp_json_encode(array('error' => 'no se pudo restaurar el original'));
    return;
}

echo wp_json_encode(
    array('episode_id' => $episode_id, 'attachment_id' => $attachment_id, 'reverted' => true)
);
