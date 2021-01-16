<?php
header('Content-type: application/json');

$max_size = 2 * 1024 * 1024 * 1024; // max file size (2 GB)
$files_folder = '../files/'; // upload directory

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
        if (isset($_SESSION["privileges"]) && $_SESSION["privileges"] > 0) {
            $userid = $_SESSION["userid"];
            if (strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION)) == "zip") {
                if (isset($_POST["packageName"]) && isset($_POST["category"]) && isset($_POST["country"]) && isset($_POST["era"]) && isset($_POST["targetPath"]) && isset($_POST["description"])) {
                    $target_path = str_replace("\\", "/", trim($_POST["targetPath"]));
                    if (!empty($target_path)) {
                        if (substr($target_path, -1) !== "/") {
                            $target_path.="/";
                        }
                        if (substr($target_path, 1) == "/") {
                            $target_path = substr($target_path, 2);
                        }
                    }
                    if (!empty($target_path) && preg_match('/^(?:[^?%*:|"><.]+(?:|\/)?)+$/', $target_path)) {
                        if (@is_uploaded_file($_FILES['file']['tmp_name'])) {
                            if ($_FILES['file']['size'] < $max_size) {                                
                                $desc = trim($_POST["description"]);
                                $display_name = trim($_POST["packageName"]);
                                $category = $_POST["category"];
                                $era = $_POST["era"];
                                $country = $_POST["country"];
                                $original_name = pathinfo($_FILES['file']['name'], PATHINFO_BASENAME);
                                $name = bin2hex(random_bytes(16)).".".pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
                                $path = $files_folder . $name;

                                $old_filename = "";
                                $old_version = 0;
                                $package_id = 0;
                                $actualisation = false;

                                if (isset($_POST["actualisation"])) {
                                    $package_id = $_POST["actualisation"];
                                    $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `id` = ?;');
                                    $sql->bind_param('i', $package_id);
                                    $sql->execute();
                                    $queryResult = $sql->get_result();
            
                                    if (!empty($queryResult)) {
                                        if ($queryResult->num_rows > 0) {
                                            $row = $queryResult->fetch_assoc();
                                            $package_id = $row["id"];
                                            $old_version = $row["version"];
                                            $old_filename = $row["file_name"];
                                            $actualisation = true;
                                        }
                                    }

                                    if (!$actualisation) {
                                        $response = new stdClass();
                                        $response->code = -1;
                                        $response->message = "No package to update!";
                                        
                                        $response_json = json_encode($response);
                                        
                                        db_log(17, false, $userid, $ip, $_SESSION["token"], "No package to update!", $mysqli);
                                        die($response_json);
                                    }
                                }

                                $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `original_file_name` = ?;');
                                $sql->bind_param('s', $original_name);
                                $sql->execute();
                                $queryResult = $sql->get_result();

                                if (!empty($queryResult)) {
                                    if ($queryResult->num_rows > 0) {
                                        $row = $queryResult->fetch_assoc();
                                        if ($actualisation) {
                                            if ($package_id != $row["id"]) {
                                                $response = new stdClass();
                                                $response->code = -1;
                                                $response->message = "Fatal error! Another package with this name already exists. Please rename your package.";
                                                
                                                $response_json = json_encode($response);
                                                
                                                db_log(17, false, $userid, $ip, $_SESSION["token"], "Another package with this name already exists.!", $mysqli);
                                                die($response_json);
                                            }
                                        } else if ($row["owner"] == $userid) {
                                            $package_id = $row["id"];
                                            $old_version = $row["version"];
                                            $old_filename = $row["file_name"];
                                            $actualisation = true;
                                        } else {
                                            $response = new stdClass();
                                            $response->code = -1;
                                            $response->message = "Package with such name already exists from another author! Please rename your package before proceeding. If you are trying to update it, please login to corresponding account.";
                                            
                                            $response_json = json_encode($response);
                                            
                                            db_log(17, false, $userid, $ip, $_SESSION["token"], "Another package with this name already exists.!", $mysqli);
                                            die($response_json);
                                        }
                                    }
                                }

                                if ($actualisation) {
                                    $sql = $mysqli->prepare('DELETE FROM `file_list` WHERE `package_id` = ?;');
                                    $sql2 = $mysqli->prepare('DELETE FROM `dependency_list` WHERE `package_id` = ?;');
                                    $sql->bind_param('i', $package_id);
                                    $sql2->bind_param('i', $package_id);
                                    $sql->execute();
                                    $sql2->execute();

                                    unlink($files_folder.$old_filename);
                                    unlink($files_folder."images/".$package_id.".png");
                                }

                                $files = array();
                                $za = new ZipArchive(); 
                                $za->open($_FILES['file']['tmp_name']);
                                for ($i = 0; $i < $za->numFiles; $i++) {
                                    $stat = $za->statIndex($i);
                                    $_name = $stat['name'];
                                    $ext = pathinfo($_name, PATHINFO_EXTENSION);
                                    if ($ext && in_array(strtolower($ext), array("bin", "xml"))) {
                                        $dir = pathinfo($_name, PATHINFO_DIRNAME)."/";
                                        $basename = pathinfo($_name, PATHINFO_FILENAME);
                                        if ($dir == "./") {
                                            $fname = $target_path.$basename;
                                        } else {
                                            $fname = $target_path.$dir.$basename;
                                        }
                                        $fname = str_replace('\\', '/', $fname);
                                        if (!in_array($fname, $files)) {
                                            array_push($files, $fname);
                                        }
                                    }
                                }

                                $imploded_files = implode($files);
                                $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `fname` IN (?);');
                                $sql->bind_param('s', $imploded_files);
                                $sql->execute();
                                $queryResult = $sql->get_result();

                                $multiple_files = array();

                                if (!empty($queryResult)) {
                                    if ($queryResult->num_rows > 0) {
                                        array_push($multiple_files, $queryResult->fetch_assoc());
                                    }
                                }
                                
                                if (count($multiple_files) == 0) {
                                    if (move_uploaded_file($_FILES['file']['tmp_name'], $path)) {
                                        $status = 'Soubor úspěšně nahrán!';

                                        $new_version = $old_version+1;
                                        if ($actualisation) {
                                            $sql = $mysqli->prepare('INSERT INTO `package_list`(`id`, `file_name`, `original_file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `description`, `target_path`) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `id` = ?, `file_name` = ?, `original_file_name` = ?, `display_name` = ?, `category` = ?, `era` = ?, `country` = ?, `version` = ?, `owner` = ?, `description` = ?, `target_path` = ?;');
                                            $sql->bind_param('isssiiiiississsiiiiiss', $package_id, $name, $original_name, $display_name, $category, $era, $country, $new_version, $userid, $desc, $target_path, $package_id, $name, $original_name, $display_name, $category, $era, $country, $new_version, $userid, $desc, $target_path);
                                        } else {
                                            $sql = $mysqli->prepare('INSERT INTO `package_list`(`file_name`, `original_file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `description`, `target_path`) VALUES(?, ?, ?, ?, ?, ?, 1, ?, ?, ?);');
                                            $sql->bind_param('sssiiiiss', $name, $original_name, $display_name, $category, $era, $country, $userid, $desc, $target_path);
                                        }
                    
                                        if ($sql->execute()) {
                                            $package_id = $mysqli->insert_id;
            
                                            foreach ($files as $fname) {
                                                $sql = $mysqli->prepare('INSERT INTO `file_list`(`package_id`, `fname`) VALUES(?, ?);');
                                                $sql->bind_param('is', $package_id, $fname);
                                                $sql->execute();
                                            }
                                            
                                            if (isset($_POST["depends"])) {
                                                foreach (json_decode($_POST["depends"]) as $dep_id) {
                                                    $sql = $mysqli->prepare('INSERT INTO `dependency_list`(`package_id`, `dependency_package_id`) VALUES(?, ?);');
                                                    $sql->bind_param('ii', $package_id, $dep_id);
                                                    $sql->execute();
                                                }
                                            }

                                            if (@is_uploaded_file($_FILES['image']['tmp_name']) && mime_content_type($_FILES['image']['tmp_name']) == "image/png") {
                                                $filename = $files_folder."images/".$package_id.".png";
                                                move_uploaded_file($_FILES['image']['tmp_name'], $filename);
                                                list($width_orig, $height_orig) = getimagesize($filename);

                                                $ratio_orig = $width_orig/$height_orig;
                                                $height = 770/$ratio_orig;

                                                $image_p = imagecreatetruecolor(770, $height);

                                                imagealphablending($image_p, false);
                                                imagesavealpha($image_p, true);
                                                $image = imagecreatefrompng($filename);
                                                imagecopyresampled($image_p, $image, 0, 0, 0, 0, 770, $height, $width_orig, $height_orig);
                                                imagepng($image_p, $filename);
                                            }
                                            
                                            $response = new stdClass();
                                            $response->code = 1;
                                            $response->message = "File uploaded successfully!";
                                            $response->content = new stdClass();
                                            $response->content->package_id = $package_id;
                                            
                                            $response_json = json_encode($response);
                                            
                                            db_log(17, true, $userid, $ip, $_SESSION["token"], "Package $package_id uploaded successfully!", $mysqli);
                                            $mysqli->close();
                                            die($response_json);
                                        }

                                        $e = $mysqli->error;

                                        $response = new stdClass();
                                        $response->code = -1;
                                        $response->message = "Writing file to database failed with following: ".$e."!";
                                        
                                        $response_json = json_encode($response);
                                        
                                        db_log(17, false, $userid, $ip, $_SESSION["token"], "Database error: $e!", $mysqli);
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
                                    $response->code = 99;
                                    $response->message = "Your file is including folowing files already included in another package! Please remove this conflict before uploading again.";
                                    $response->multipleFiles = $multiple_files;
                                    
                                    $response_json = json_encode($response);

                                    db_log(17, false, $userid, $ip, $_SESSION["token"], "Files from another package included!", $mysqli);
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
                        $response->message = "Target path must be valid Windows folderpath from Assets folder!";
                        
                        $response_json = json_encode($response);
                        
                        db_log(17, false, $userid, $ip, $_SESSION["token"], "Target path is not valid!", $mysqli);
                        $mysqli->close();
                        die($response_json);
                    }
                } else {
                    $response = new stdClass();
                    $response->code = -1;
                    $response->message = "Not all parameters set!";
                    
                    $response_json = json_encode($response);
                    
                    db_log(17, false, $userid, $ip, $_SESSION["token"], "Not all parameters set!", $mysqli);
                    $mysqli->close();
                    die($response_json);
                }
            } else {
                $response = new stdClass();
                $response->code = -1;
                $response->message = "Uploaded file must be *.zip!";
                
                $response_json = json_encode($response);
                
                db_log(17, false, $userid, $ip, $_SESSION["token"], "File is not zip!", $mysqli);
                $mysqli->close();
                die($response_json);
            }
        } else {
            $response = new stdClass();
            $response->code = -1;
            $response->message = "Not enough privileges to upload!";
            
            $response_json = json_encode($response);
            
            db_log(17, false, $userid, $ip, $_SESSION["token"], "Not enough privileges to upload!", $mysqli);
            $mysqli->close();
            die($response_json);
        }
    } else {
        $response = new stdClass();
        $response->code = -1;
        $response->message = "Only logged users can upload!";
        
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
