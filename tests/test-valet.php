<?php
/**
 * Procedural test suite for the Valet notifications capability.
 *
 * Covers the BDD scenarios of openspec/changes/valet-notifications/specs/.
 * Run it with `just test` (or `php tests/test-valet.php`). The exit code is 0
 * when every assertion passes and 1 otherwise.
 *
 * Child mode: the script re-invokes itself as
 * `php tests/test-valet.php --child=<scenario>` to exercise
 * ContactForm::handleSubmission(), which calls exit() and therefore can only
 * be observed from a separate process (see harness_run_child()).
 *
 * @package Atareao_Functionality\Tests
 */

require_once __DIR__ . '/wp-stubs.php';
require_once __DIR__ . '/class-matrix-config-stub.php';

$plugin_includes = dirname(__DIR__) . '/wp-content/plugins/atareao-functionality/includes';
require_once $plugin_includes . '/class-valet-config.php';
require_once $plugin_includes . '/class-contact-form.php';
require_once $plugin_includes . '/class-settings.php';

// ---------------------------------------------------------------------------
// Child mode: run one contact-form scenario and let the code under test exit.
// ---------------------------------------------------------------------------
$child_scenario = null;
if (isset($argv) && is_array($argv)) {
    foreach ($argv as $argument) {
        if (strpos($argument, '--child=') === 0) {
            $child_scenario = substr($argument, strlen('--child='));
        }
    }
}

if ($child_scenario !== null) {
    harness_run_child_scenario($child_scenario);
    exit(0);
}

// ---------------------------------------------------------------------------
// Parent mode: run the suite.
// ---------------------------------------------------------------------------
harness_reset_state();

$empty_report = array(
    'redirect' => '',
    'redirect_count' => 0,
    'valet_requests' => array(),
    'matrix_calls' => array(),
    'options' => array(),
);

$child_report = function ($scenario) {
    $report = harness_run_child(__FILE__, $scenario);

    return is_array($report) ? $report : array(
        'redirect' => '',
        'redirect_count' => 0,
        'valet_requests' => array(),
        'matrix_calls' => array(),
        'options' => array(),
    );
};

echo "=== Running Valet Notifications Test Suite ===\n";

// Test 1: Incomplete credentials
echo "\n--- Scenario: Credenciales incompletas ---\n";
update_option('atareao_valet_url', '');
update_option('atareao_valet_token', '');
$res = \Atareao\ValetConfig::sendValetNotification('Test message');
assert_true(
    is_string($res) && strpos($res, 'Configura la URL y el Token') !== false,
    'Rejects when url and token are missing'
);

update_option('atareao_valet_url', 'https://valet.example.com');
update_option('atareao_valet_token', '');
$res = \Atareao\ValetConfig::sendValetNotification('Test message');
assert_true(
    is_string($res) && strpos($res, 'Configura la URL y el Token') !== false,
    'Rejects when token is missing'
);

// Test 2: Message validation
echo "\n--- Scenario: Validación de mensaje (vacío o > 4096 bytes) ---\n";
update_option('atareao_valet_url', 'https://valet.example.com');
update_option('atareao_valet_token', 'token-123');
$res = \Atareao\ValetConfig::sendValetNotification('   ');
assert_true(is_string($res) && strpos($res, 'obligatorio') !== false, 'Rejects empty/whitespace message');

$too_long_message = str_repeat('a', 4097);
$res = \Atareao\ValetConfig::sendValetNotification($too_long_message);
assert_true(is_string($res) && strpos($res, '4096') !== false, 'Rejects message exceeding 4096 bytes');

// Test 3: Successful dispatch (201 Created)
echo "\n--- Scenario: Envío correcto con respuesta 201 ---\n";
$mock_remote_requests = array();
$mock_remote_response = array('response' => array('code' => 201), 'body' => '{"id":"uuid-1"}');
$res = \Atareao\ValetConfig::sendValetNotification('Mensaje valido', 'atareao.es', 'Lorenzo', 'info');
assert_true($res === true, 'sendValetNotification returns true on 201 response');
assert_equals(1, count($mock_remote_requests), 'HTTP request was made');
$req = $mock_remote_requests[0];
assert_equals('https://valet.example.com/api/notifications', $req['url'], 'Endpoint is /api/notifications');
assert_equals(
    'Bearer token-123',
    $req['args']['headers']['Authorization'],
    'Authorization header has Bearer token'
);
assert_equals('application/json', $req['args']['headers']['Content-Type'], 'Content-Type is application/json');
$body = json_decode($req['args']['body'], true);
assert_equals('Mensaje valido', $body['message'], 'Payload contains correct message');
assert_equals('atareao.es', $body['origin'], 'Payload contains origin');
assert_equals('Lorenzo', $body['author'], 'Payload contains author');
assert_equals('info', $body['level'], 'Payload contains level info');

