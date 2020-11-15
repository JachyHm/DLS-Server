<?php
header('Content-type: application/json');
require "../dls_db.php";
$files_folder = '../files/'; // upload directory

function flushResponse($code, $message, $body, $mysqli) 
{
    $response->code = $code;
    $response->message = $message;
    $response->content = $body;

    $response_json = json_encode($response);

    $mysqli->close();
    
    die($response_json);
}

function get_http_response_code($domain1) 
{
    $headers = get_headers($domain1);
    return substr($headers[0], 9, 3);
}

//print_r($_POST["content"]);

//if (isset($_POST["content"])) {
$reportedContent = file_get_contents("php://input");
$decodedContent = json_decode($reportedContent);

$errors = array();

try {
    foreach ($decodedContent as $dlc) {
        if (count($dlc->IncludedFiles)) {
            $id = $dlc->DLCAppId;

            $sql = $mysqli->prepare('SELECT `steamappid` FROM `package_list` WHERE `steamappid` = ?;');
            $sql->bind_param('i', $id);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (empty($queryResult) || $queryResult->num_rows == 0) {
                $store_page = "<a href=\"https://store.steampowered.com/app/$id\">https://store.steampowered.com/app/$id</a>";
                $response = file_get_contents("http://store.steampowered.com/api/appdetails/?appids=$id");

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

                    $files = $dlc->IncludedFiles;

                    $sql = $mysqli->prepare('INSERT INTO `package_list` (`file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `datetime`, `description`, `target_path`, `paid`, `steamappid`) VALUES (?, ?, 8, -1, -1, -1, -1, ?, ?, "", 1, ?) ON DUPLICATE KEY UPDATE `id` = `id`;');
                    $sql->bind_param('ssssi', $store_page, $display_name, $release_date, $description, $id);
                    $sql->execute();

                    $package_id = $mysqli->insert_id;
                    if ($package_id>0) {
                        $sql = $mysqli->prepare('INSERT INTO `file_list` (`package_id`, `fname`) VALUES (?, ?);');
                        foreach ($files as $file_name) {
                            $sql->bind_param('is', $package_id, str_replace('\\', '/', $file_name));
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
                    $date_obj = DateTime::createFromFormat("j M, Y", $data->release_date->date);
                    if ($date_obj) {
                        $release_date = $date_obj->format('Y-m-d');
                    } else {
                        $release_date = "1970-01-01";
                    }
            
                    $files = $dlc->IncludedFiles;
            
                    $sql = $mysqli->prepare('INSERT INTO `package_list` (`file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `datetime`, `description`, `target_path`, `paid`, `steamappid`) VALUES (?, ?, 8, -1, -1, -1, -1, ?, ?, "", 1, ?) ON DUPLICATE KEY UPDATE `id` = `id`;');
                    $sql->bind_param('ssssi', $store_page, $display_name, $release_date, $description, $id);
                    $sql->execute();
            
                    $package_id = $mysqli->insert_id;
                    if ($package_id>0) {
                        $sql = $mysqli->prepare('INSERT INTO `file_list` (`package_id`, `fname`) VALUES (?, ?);');
                        foreach ($files as $file_name) {
                            $sql->bind_param('is', $package_id, str_replace('\\', '/', $file_name));
                            $sql->execute();
                        }
                    }
                }
            } else {
                array_push($errors, "$id already exists!");
            }
        } else {
            array_push($errors, "$id does not contain any files!");
        }
    }
    flushResponse(1, "DLC files written successfully! Thank you for contributing!", $errors, $mysqli);
} catch (Exception $e) {
    $err = $e->getMessage();
    flushResponse(-1, "Fatal error occured! $err", new stdClass(), $mysqli);
}
//}
//flushResponse(-1, "Bad request!", new stdClass(), $mysqli);

?>