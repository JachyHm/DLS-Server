<?php
require "../dls_db.php";
require "utils.php";

$sql = $mysqli->prepare('SELECT `version_name`, `deployed`, `comment`, `file_path` FROM `app_versions` ORDER BY `id` DESC LIMIT 1;');
$sql->execute();
$queryResult = $sql->get_result();

if (!empty($queryResult)) {
    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        flushResponse(200, "Success!", $mysqli, $row);
    } else {
        flushResponse(404, "No versions found!", $mysqli);
    }
}

flushResponse(500, "Something went wrong!", $mysqli);

?>