// Test 4: Default origin and level validation
echo "\n--- Scenario: Normalización de origin y level ---\n";
$mock_remote_requests = array();
$res = \Atareao\ValetConfig::sendValetNotification('Normalizar', '', '', 'invalid_level');
assert_true($res === true, 'sendValetNotification succeeds with defaults');
$body = json_decode($mock_remote_requests[0]['args']['body'], true);
assert_equals('atareao.es', $body['origin'], "Origin defaults to hostname");
assert_equals('info', $body['level'], "Invalid level defaults to 'info'");

// Test 5: HTTP error response (401 / 500)
echo "\n--- Scenario: Respuesta HTTP de error ---\n";
$mock_remote_requests = array();
$mock_remote_response = array('response' => array('code' => 401), 'body' => '{"error":"unauthorized"}');
$res = \Atareao\ValetConfig::sendValetNotification('Test error');
assert_true(is_string($res), 'Returns error string on HTTP 401');

// Test 6: Network error / WP_Error
echo "\n--- Scenario: Error de red o timeout ---\n";
$mock_remote_requests = array();
$mock_remote_response = new WP_Error('http_request_failed', 'Connection timed out');
$res = \Atareao\ValetConfig::sendValetNotification('Test timeout');
assert_true(is_string($res), 'Returns error string on WP_Error');

// Test 7: Comment notifications
echo "\n--- Scenario: Notificación de comentarios aprobados vs no aprobados ---\n";
$mock_remote_requests = array();
$mock_remote_response = array('response' => array('code' => 201), 'body' => '{}');
$mock_comments[42] = array(
    'comment_author' => 'Juan',
    'comment_content' => 'Excelente articulo',
    'comment_post_ID' => 10,
    'comment_ID' => 42,
);

\Atareao\ValetConfig::notifyOnComment(42, 0);
assert_equals(0, count($mock_remote_requests), 'Unapproved comment (0) does not dispatch notification');

\Atareao\ValetConfig::notifyOnComment(42, 1);
assert_equals(1, count($mock_remote_requests), 'Approved comment (1) dispatches notification');
$req_body = json_decode($mock_remote_requests[0]['args']['body'], true);
assert_equals('Juan', $req_body['author'], 'Comment author passed to notification');
assert_true(
    strpos($req_body['message'], 'Excelente articulo') !== false,
    'Comment body included in notification'
);

// Test 8: sendTestMessage
echo "\n--- Scenario: Envío de mensaje de prueba ---\n";
$mock_remote_requests = array();
$res = \Atareao\ValetConfig::sendTestMessage();
assert_true($res === true, 'sendTestMessage returns true');
assert_equals(1, count($mock_remote_requests), 'sendTestMessage dispatches notification');

// Test 9: Settings Hub tabs
echo "\n--- Scenario: Pestaña Valet en el Hub de Ajustes ---\n";
$tabs = \Atareao\Settings::tabs();
$tab_keys = array_keys($tabs);
assert_equals(
    array('matrix', 'pocketid', 'umami', 'mastodon', 'valet', 'tema'),
    $tab_keys,
    'Settings::tabs has 6 tabs in exact order'
);
assert_equals('Valet', $tabs['valet']['label'], "Valet tab label is 'Valet'");
assert_equals(
    array(\Atareao\ValetConfig::class, 'renderConfigPage'),
    $tabs['valet']['callback'],
    'Valet tab callback is ValetConfig::renderConfigPage'
);

// Test 10: renderConfigPage save (valid nonce + capability)
echo "\n--- Scenario: Guardado por POST en renderConfigPage ---\n";
$_POST = array(
    'atareao_valet_save' => '1',
    'atareao_valet_nonce' => 'valid_nonce',
    'atareao_valet_url' => 'https://valet.myhost.local',
    'atareao_valet_token' => 'my-secret-key-456',
);
ob_start();
\Atareao\ValetConfig::renderConfigPage();
$output = ob_get_clean();
assert_equals('https://valet.myhost.local', get_option('atareao_valet_url'), 'URL saved correctly from form');
assert_equals('my-secret-key-456', get_option('atareao_valet_token'), 'Token saved correctly from form');
assert_true(strpos($output, 'Configuración guardada') !== false, 'Displays success notice on save');

