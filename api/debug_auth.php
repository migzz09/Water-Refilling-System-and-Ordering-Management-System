<?php
// ...new debug helper...
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../utils/auth.php';

header('Content-Type: application/json');

$out = [
    'php_sapi' => php_sapi_name(),
    'session_status' => session_status(),
    'session_id' => session_id(),
    'cookies' => $_COOKIE,
    'server_headers' => array_intersect_key($_SERVER, array_flip(['HTTP_COOKIE','HTTP_AUTHORIZATION','REQUEST_URI','REQUEST_METHOD','REMOTE_ADDR'])),
    'session_vars' => $_SESSION,
    'validateToken_exists' => function_exists('validateToken'),
    'validateToken_result' => null,
];

if (function_exists('validateToken')) {
    try {
        $vt = validateToken();
        // avoid dumping secrets - only show account_id if present
        $out['validateToken_result'] = is_array($vt) && isset($vt['account_id']) ? ['account_id' => (int)$vt['account_id']] : ($vt === false ? false : 'non-array result');
    } catch (Throwable $e) {
        $out['validateToken_error'] = $e->getMessage();
    }
}

echo json_encode($out, JSON_PRETTY_PRINT);
?>