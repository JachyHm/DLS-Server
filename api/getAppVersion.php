<?php
require "../dls_db.php";

function flushResponse($code, $message, $body, $mysqli) 
{
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

    header("Cache-Control: must-revalidate");
    header('Content-Type: application/json; Charset: UTF-8');
    header('Content-Length: ' . strlen($response_json));
    die($response_json);
}

$sql = $mysqli->prepare('SELECT `version_name`, `deployed`, `comment`, `file_path` FROM `app_versions` ORDER BY `id` DESC LIMIT 1;');
$sql->execute();
$queryResult = $sql->get_result();

if (!empty($queryResult)) {
    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        flushResponse(1, "Success!", $row, $mysqli);
    } else {
        flushResponse(-1, "No versions found!", new stdClass(), $mysqli);
    }
}

flushResponse(-1, "Something went wrong!", new stdClass(), $mysqli);

?>