// Test 11: init() registers both hooks (A3)
echo "\n--- Scenario: init() registra los dos hooks (A3) ---\n";
$mock_actions = array();
\Atareao\ValetConfig::init();
assert_true(isset($mock_actions['comment_post'][0]), 'init() registers the comment_post hook');
assert_equals(
    array(\Atareao\ValetConfig::class, 'notifyOnComment'),
    $mock_actions['comment_post'][0]['callback'],
    'comment_post is bound to notifyOnComment'
);
assert_equals(2, $mock_actions['comment_post'][0]['args'], 'comment_post hook receives 2 arguments');
assert_true(
    isset($mock_actions['transition_comment_status'][0]),
    'init() registers the transition_comment_status hook'
);
assert_equals(
    array(\Atareao\ValetConfig::class, 'notifyOnCommentTransition'),
    $mock_actions['transition_comment_status'][0]['callback'],
    'transition_comment_status is bound to notifyOnCommentTransition'
);
assert_equals(
    3,
    $mock_actions['transition_comment_status'][0]['args'],
    'transition_comment_status hook receives 3 arguments'
);

// Test 12: notifyOnCommentTransition only fires on -> approved (A3)
echo "\n--- Scenario: transición a approved (A3) ---\n";
$mock_remote_requests = array();
$mock_remote_response = array('response' => array('code' => 201), 'body' => '{}');
$transition_comment = (object) array(
    'comment_author' => 'Ana',
    'comment_content' => 'Hola',
    'comment_post_ID' => 7,
    'comment_ID' => 77,
);

\Atareao\ValetConfig::notifyOnCommentTransition('approved', 'hold', $transition_comment);
assert_equals(1, count($mock_remote_requests), 'approved<-hold dispatches one notification');

$mock_remote_requests = array();
\Atareao\ValetConfig::notifyOnCommentTransition('approved', 'approved', $transition_comment);
assert_equals(0, count($mock_remote_requests), 'approved<-approved (repeated) dispatches nothing');

$mock_remote_requests = array();
\Atareao\ValetConfig::notifyOnCommentTransition('hold', 'approved', $transition_comment);
assert_equals(0, count($mock_remote_requests), 'hold<-approved (unapproval) dispatches nothing');

$mock_remote_requests = array();
\Atareao\ValetConfig::notifyOnCommentTransition('spam', 'approved', $transition_comment);
assert_equals(0, count($mock_remote_requests), 'spam<-approved dispatches nothing');

// Test 13: realistic insertion flows do not double-notify (A3)
echo "\n--- Scenario: sin doble aviso en el flujo realista (A3) ---\n";
$mock_remote_requests = array();
\Atareao\ValetConfig::notifyOnComment(42, 0);
assert_equals(0, count($mock_remote_requests), 'A pending comment inserted does not notify');

\Atareao\ValetConfig::notifyOnCommentTransition('approved', 'hold', (object) $mock_comments[42]);
assert_equals(1, count($mock_remote_requests), 'pending->approved notifies exactly once');

$mock_remote_requests = array();
\Atareao\ValetConfig::notifyOnComment(42, 1);
assert_equals(1, count($mock_remote_requests), 'Already-approved comment notifies once via comment_post');

\Atareao\ValetConfig::notifyOnCommentTransition('approved', 'approved', (object) $mock_comments[42]);
assert_equals(1, count($mock_remote_requests), 'Repeated approved transition does not double-notify');

// Test 14: long comment is byte-truncated, never rejected (A4)
echo "\n--- Scenario: comentario largo recortado sin superar 4096 bytes (A4) ---\n";
$mock_remote_requests = array();
$mock_response_201 = array('response' => array('code' => 201), 'body' => '{}');
$mock_remote_response = $mock_response_201;
$mock_comments[100] = array(
    'comment_author' => 'Largo',
    'comment_content' => str_repeat('a', 10000),
    'comment_post_ID' => 3,
    'comment_ID' => 100,
);

\Atareao\ValetConfig::notifyOnComment(100, 1);
assert_equals(1, count($mock_remote_requests), 'A 10 kB comment dispatches exactly one request');
$body = json_decode($mock_remote_requests[0]['args']['body'], true);
assert_true(is_array($body), 'Long comment payload decodes as JSON');
$message = isset($body['message']) ? $body['message'] : '';
assert_true(strlen($message) <= 4096, 'Comment notification never exceeds 4096 bytes');
assert_equals("\xE2\x80\xA6", substr($message, -3), 'Truncated message ends with the ellipsis suffix');

