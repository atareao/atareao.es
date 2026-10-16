<?php
/**
 * Test stub for the Matrix notifications module.
 *
 * The contact-form code under test dispatches to `Atareao\MatrixConfig`, a
 * class that talks to a live Matrix homeserver. This stub replaces it with an
 * in-memory recorder so the tests can assert whether the Matrix channel was
 * used without performing any network access.
 *
 * @package Atareao_Functionality\Tests
 */

namespace Atareao;

/**
 * Records calls to sendMatrixMessage() and returns a configurable result.
 */
class MatrixConfig
{
    /**
     * Send a message to Matrix (stubbed).
     *
     * Appends the message to the `$mock_matrix_calls` global and returns the
     * value of `$mock_matrix_send_result` (true by default).
     *
     * @param string $message_body Message to deliver.
     * @return true|string True on success, error string on failure.
     */
    public static function sendMatrixMessage($message_body)
    {
        $GLOBALS['mock_matrix_calls'][] = $message_body;

        return isset($GLOBALS['mock_matrix_send_result'])
            ? $GLOBALS['mock_matrix_send_result']
            : true;
    }

    /**
     * Render the Matrix settings tab (stub).
     *
     * The settings hub only stores this callback in its tab registry; keeping
     * it here mirrors the real module's public surface.
     *
     * @return void
     */
    public static function renderConfigPage()
    {
    }
}
