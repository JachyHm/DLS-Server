<?php
header('Content-type: application/json');

$max_size = 2 * 1024 * 1024 * 1024; // max file size (2 GB)
$files_folder = '../'; // upload directory

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"] && isset($_SESSION["userid"])) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

        include "../dls_db.php";
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
                                $response = new stdClass();
                                $response->code = -1;
                                $response->message = "Version code $version_code is already used! Please use another!";
                                $response->content = new stdClass();
                                $response->content->package_id = $package_id;
                                
                                $response_json = json_encode($response);
                                
                                db_log(17, false, $userid, $ip, $_SESSION["token"], "Version code $version_code already used!", $mysqli);
                                $mysqli->close();
                                die($response_json);
                            }
                        }
                        
                        if (move_uploaded_file($tmp_name, $path)) {
                            $sql = $mysqli->prepare('INSERT INTO `app_versions`(`version_name`, `comment`, `file_path`) VALUES(?, ?, "https://dls.rw.jachyhm.cz/RailworksDownloader.exe")');
                            $sql->bind_param('ss', $version_code, $desc);
                            $sql->execute();
                            
                            $response = new stdClass();
                            $response->code = 1;
                            $response->message = "File uploaded successfully!";
                            $response->content = new stdClass();
                            $response->content->package_id = $package_id;
                            
                            $response_json = json_encode($response);
                            
                            db_log(17, true, $userid, $ip, $_SESSION["token"], "Client app $version_code uploaded successfully!", $mysqli);
                            $mysqli->close();
                            die($response_json);
                        } else {
                            $response->code = -1;
                            $response->message = "Upload failed! Unable to move uploaded file to target directory!";
                            
                            $response_json = json_encode($response);
                            
                            db_log(17, false, $userid, $ip, $_SESSION["token"], "Unable to move file to target!", $mysqli);
                            $mysqli->close();
                            die($response_json);
                        }
                    } else {
                        $response = new stdClass();
                        $response->code = -1;
                        $response->message = "Upload failed! Exceeded max file size!";
                        
                        $response_json = json_encode($response);
                        
                        db_log(17, false, $userid, $ip, $_SESSION["token"], "Max file size exceeded!", $mysqli);
                        $mysqli->close();
                        die($response_json);
                    }
                } else {
                    $response = new stdClass();
                    $response->code = -1;
                    $response->message = "Upload failed! Unable to upload file!";
                    
                    $response_json = json_encode($response);
                    
                    db_log(17, false, $userid, $ip, $_SESSION["token"], "Unable to upload file!", $mysqli);
                    $mysqli->close();
                    die($response_json);
                }
            } else {
                $response = new stdClass();
                $response->code = -1;
                $response->message = "Uploaded file must be *.exe!";
                
                $response_json = json_encode($response);
                
                db_log(17, false, $userid, $ip, $_SESSION["token"], "File is not exe!", $mysqli);
                $mysqli->close();
                die($response_json);
            }
        } else {
            $response = new stdClass();
            $response->code = -1;
            $response->message = "Not enough privileges to upload client app!";
            
            $response_json = json_encode($response);
            
            db_log(17, false, $userid, $ip, $_SESSION["token"], "Not enough privileges to upload client app!", $mysqli);
            $mysqli->close();
            die($response_json);
        }
    } else {
        $response = new stdClass();
        $response->code = -1;
        $response->message = "Only system administrators can upload client app!";
        
        $response_json = json_encode($response);
        
        die($response_json);
    }
} else {
    $resposne = new stdClass();
    $response->code = -1;
    $response->message = "Bad request!";
    
    $response_json = json_encode($response);
    
    die($response_json);
}
?>
