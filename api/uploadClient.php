<?php
header('Content-type: application/json');

$max_size = 2 * 1024 * 1024 * 1024; // max file size (2 GB)
$files_folder = '../'; // upload directory

session_start();
require "../dls_db.php";
require "utils.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"] && isset($_SESSION["userid"])) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

        if (isset($_SESSION["privileges"]) && $_SESSION["privileges"] >= 9) {
            $userid = $_SESSION["userid"];
            if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) == "exe") {
                $tmp_name = $_FILES['file']['tmp_name'];
                if (@is_uploaded_file($tmp_name)) {
                    if ($_FILES['file']['size'] < $max_size) {
                        $desc = trim($_POST["description"]);
                        $version_code = trim($_POST["version"]);
                        $path = "../RailworksDownloader.exe";
                        
                        $sql = $mysqli->prepare('SELECT * FROM `app_versions` WHERE `version_name` = ? LIMIT 1');
                        $sql->bind_param('s', $version_code);
                        $sql->execute();
                        $queryResult = $sql->get_result();

                        if (!empty($queryResult)) {
                            if ($queryResult->num_rows > 0) {
                                db_log(17, false, $userid, $ip, $_SESSION["token"], "Version code $version_code already used!", $mysqli);
                                flushResponse(409, "Version code $version_code is already used! Please use another!", $mysqli);
                            }
                        }
                        
                        if (move_uploaded_file($tmp_name, $path)) {
                            $sql = $mysqli->prepare('INSERT INTO `app_versions`(`version_name`, `comment`, `file_path`) VALUES(?, ?, "https://dls.rw.jachyhm.cz/RailworksDownloader.exe")');
                            $sql->bind_param('ss', $version_code, $desc);
                            $sql->execute();

                            db_log(17, true, $userid, $ip, $_SESSION["token"], "Client app $version_code uploaded successfully!", $mysqli);
                            flushResponse(201, "File uploaded successfully!", $mysqli);
                        } else {
                            db_log(17, false, $userid, $ip, $_SESSION["token"], "Unable to move file to target!", $mysqli);
                            flushResponse(500, "Upload failed! Unable to move uploaded file to target directory!", $mysqli);
                        }
                    } else {
                        db_log(17, false, $userid, $ip, $_SESSION["token"], "Max file size exceeded!", $mysqli);
                        flushResponse(413, "Upload failed! Exceeded max file size!", $mysqli);
                    }
                } else {
                    db_log(17, false, $userid, $ip, $_SESSION["token"], "Unable to upload file!", $mysqli);
                    flushResponse(500, "Upload failed! Unable to upload file!", $mysqli);
                }
            } else {
                db_log(17, false, $userid, $ip, $_SESSION["token"], "File is not exe!", $mysqli);
                flushResponse(415, "Uploaded file must be *.exe!", $mysqli);
            }
        } else {
            db_log(17, false, $userid, $ip, $_SESSION["token"], "Not enough privileges to upload client app!", $mysqli);
            flushResponse(403, "Not enough privileges to upload client app!", $mysqli);
        }
    } else {
        flushResponse(403, "Only system administrators can upload client app!", $mysqli);
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
