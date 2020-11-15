<?php
session_start();

require "../dls_db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && $_SESSION["logged"] && isset($_SESSION["userid"])) {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

        $userid = $_SESSION["userid"];
        if (isset($_POST["package_id"]) && isset($_POST["package_name"]) && isset($_POST["target_path"]) && isset($_POST["description"])) {
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
            array_unshift($query_array, $query_pattern);
            call_user_func_array(array($sql,'bind_param'), $query_array);

            if ($sql->execute()) {
                db_log(16, true, $userid, $ip, $_SESSION["token"], "Package updated $package_id!", $mysqli);
                $_SESSION["successMessage"] = "Package successfully updated!";
                header("Location: ../?package=".$package_id);
                die();
            }

            db_log(16, false, $userid, $ip, $_SESSION["token"], "Package not updated $package_id!", $mysqli);
            $_SESSION["errorMessage"] = "Unable to update!";
            header("Location: ../?package=".$package_id);
            die();
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

                    $_SESSION["errorMessage"] = "Unable to update!";
                    header("Location: ../?package=".$_POST["package_id"]);
                    die();
                }
            }

            db_log(16, true, $userid, $ip, $_SESSION["token"], "Updating package $package_id dependencies succeeded!", $mysqli);
            $_SESSION["successMessage"] = "Package dependencies successfully updated!";
            header("Location: ../?package=".$_POST["package_id"]);
            die();
        } else {
            $_SESSION["errorMessage"] = "Missing required parameters!";
            if (isset($_POST["package_id"])) {
                header("Location: ../?package=".$_POST["package_id"]);
            } else {
                header("Location: ../");
            }
            die();
        }
    } else {
        $_SESSION["errorMessage"] = "Only logged users can change packages!";
        if (isset($_POST["package_id"])) {
            header("Location: ../?package=".$_POST["package_id"]);
        } else {
            header("Location: ../");
        }
        die();
    }
} else {
    header('Content-type: application/json');

    $response->code = -1;
    $response->message = "Bad request!";
    
    $response_json = json_encode($response);
    
    die($response_json);
}
?>