<?php
require "../dls_db.php";
session_start();

$files_folder = '../files/'; // upload directory

function flushResponse($code, $message, $body, $mysqli) 
{
    header('Content-type: application/json');

    $response->code = $code;
    $response->message = $message;
    $response->content = $body;

    $response_json = json_encode($response);

    $mysqli->close();
    
    die($response_json);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET["token"]) && isset($_GET["package_id"])) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

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

                        /*if (is_file("$fname.gz")) {
                            $gzdata = implode("", "$fname.gz");
                        } else {
                            $data = implode("", file($fname));
                            $gzdata = gzencode($data, 9);
                            $fp = fopen("$fname.gz", "w");
                            fwrite($fp, $gzdata);
                            fclose($fp);
                        }*/
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
                        //header('Content-Type: application/gzip');
                        //header('Content-Encoding: gzip');
                        //header('Content-Length: '.strlen($gzdata));
                        //die($gzdata);
                    }
                }

                db_log(15, false, $userid, $ip, $token, "No such package $package_id!", $mysqli);
                flushResponse(-1, "No such package!", new stdClass(), $mysqli);
            }
        }
        db_log(15, false, -1, $ip, $token, "Invalid token!", $mysqli);
        flushResponse(-1, "Invalid token!", new stdClass(), $mysqli);
    } else {
        flushResponse(-1, "Bad request!", new stdClass(), $mysqli);
    }
} else {
    flushResponse(-1, "Bad request!", new stdClass(), $mysqli);
}
?>