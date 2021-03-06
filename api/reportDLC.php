<?php
header('Content-type: application/json');
require "../dls_db.php";
require "utils.php";
$files_folder = '../files/'; // upload directory

function getResponse($url, &$response = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:9050");
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return $response_code;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["token"]) && isset($_POST["dlcList"])) {
        $token = $_POST["token"];
        $dlcList = $_POST["dlcList"];

        $sql = $mysqli->prepare('SELECT * FROM `tokens` WHERE `token` = ?;');
        $sql->bind_param('s', $token);
        $sql->execute();

        $queryResult = $sql->get_result();
        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {                
                $errors = array();
                try {
                    $result = array();
                    foreach ($dlcList as $dlc) {
                        $id = $dlc["DLCAppId"];
                        if (count($dlc["IncludedFiles"])) {
                            $sql = $mysqli->prepare('SELECT `steamappid`, `id` FROM `package_list` WHERE `steamappid` = ?;');
                            $sql->bind_param('i', $id);
                            $sql->execute();
                            $queryResult = $sql->get_result();

                            if (empty($queryResult) || $queryResult->num_rows == 0) {
                                $store_page = "<a href=\"https://store.steampowered.com/app/$id\">https://store.steampowered.com/app/$id</a>";

                                $response = "";
                                $url = "http://store.steampowered.com/api/appdetails/?appids=$id";
                                $response_code = getResponse($url, $response);

                                //$response = file_get_contents("http://store.steampowered.com/api/appdetails/?appids=$id");

                                if ($response_code == 200) {
                                    $success = false;
                                    if ($response) {
                                        $decoded_resp = json_decode($response)->$id;

                                        $success = $decoded_resp->success;
                                    }
                                    if (!$success) {
                                        array_push($errors, "$id isn't DLC!");
                                        $display_name = "Unknown DLC placeholder";
                                        $description = "What you see is placeholder for unlisted DLC. Please do not edit, nor delete this package. These are usually packages which are already part of game, or another DLC, but are also important for DLS functioning.";
                                        $release_date = "1970-01-01";

                                        $files = $dlc["IncludedFiles"];

                                        $sql = $mysqli->prepare('INSERT INTO `package_list` (`file_name`, `original_file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `datetime`, `description`, `target_path`, `paid`, `steamappid`, `steam_dev`) VALUES (?, ?, ?, 9, -1, -1, -1, -2, ?, ?, "", 1, ?, "Unknown") ON DUPLICATE KEY UPDATE `id` = `id`;');
                                        $sql->bind_param('sssssi', $store_page, $store_page, $display_name, $release_date, $description, $id);
                                        $sql->execute();

                                        $package_id = $mysqli->insert_id;
                                        if ($package_id>0) {
                                            $sql = $mysqli->prepare('INSERT INTO `file_list` (`package_id`, `fname`) VALUES (?, ?);');
                                            foreach ($files as $file_name) {
                                                $_file_name = str_replace('\\', '/', $file_name);
                                                $sql->bind_param('is', $package_id, $_file_name);
                                                $sql->execute();
                                            }
                                        }
                                    } else {
                                        $data = $decoded_resp->data;
                                        if (isset($data->fullgame) && !$data->fullgame->appid == 24010) {
                                            array_push($errors, "$id isn't Train Simulator DLC!");
                                        }
                                        $display_name = $data->name ?? "Unknown DLC placeholder";
                                        $description = $data->short_description ?? "What you see is placeholder for unlisted DLC. Please do not edit, nor delete this package. These are usually packages which are already part of game, or another DLC, but are also important for DLS functioning.";
                                        $steam_dev = "Unknown";
                                        try {
                                            $steam_dev = $data->developers[0];
                                        } catch (Exception $e) {
                                        }
                                        $steam_dev_link = "<a href='https://store.steampowered.com/search/?developer=$steam_dev'>$steam_dev</a>";
                                        $date_obj = DateTime::createFromFormat("j M, Y", $data->release_date->date);
                                        if ($date_obj) {
                                            $release_date = $date_obj->format('Y-m-d');
                                        } else {
                                            $release_date = "1970-01-01";
                                        }
                                
                                        $files = $dlc["IncludedFiles"];
                                
                                        $sql = $mysqli->prepare('INSERT INTO `package_list` (`file_name`, `original_file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `datetime`, `description`, `target_path`, `paid`, `steamappid`, `steam_dev`) VALUES (?, ?, ?, 8, -1, -1, -1, -2, ?, ?, "", 1, ?, ?) ON DUPLICATE KEY UPDATE `id` = `id`;');
                                        $sql->bind_param('sssssis', $store_page, $store_page, $display_name, $release_date, $description, $id, $steam_dev_link);
                                        $sql->execute();
                                
                                        $package_id = $mysqli->insert_id;
                                        if ($package_id>0) {
                                            $sql = $mysqli->prepare('INSERT INTO `file_list` (`package_id`, `fname`) VALUES (?, ?);');
                                            foreach ($files as $file_name) {
                                                $_file_name = str_replace('\\', '/', $file_name);
                                                $sql->bind_param('is', $package_id, $_file_name);
                                                $sql->execute();
                                            }
                                        }
                                    }
                                } else {
                                    array_push($errors, "$id Steam request failed with code $response_code!");
                                }
                            } else {
                                array_push($errors, "$id already exists!");
                                /*$row = $queryResult->fetch_assoc();
                                $package_id = $row["id"];

                                $files = $dlc["IncludedFiles"];
                                $sql = $mysqli->prepare('INSERT INTO `file_list` (`package_id`, `fname`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `package_id`=`package_id`;');
                                foreach ($files as $file_name) {
                                    $_file_name = str_replace('\\', '/', $file_name);
                                    $sql->bind_param('is', $package_id, $_file_name);
                                    $sql->execute();
                                }*/
                            }
                        } else {
                            array_push($errors, "$id does not contain any files!");
                        }
                        
                        $sql = $mysqli->prepare('SELECT * FROM `file_list` LEFT JOIN `package_list` ON `file_list`.`package_id` = `package_list`.`id` WHERE `steamappid` = ?;');
                        $sql->bind_param("s", $id);
                        if ($sql->execute()) {
                            $queryResult = $sql->get_result();
                        
                            if (!empty($queryResult)) {
                                if ($queryResult->num_rows > 0) {
                                    $row = $queryResult->fetch_assoc();
                    
                                    $package = new stdClass();
                                    $package->id = $row["id"];
                                    $package->file_name = $row["original_file_name"];
                                    $package->display_name = $row["display_name"];
                                    $package->category = $row["category"];
                                    $package->era = $row["era"];
                                    $package->country = $row["country"];
                                    $package->version = $row["version"];
                                    $package->owner = $row["owner"];
                                    $package->created = $row["datetime"];
                                    $package->description = $row["description"];
                                    $package->target_path = $row["target_path"];
                                    $package->paid = $row["paid"];
                                    $package->steamappid = $row["steamappid"];
                                    $package->files = array();
                                    $package->dependencies = array();
                    
                                    $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `package_id` = ?;');
                                    $sql->bind_param('i', $package->id);
                                    $sql->execute();
                                    $queryResult = $sql->get_result();
                    
                                    if (!empty($queryResult)) {
                                        while ($row = $queryResult->fetch_assoc()) {
                                            array_push($package->files, $row["fname"]);
                                            //array_push($processed, $row["fname"]);
                                        }
                                    }
                    
                                    $sql = $mysqli->prepare('SELECT * FROM `dependency_list` WHERE `package_id` = ?;');
                                    $sql->bind_param('i', $package->id);
                                    $sql->execute();
                                    $queryResult = $sql->get_result();
                    
                                    if (!empty($queryResult)) {
                                        while ($row = $queryResult->fetch_assoc()) {
                                            array_push($package->dependencies, $row["dependency_package_id"]);
                                        }
                                    }

                                    array_push($result, $package);
                                }
                            }
                        }
                    }
                    flushResponse(200, "DLC files written successfully! Thank you for contributing!", $mysqli, $result);
                } catch (Exception $e) {
                    $err = $e->getMessage();
                    flushResponse(500, "Fatal error occured! $err", $mysqli);
                }
            }
        }
        flushResponse(498, "Invalid token!", $mysqli);
    } else {
        flushResponse(400, "Not all parameters set!", $mysqli);
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
