<?php
// proxy.php — Beeple API proxy
//
// Usage:
//   ?base=<tenant_base_url>&path=<relative_api_path>[&debug=1]
//   Header: X-Beeple-Token: <token>
//
// Supports GET, POST, PATCH, DELETE.
// Body is forwarded for POST, PATCH, PUT.

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Beeple-Token');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, PUT, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawBody = file_get_contents('php://input');
$debug   = isset($_GET['debug']) && $_GET['debug'] === '1';

function json_error($code, $msg) {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

// Build target URL
$base = rtrim($_GET['base'] ?? '', '/');
$path = ltrim($_GET['path'] ?? '', '/');
if (!$base || !$path)                        json_error(400, "Missing 'base' or 'path'");
if (!preg_match('#^https?://#i', $base))     json_error(400, "Invalid base URL");
$url = $base . '/' . $path;

// Read token — prefer X-Beeple-Token, fall back to Authorization header
$token = $_SERVER['HTTP_X_BEEPLE_TOKEN'] ?? '';
if (!$token) {
    $auth  = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = $auth ? trim(preg_replace('/^(bearer|token(\s+token=)?)\s+/i', '', $auth), '"') : '';
}
if (!$token) json_error(401, "Missing X-Beeple-Token");

$method  = $_SERVER['REQUEST_METHOD'];
$hasBody = in_array($method, ['POST', 'PUT', 'PATCH'], true);

function do_curl($url, $method, $token, $body, $hasBody, $debug) {
    if ($debug) error_log("proxy.php → $method $url" . ($body ? "\n$body" : ""));

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            "Token: $token",
            "Content-Type: application/json",
            "Accept: application/json",
        ],
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($hasBody) curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?: '{}');

    $response = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err      = curl_error($ch);
    curl_close($ch);
    return [$code, $response, $err];
}

[$code, $response, $err] = do_curl($url, $method, $token, $rawBody, $hasBody, $debug);
if ($err) json_error(500, "cURL error: $err");

// Retry with .json suffix on 404 (some Beeple endpoints require it)
if ($code === 404 && !preg_match('/\.[a-z0-9]+$/i', parse_url($url, PHP_URL_PATH))) {
    [$code, $response, $err] = do_curl($url . '.json', $method, $token, $rawBody, $hasBody, $debug);
    if ($err) json_error(500, "cURL error: $err");
}

if ($debug) error_log("proxy.php ← $code");
http_response_code($code);
echo $response !== false ? $response : '';