// Test 15: byte-counted truncation keeps multibyte characters intact (A4)
echo "\n--- Scenario: recorte por bytes con caracteres multibyte (A4) ---\n";
$mock_remote_requests = array();
$mock_remote_response = $mock_response_201;
$mock_comments[101] = array(
    'comment_author' => 'Unicode',
    'comment_content' => str_repeat('á', 3000),
    'comment_post_ID' => 4,
    'comment_ID' => 101,
);

\Atareao\ValetConfig::notifyOnComment(101, 1);
assert_equals(
    1,
    count($mock_remote_requests),
    'A 6000-byte multibyte comment dispatches exactly one request'
);
$body = json_decode($mock_remote_requests[0]['args']['body'], true);
assert_true(is_array($body), 'Multibyte payload decodes as JSON');
$message = isset($body['message']) ? $body['message'] : '';
assert_true(strlen($message) <= 4096, 'Multibyte message respects the 4096-byte budget');
assert_true(mb_check_encoding($message, 'UTF-8'), 'Multibyte message keeps valid UTF-8 (no split character)');

// Test 16: the form never reprints the stored token (A7)
echo "\n--- Scenario: el formulario nunca reimprime el token (A7) ---\n";
$mock_admin_nonce_valid = true;
$mock_user_can_manage_options = true;
update_option('atareao_valet_url', 'https://valet.example.com');
update_option('atareao_valet_token', 'super-secret-token');
$_POST = array();
ob_start();
\Atareao\ValetConfig::renderConfigPage();
$html = ob_get_clean();
assert_true(strpos($html, 'super-secret-token') === false, 'The stored token is never printed');
assert_true(strpos($html, 'type="password"') !== false, 'Token field is type=password');
assert_true(strpos($html, 'autocomplete="new-password"') !== false, 'Token field opts out of autofill');
assert_true(strpos($html, 'value="super-secret-token"') === false, 'Token field has no value attribute');

// Test 17: empty or whitespace-only token keeps the previous value (A7)
echo "\n--- Scenario: token vacío conserva el anterior (A7) ---\n";
$_POST = array(
    'atareao_valet_save' => '1',
    'atareao_valet_nonce' => 'valid_nonce',
    'atareao_valet_url' => 'https://valet.example.com',
    'atareao_valet_token' => '',
);
ob_start();
\Atareao\ValetConfig::renderConfigPage();
ob_get_clean();
assert_equals('super-secret-token', get_option('atareao_valet_token'), 'Empty token POST preserves the token');

$_POST['atareao_valet_token'] = '   ';
ob_start();
\Atareao\ValetConfig::renderConfigPage();
ob_get_clean();
assert_equals(
    'super-secret-token',
    get_option('atareao_valet_token'),
    'Whitespace-only token POST preserves the token'
);

// Test 18: invalid or missing nonce must not save (A1)
echo "\n--- Scenario: nonce inválido no guarda (A1) ---\n";
$mock_user_can_manage_options = true;
$mock_admin_nonce_valid = false;
update_option('atareao_valet_url', 'https://previous.example.com');
update_option('atareao_valet_token', 'previous-token');
$_POST = array(
    'atareao_valet_save' => '1',
    'atareao_valet_url' => 'https://evil.example.com',
    'atareao_valet_token' => 'evil-token',
);
$nonce_aborted = false;
ob_start();
try {
    \Atareao\ValetConfig::renderConfigPage();
} catch (WpDieSignal $e) {
    $nonce_aborted = true;
}
$html = ob_get_clean();
assert_true($nonce_aborted, 'renderConfigPage aborts when check_admin_referer fails');
assert_equals(
    'https://previous.example.com',
    get_option('atareao_valet_url'),
    'URL is not updated with an invalid nonce'
);
assert_equals(
    'previous-token',
    get_option('atareao_valet_token'),
    'Token is not updated with an invalid nonce'
);
assert_true(strpos($html, 'Configuración guardada') === false, 'No save notice with an invalid nonce');

