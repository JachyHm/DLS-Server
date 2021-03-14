<?php
session_start();

require "../dls_db.php";
require "utils.php";

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

function raiseError($message) 
{
    $_SESSION["errorMessage"] = $message;
    if (isset($_POST["package_id"])) {
        header("Location: ../?package=".$_POST["package_id"]);
    } else if (isset($_GET["package_id"])) {
        header("Location: ../?package=".$_GET["package_id"]);
    } else {
        header("Location: ../");
    }
    die();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"] && isset($_SESSION["userid"]) && isset($_SESSION["privileges"]) && $_SESSION["privileges"] > 0) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

        $userid = $_SESSION["userid"];
        if (isset($_POST["package_id"]) && isset($_POST["package_name"]) && isset($_POST["target_path"]) && isset($_POST["description"])) {
            $sql = $mysqli->prepare('SELECT `target_path` FROM `package_list` WHERE `id` = ?;');
            $sql->bind_param('i', $_POST["package_id"]);
            $sql->execute();
            $queryResult = $sql->get_result();
        
            $target_path = "";
            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $target_path = $queryResult->fetch_assoc()["target_path"];
                }
            }

            $query = "UPDATE `package_list` SET `display_name` = ?, `target_path` = ?, `description` = ?";
            $query_pattern = "sss";
            $query_array = array($_POST["package_name"], $_POST["target_path"], $_POST["description"]);

            if (isset($_POST["category"])) {
                $query .= ", `category` = ?";
                $query_pattern .= "i";
                array_push($query_array, $_POST["category"]);
            }

            if (isset($_POST["country"])) {
                $query .= ", `country` = ?";
                $query_pattern .= "i";
                array_push($query_array, $_POST["country"]);
            }

            if (isset($_POST["era"])) {
                $query .= ", `era` = ?";
                $query_pattern .= "i";
                array_push($query_array, $_POST["era"]);
            }

            if (isset($_POST["owner"])) {
                $query .= ", `owner` = ?";
                $query_pattern .= "i";
                array_push($query_array, $_POST["owner"]);
            }

            $package_id = $_POST["package_id"];

            $query .= " WHERE `id` = ?;";
            $query_pattern .= "i";
            array_push($query_array, $package_id);

            $sql = $mysqli->prepare($query);
            $sql->bind_param($query_pattern, ...$query_array);
            //array_unshift($query_array, $query_pattern);
            //call_user_func_array(array($sql,'bind_param'), $query_array);

            if ($sql->execute()) {
                if ($target_path == "") {
                    $sql = $mysqli->prepare('UPDATE `file_list` SET `fname` = CONCAT(?,`fname`) WHERE `package_id` = ?;');
                    $sql->bind_param('si', $_POST["target_path"], $package_id);
                    $sql->execute();
                } else {
                    $sql = $mysqli->prepare('UPDATE `file_list` SET `fname` = STUFF(`fname`, CHARINDEX(?, `fname`), LEN(?), ?) WHERE `package_id` = ?;');
                    $sql->bind_param('sssi', $target_path, $target_path, $_POST["target_path"], $package_id);
                    $sql->execute();
                }

                db_log(16, true, $userid, $ip, $_SESSION["token"], "Package updated $package_id!", $mysqli);
                $_SESSION["successMessage"] = "Package successfully updated!";
                header("Location: ../?package=".$package_id);
                die();
            }

            db_log(16, false, $userid, $ip, $_SESSION["token"], "Package not updated $package_id!", $mysqli);
            raiseError("Unable to update!");
        } else if (isset($_POST["package_id"]) && isset($_POST["depends"])) {
            $package_id = $_POST["package_id"];

            $sql = $mysqli->prepare('DELETE FROM `dependency_list` WHERE `package_id` = ?;');
            $sql->bind_param('i', $package_id);
            $sql->execute();

            foreach (json_decode($_POST["depends"]) as $dep_id) {
                $sql = $mysqli->prepare('INSERT INTO `dependency_list`(`package_id`, `dependency_package_id`) VALUES(?, ?);');
                $sql->bind_param('ii', $package_id, $dep_id);
                if (!$sql->execute()) {
                    db_log(16, false, $userid, $ip, $_SESSION["token"], "Updating package $package_id dependencies failed!", $mysqli);

                    raiseError("Unable to update!");
                }
            }

            db_log(16, true, $userid, $ip, $_SESSION["token"], "Updating package $package_id dependencies succeeded!", $mysqli);
            $_SESSION["successMessage"] = "Package dependencies successfully updated!";
            header("Location: ../?package=".$_POST["package_id"]);
            die();
        } else if (isset($_POST["package_id"]) && isset($_POST["refreshDLC"])) {
            $package_id = $_POST["package_id"];
            $steamappid = $_POST["refreshDLC"];
            
            $response = null;
            $url = "http://store.steampowered.com/api/appdetails/?appids=$steamappid";
            $response_code = getResponse($url, $response);

            if ($response_code == 200) {
                $success = false;
                if ($response) {
                    $decoded_resp = json_decode($response)->$steamappid;

                    $success = $decoded_resp->success;
                }
                if ($success) {
                    $data = $decoded_resp->data;
                    if (isset($data->fullgame) && !$data->fullgame->appid == 24010) {
                        raiseError("$steamappid isn't Train Simulator DLC!");
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

                    $sql = $mysqli->prepare('UPDATE `package_list` SET `display_name` = ?, `category` = 8, `era` = -1, `country` = -1, `version` = -1, `owner` = -2, `datetime` = ?, `description` = ?, `target_path` = "", `paid` = 1, `steamappid` = ?, `steam_dev` = ? WHERE `id` = ?;');
                    $sql->bind_param('sssisi', $display_name, $release_date, $description, $steamappid, $steam_dev_link, $package_id);
                    $sql->execute();
                    
                    $_SESSION["successMessage"] = "Package successfully updated!";
                    header("Location: ../?package=".$package_id);
                    die();
                }
                raiseError("$steamappid isn't Train Simulator DLC!");
            }
        } else {
            raiseError("Missing required parameters!");
        }
    } else {
        raiseError("Only logged users can change packages!");
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"] && isset($_SESSION["userid"]) && isset($_SESSION["privileges"]) && $_SESSION["privileges"] > 0) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

        $userid = $_SESSION["userid"];
        if (isset($_GET["package_id"]) && isset($_GET["refreshDLC"])) {
            $package_id = $_GET["package_id"];
            $steamappid = $_GET["refreshDLC"];
            
            $response = null;
            $url = "http://store.steampowered.com/api/appdetails/?appids=$steamappid";
            $response_code = getResponse($url, $response);

            if ($response_code == 200) {
                $success = false;
                if ($response) {
                    $decoded_resp = json_decode($response)->$steamappid;

                    $success = $decoded_resp->success;
                }
                if ($success) {
                    $data = $decoded_resp->data;
                    if (isset($data->fullgame) && !$data->fullgame->appid == 24010) {
                        raiseError("$steamappid isn't Train Simulator DLC!");
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

                    $sql = $mysqli->prepare('UPDATE `package_list` SET `display_name` = ?, `category` = 8, `era` = -1, `country` = -1, `version` = -1, `owner` = -2, `datetime` = ?, `description` = ?, `target_path` = "", `paid` = 1, `steamappid` = ?, `steam_dev` = ? WHERE `id` = ?;');
                    $sql->bind_param('sssisi', $display_name, $release_date, $description, $steamappid, $steam_dev_link, $package_id);
                    $sql->execute();

                    $_SESSION["successMessage"] = "Package successfully updated!";
                    header("Location: ../?package=".$package_id);
                    die();
                }
                raiseError("$steamappid isn't Train Simulator DLC!");
            }
            raiseError("Refreshing Steam DLC query for \"$url\" failed with: $response_code!");
        } else {
            raiseError("Missing required parameters!");
        }
    } else {
        raiseError("Only logged users can change packages!");
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
