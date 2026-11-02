<?php
/**
 * SRT upload — permite SubRip en la biblioteca de medios.
 *
 * Core registra la extensión `srt` como `text/plain` dentro del patrón
 * agrupado `txt|asc|c|cc|h|srt`. La detección real del fichero (libmagic)
 * devuelve `application/x-subrip`, y `wp_check_filetype_and_ext()` rechaza la
 * discrepancia: la subida desde la biblioteca falla con «no tienes permiso
 * para subir este tipo de archivo».
 *
 * La vía automática (el pipeline de transcripciones) no pasa por esa
 * comprobación porque crea el adjunto con `wp_upload_bits()` +
 * `wp_insert_attachment()` y fija el tipo a mano.
 *
 * Este módulo registra `srt` como `application/x-subrip` —el mismo tipo que
 * declara el feed y que ya tienen los adjuntos existentes— y lo hace ANTES
 * del patrón de core, porque `wp_check_filetype()` devuelve el primer patrón
 * que casa y el orden del mapa es lo que decide.
 *
 * @package Atareao_Functionality
 */

namespace Atareao;

if (!defined('ABSPATH')) {
    exit;
}

class SrtUpload
{
    /**
     * Engancha el filtro de tipos de subida.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('upload_mimes', array(__CLASS__, 'allowSrtMime'));
    }

    /**
     * Registra `.srt` como SubRip por delante del patrón de core.
     *
     * Anteponer no es cosmético: `wp_check_filetype()` recorre el mapa y
     * devuelve el primer patrón que casa, y core ya trae
     * `txt|asc|c|cc|h|srt => text/plain`. Añadirlo al final deja la resolución
     * en `text/plain` y la subida sigue rechazándose.
     *
     * @param array<string, string> $mimes Patrón de extensión => tipo MIME.
     * @return array<string, string>
     */
    public static function allowSrtMime(array $mimes): array
    {
        return array('srt' => PodcastFeed::TRANSCRIPT_MEDIA_TYPE) + $mimes;
    }
}