// Test 19: without manage_options nothing is rendered nor saved (A1)
echo "\n--- Scenario: sin manage_options no hay formulario ni guardado (A1) ---\n";
$mock_admin_nonce_valid = true;
$mock_user_can_manage_options = false;
update_option('atareao_valet_url', 'https://previous.example.com');
update_option('atareao_valet_token', 'previous-token');
$_POST = array(
    'atareao_valet_save' => '1',
    'atareao_valet_nonce' => 'valid_nonce',
    'atareao_valet_url' => 'https://evil.example.com',
    'atareao_valet_token' => 'evil-token',
);
ob_start();
\Atareao\ValetConfig::renderConfigPage();
$html = ob_get_clean();
assert_equals('', $html, 'No output without manage_options');
assert_true(strpos($html, '<form') === false, 'The form is not rendered without manage_options');
assert_equals(
    'https://previous.example.com',
    get_option('atareao_valet_url'),
    'Options are untouched without manage_options'
);

// Test 20: esc_url_raw sanitises the saved URL
echo "\n--- Scenario: esc_url_raw sanea la URL guardada ---\n";
$mock_user_can_manage_options = true;
$mock_admin_nonce_valid = true;
$mock_options['atareao_valet_url'] = '';
$_POST = array(
    'atareao_valet_save' => '1',
    'atareao_valet_nonce' => 'valid_nonce',
    'atareao_valet_url' => 'https://valet.example.com/has space',
    'atareao_valet_token' => 'tok',
);
ob_start();
\Atareao\ValetConfig::renderConfigPage();
ob_get_clean();
$saved_url = get_option('atareao_valet_url');
assert_true(strpos($saved_url, ' ') === false, 'The stored URL contains no raw space');
assert_equals('https://valet.example.com/has%20space', $saved_url, 'A literal space is encoded as %20');
assert_equals('', esc_url_raw('javascript:alert(1)'), 'esc_url_raw rejects a javascript: URL');
assert_equals('https://valet.example.com', esc_url_raw('https://valet.example.com'), 'esc_url_raw keeps a valid URL');

// Test 21: ContactForm::handleSubmission() in real subprocesses
echo "\n--- Scenario: ContactForm entrega real vía subproceso ---\n";
$report = harness_run_child(__FILE__, 'valet_only_success');
assert_true(is_array($report), 'Child subprocess returned a JSON report');
$report = is_array($report) ? $report : $empty_report;
$valet_url = isset($report['valet_requests'][0]['url']) ? $report['valet_requests'][0]['url'] : '';
assert_equals(1, count($report['valet_requests']), 'Valet-only: exactly one HTTP request');
assert_true(strpos($valet_url, '/api/notifications') !== false, 'Valet-only: request targets /api/notifications');
assert_equals(0, count($report['matrix_calls']), 'Valet-only: Matrix is not contacted');
assert_true(
    strpos((string) $report['redirect'], 'atareao_contact=success') !== false,
    'Valet-only: redirect carries atareao_contact=success'
);

$report = $child_report('valet_and_matrix_success');
assert_equals(1, count($report['valet_requests']), 'Valet+Matrix: one request to Valet');
assert_equals(1, count($report['matrix_calls']), 'Valet+Matrix: one message to Matrix');
assert_true(
    strpos((string) $report['redirect'], 'atareao_contact=success') !== false,
    'Valet+Matrix: redirect carries atareao_contact=success'
);

$report = $child_report('invalid_nonce');
assert_equals(0, count($report['valet_requests']), 'Invalid nonce: no Valet request');
assert_equals(0, count($report['matrix_calls']), 'Invalid nonce: no Matrix message');
assert_true(
    strpos((string) $report['redirect'], 'atareao_contact=error') !== false,
    'Invalid nonce: redirect carries atareao_contact=error'
);

$report = $child_report('honeypot_filled');
assert_equals(0, count($report['valet_requests']), 'Filled honeypot: no Valet request');
assert_equals(0, count($report['matrix_calls']), 'Filled honeypot: no Matrix message');
assert_true(
    strpos((string) $report['redirect'], 'atareao_contact=error') !== false,
    'Filled honeypot: redirect carries atareao_contact=error'
);

$report = $child_report('bad_captcha');
assert_equals(0, count($report['valet_requests']), 'Wrong captcha: no Valet request');
assert_equals(0, count($report['matrix_calls']), 'Wrong captcha: no Matrix message');
assert_true(
    strpos((string) $report['redirect'], 'atareao_contact=error') !== false,
    'Wrong captcha: redirect carries atareao_contact=error'
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n============================================\n";
echo "Total passed: $passes, Total failed: " . count($failures) . "\n";
if (count($failures) > 0) {
    echo "FAILED TESTS:\n";
    foreach ($failures as $failure) {
        echo "  - $failure\n";
    }
    exit(1);
}

echo "All tests passed successfully!\n";
exit(0);
