<?php
require "../dls_db.php";
session_start();

$files_folder = '../files/'; // upload directory

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["token"]) && isset($_POST["package_id"])) {
        $package_id = $_POST["package_id"];
        if (true) {//TODO: check token!!
            $sql = $mysqli->prepare('SELECT `file_name` FROM `package_list` WHERE `id` = ?;');
            $sql->bind_param('i', $package_id);
            $sql->execute();
            $queryResult = $sql->get_result();
    
            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $fname = $files_folder.$queryResult->fetch_assoc()["file_name"];

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
                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
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

            header('Content-type: application/json');
            $response->code = -1;
            $response->message = "No such package!";
            
            $response_json = json_encode($response);
            
            die($response_json);
        } else {
            header('Content-type: application/json');
            $response->code = -1;
            $response->message = "Invalid token!";
            
            $response_json = json_encode($response);
            
            die($response_json);
        }
    } else {
        header('Content-type: application/json');
        $response->code = -1;
        $response->message = "Bad request!";
        
        $response_json = json_encode($response);
        
        die($response_json);
    }
} else {
    header('Content-type: application/json');
    $response->code = -1;
    $response->message = "Bad request!";

    $response_json = json_encode($response);
    
    die($response_json);
}
?>