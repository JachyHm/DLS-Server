<?php
function flushResponse($code, $message, $mysqli, $body = null) 
{
    http_response_code($code);

    if (!isset($body)) {
        $body = new stdClass();
    }

    $response = new stdClass();
    $response->code = $code;
    $response->message = $message;
    $response->content = $body;

    $response_json = json_encode($response);

    $mysqli->close();

    $supportsGzip = false;
    if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
        $supportsGzip = strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false;
    }
    if ($supportsGzip) {
        $response_json = gzencode($response_json, 9);
        header('Content-Encoding: gzip');
    }

    header('Content-Type: application/json; Charset: UTF-8');
    header('Content-Length: ' . strlen($response_json));
    
    die($response_json);
}
