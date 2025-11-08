<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, X-Beeple-Token");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

/*
|--------------------------------------------------------------------------
| proxy.php
|--------------------------------------------------------------------------
| This file acts as a secure backend proxy between your Beeple admin tool
| and the Beeple / OpenAI APIs.
|
| ✅ Purpose:
|   - Avoid browser CORS restrictions.
|   - Keep your API tokens secret (never exposed in the browser).
|
| 💡 How to use:
|   - Place this file next to your HTML (e.g., team-wizard.html).
|   - Fill in your OpenAI key below.
|   - Your frontend can now call:
|       proxy.php?base=<tenant>&path=api/v1/admin/projects      (Beeple)
|       proxy.php?ai=1                                          (OpenAI)
|
| ⚠️ SECURITY NOTE:
|   Keep this file private; do not expose it publicly.
|--------------------------------------------------------------------------
*/

// 🔑 Insert your OpenAI token here (e.g. "sk-...")
$openaiKey = "";  // <-- your OpenAI API token

$base   = trim($_GET['base'] ?? '');
$path   = trim($_GET['path'] ?? '');
$token  = trim($_SERVER['HTTP_X_BEEPLE_TOKEN'] ?? '');
$method = $_SERVER['REQUEST_METHOD'];
$raw    = file_get_contents("php://input");

/* Helper to run cURL safely */
function do_curl($url, $method='GET', $headers=array(), $body=null) {
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  if ($body !== null && in_array(strtoupper($method), array('POST','PUT','PATCH'))) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  }
  $response = curl_exec($ch);
  $info = curl_getinfo($ch);
  $error = curl_error($ch);
  curl_close($ch);
  return array($response, $info, $error);
}

/* =========================================================================
   🧠 1) OpenAI analysis endpoint
   ========================================================================= */
if (isset($_GET['ai'])) {
  if (empty($openaiKey)) {
    http_response_code(401);
    echo json_encode(array(
      "error" => "missing_openai_key",
      "message" => "Please set your OpenAI API key in proxy.php"
    ));
    exit;
  }

  $json = json_decode($raw ?: "{}", true);
  $userText = trim($json['text'] ?? '');
  if (!$userText) {
    http_response_code(400);
    echo json_encode(array(
      "error" => "missing_text",
      "message" => "POST body must include {text:'...'}"
    ));
    exit;
  }

  // Prompt for contextual team generation with clarification ability
  $prompt = <<<PROMPT
You are a scheduling assistant for workforce planning.
The user provides free text that describes how many teams, when, where, and any constraints.
If something is ambiguous, first ask clarifying questions.
Once clear, return a strict JSON response with this schema:

{
  "teams": [
    {
      "name": "Check-in AM shift",
      "auto_name": true,
      "volunteers": 3,
      "project": "Main Event",
      "subproject": "Day 1",
      "function": "Check-in",
      "address": "Antwerp Expo",
      "compensation": "Weekend rate",
      "draft": "yes",
      "start": "2025-12-10T09:00",
      "end": "2025-12-10T13:00",
      "maxKm": 20,
      "extra": "Wear uniform"
    }
  ]
}
PROMPT;

  $messages = array(
    array("role" => "system", "content" => $prompt),
    array("role" => "user", "content" => $userText)
  );

  list($resp, $info, $err) = do_curl(
    "https://api.openai.com/v1/chat/completions",
    "POST",
    array(
      "Authorization: Bearer $openaiKey",
      "Content-Type: application/json"
    ),
    json_encode(array(
      "model" => "gpt-4o-mini",
      "temperature" => 0.3,
      "messages" => $messages
    ))
  );

  if ($err) {
    http_response_code(502);
    echo json_encode(array("error"=>"curl_error","message"=>$err));
    exit;
  }

  http_response_code($info["http_code"] ?? 200);
  echo $resp;
  exit;
}

/* =========================================================================
   🐝 2) Beeple API passthrough
   ========================================================================= */
if (!$base || !$path) {
  http_response_code(400);
  echo json_encode(array(
    "error" => "missing_parameters",
    "message" => "Provide ?base=<tenant> and ?path=<endpoint>."
  ));
  exit;
}

if (!$token) {
  http_response_code(401);
  echo json_encode(array(
    "error" => "missing_token",
    "message" => "Provide X-Beeple-Token header."
  ));
  exit;
}

// Normalize base to only scheme+host
if (preg_match('#^(https?://[^/]+)#i', $base, $m)) {
  $base = $m[1];
}

$target = rtrim($base, '/') . '/' . ltrim($path, '/');

list($resp, $info, $err) = do_curl(
  $target,
  $method,
  array(
    "Token: $token",
    "Content-Type: application/json"
  ),
  $raw
);

if ($err) {
  http_response_code(502);
  echo json_encode(array("error" => "curl_error", "message" => $err));
  exit;
}

http_response_code($info["http_code"] ?? 500);
$ct = $info["content_type"] ?? '';
if (stripos($ct, "json") !== false) {
  echo $resp;
} else {
  echo json_encode(array(
    "notice" => "non_json_response",
    "status" => $info["http_code"] ?? 0,
    "body" => $resp
  ));
}