<?php
require "../dls_db.php";
require "utils.php";
session_start();

$files_folder = '../files/'; // upload directory

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET["token"]) && isset($_GET["package_id"])) {
        $token = $_GET["token"];

        $sql = $mysqli->prepare('SELECT * FROM `tokens` WHERE `token` = ?;');
        $sql->bind_param('s', $token);
        $sql->execute();

        $queryResult = $sql->get_result();
        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                $userid = $queryResult->fetch_assoc()["user_id"];
                $package_id = $_GET["package_id"];
                $sql = $mysqli->prepare('SELECT `file_name` FROM `package_list` WHERE `id` = ?;');
                $sql->bind_param('i', $package_id);
                $sql->execute();
                $queryResult = $sql->get_result();
        
                if (!empty($queryResult)) {
                    if ($queryResult->num_rows > 0) {
                        $fname = $files_folder.$queryResult->fetch_assoc()["file_name"];
                        db_log(15, true, $userid, $ip, $token, "Downloaded $fname!", $mysqli);

                        header('Content-Description: File Transfer');
                        header('Content-Type: application/zip');
                        header('Content-Encoding: zip');
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, GET-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: '.filesize($fname));
                        readfile($fname);
                        die();
                    }
                }

                db_log(15, false, $userid, $ip, $token, "No such package $package_id!", $mysqli);
                flushResponse(404, "No such package!", $mysqli);
            }
        }
        db_log(15, false, -1, $ip, $token, "Invalid token!", $mysqli);
        flushResponse(498, "Invalid token!", $mysqli);
    } else {
        flushResponse(400, "Not enough parameters set!", $mysqli);
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>