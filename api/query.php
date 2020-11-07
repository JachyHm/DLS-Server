<?php
header('Content-type: application/json');
require "../dls_db.php";

function flushResponse($code, $message, $body, $mysqli) 
{
    $response->code = $code;
    $response->message = $message;
    $response->content = $body;

    $response_json = json_encode($response);

    $mysqli->close();
    
    die($response_json);
}

if (isset($_GET["file"]) || isset($_POST["file"])) {
    if (isset($_GET["file"])) {
        $fname = "%".trim(urldecode($_GET["file"]))."%";
    } else {
        $fname = "%".trim(urldecode($_POST["file"]))."%";
    }

    $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `fname` LIKE ?;');
    $sql->bind_param('s', $fname);
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            if ($queryResult->num_rows > 1) {
                $row = $queryResult->fetch_assoc();
                $last_id = $row["package_id"];
                while ($row = $queryResult->fetch_assoc()) {
                    if ($last_id != $row["package_id"]) {
                        flushResponse(-1, "Fatal error! More than one package containing such file was found!", new stdClass(), $mysqli);
                    }
                }
            }
            $queryResult->data_seek(0);
            $row = $queryResult->fetch_assoc();
            $package_id = $row["package_id"];
            
            $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `id` = ?;');
            $sql->bind_param('i', $package_id);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();

                    $package->id = $package_id;
                    $package->file_name = $row["file_name"];
                    $package->display_name = $row["display_name"];
                    $package->category = $row["category"];
                    $package->era = $row["era"];
                    $package->country = $row["country"];
                    $package->paid = $row["paid"];
                    $package->steamappid = $row["steamappid"];
                    $package->version = $row["version"];
                    $package->owner = $row["owner"];
                    $package->created = $row["datetime"];
                    $package->files = array();
                    $package->dependencies = array();

                    $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `package_id` = ?;');
                    $sql->bind_param('i', $package_id);
                    $sql->execute();
                    $queryResult = $sql->get_result();
        
                    if (!empty($queryResult)) {
                        while ($row = $queryResult->fetch_assoc()) {
                            array_push($package->files, $row["fname"]);
                        }
                    }

                    $sql = $mysqli->prepare('SELECT * FROM `dependency_list` WHERE `package_id` = ?;');
                    $sql->bind_param('i', $package_id);
                    $sql->execute();
                    $queryResult = $sql->get_result();
        
                    if (!empty($queryResult)) {
                        while ($row = $queryResult->fetch_assoc()) {
                            array_push($package->dependencies, $row["dependency_package_id"]);
                        }
                    }

                    flushResponse(1, "Success!", $package, $mysqli);
                } else {
                    flushResponse(-1, "Fatal error! No package with such ID found!", new stdClass(), $mysqli);
                }
            } else {
                flushResponse(-1, "Fatal error! No package with such ID found!", new stdClass(), $mysqli);
            }
        } else {
            flushResponse(-1, "No package containing such file found!", new stdClass(), $mysqli);
        }
    } else {
        flushResponse(-1, "No package containing such file found!", new stdClass(), $mysqli);
    }
} else if (isset($_GET["listFiles"]) || isset($_POST["listFiles"])) {
    $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `fname` LIKE "%.%bin" OR `fname` LIKE "%.%xml";');
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            $files = array();
            while ($row = $queryResult->fetch_assoc()) {
                $files[] = $row["fname"];
            }
            flushResponse(1, "Success!", $files, $mysqli);
        }
        flushResponse(-1, "No packages found!", new stdClass(), $mysqli);
    }
    flushResponse(-1, "No packages found!", new stdClass(), $mysqli);
} else if (isset($_GET["id"]) || isset($_POST["id"])) {
    if (isset($_GET["id"])) {
        $package_id = trim($_GET["id"]);
    } else {
        $package_id = trim($_POST["id"]);
    }
    $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `id` = ?;');
    $sql->bind_param('i', $package_id);
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            $row = $queryResult->fetch_assoc();

            $package->id = $package_id;
            $package->file_name = $row["file_name"];
            $package->display_name = $row["display_name"];
            $package->category = $row["category"];
            $package->era = $row["era"];
            $package->country = $row["country"];
            $package->paid = $row["paid"];
            $package->steamappid = $row["steamappid"];
            $package->version = $row["version"];
            $package->owner = $row["owner"];
            $package->created = $row["datetime"];
            $package->files = array();
            $package->dependencies = array();

            $sql = $mysqli->prepare('SELECT * FROM `file_list` WHERE `package_id` = ?;');
            $sql->bind_param('i', $package_id);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                while ($row = $queryResult->fetch_assoc()) {
                    array_push($package->files, $row["fname"]);
                }
            }

            $sql = $mysqli->prepare('SELECT * FROM `dependency_list` WHERE `package_id` = ?;');
            $sql->bind_param('i', $package_id);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                while ($row = $queryResult->fetch_assoc()) {
                    array_push($package->dependencies, $row["dependency_package_id"]);
                }
            }

            flushResponse(1, "Success!", $package, $mysqli);
        } else {
            flushResponse(-1, "Fatal error! No package with such ID found!", new stdClass(), $mysqli);
        }
    } else {
        flushResponse(-1, "Fatal error! No package with such ID found!", new stdClass(), $mysqli);
    }
} else if (isset($_GET["keyword"])) {
    $query = "";
    $query_pattern = "";
    $query_array = array();

    $keyword = trim(urldecode($_GET["keyword"]));
    if (!empty($keyword)) {
        $switch = 9;
        if (isset($_GET["searchBy"])) {
            $switch = $_GET["searchBy"];
        }
        switch ($switch) {
        case 0:
            $query .= "`display_name` LIKE ?";
            $query_pattern .= "s";
            array_push($query_array, "%".$keyword."%");
            break;
        case 1:
            //TODO: convert author name to ID!!!
            $sql = $mysqli->prepare('SELECT `id` FROM `users` WHERE `nickname` LIKE ?;');
            $_ = "%".$keyword."%";
            $sql->bind_param('s', $_);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();
                    $query .= "`owner` = ".$row["id"];
                    break;
                }
            }
            flushResponse(-1, "Nothing found!".$sql->error, new stdClass(), $mysqli);
            break;
        case 2:
            $query .= "`description` LIKE ?";
            $query_pattern .= "s";
            array_push($query_array, "%".$keyword."%");
            break;
        case 3:
            $query .= "`package_list`.`id` = ?";
            $query_pattern .= "i";
            array_push($query_array, (int)$keyword);
            break;
        case 4:
            $sql = $mysqli->prepare('SELECT `package_id` FROM `file_list` WHERE `fname` LIKE ? GROUP BY `package_id`;');
            $_ = "%".$keyword."%";
            $sql->bind_param('s', $_);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    if ($queryResult->num_rows == 1) {
                        $row = $queryResult->fetch_assoc();
                        $query .= "`package_list`.`id` = '".$row["package_id"]."'";
                    } else {
                        $_query = array();
                        while ($row = $queryResult->fetch_assoc()) {
                            array_push($_query, "`package_list`.`id` = '".$row["package_id"]."'");
                        }
                        $query .= "(".implode(" OR ", $_query).")";
                    }
                    break;
                }
            }
            flushResponse(-1, "Nothing found!".$sql->error, new stdClass(), $mysqli);
            break;
        default:
            $query .= "`description` LIKE ?";
            $query_pattern .= "s";
            array_push($query_array, "%".$keyword."%");
            break;
        }
    }

    if (isset($_GET["category"]) && $_GET["category"] > 0) {
        if (strlen($query) > 0) {
            $query .= " AND `category` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["category"]);
        } else {
            $query .= "`category` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["category"]);
        }
    }

    if (isset($_GET["country"]) && $_GET["country"] !== 0) {
        if (strlen($query) > 0) {
            $query .= " AND `country` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["country"]);
        } else {
            $query .= "`country` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["country"]);
        }
    }

    if (isset($_GET["era"]) && $_GET["era"] > 0) {
        if (strlen($query) > 0) {
            $query .= " AND `era` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["era"]);
        } else {
            $query .= "`era` = ?";
            $query_pattern .= "i";
            array_push($query_array, $_GET["era"]);
        }
    }

    //print_r('SELECT `package_list`.`id`, `display_name`, `version`, `description`, `owner`, `users`.`nickname` AS `author`, `categories`.`text` AS `category`, `eras`.`text` AS `era`, `countries`.`text` AS `country` FROM `package_list` LEFT JOIN `users` ON `package_list`.`owner` = `users`.`id` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` LEFT JOIN `eras` ON `package_list`.`era` = `eras`.`id` LEFT JOIN `countries` ON `package_list`.`country` = `countries`.`id` WHERE '.$query.';');
    if (strlen($query) > 0) {
        $sql = $mysqli->prepare('SELECT `package_list`.`id`, `display_name`, `version`, `description`, `owner`, `users`.`nickname` AS `author`, `categories`.`text` AS `category`, `eras`.`text` AS `era`, `countries`.`text` AS `country` FROM `package_list` LEFT JOIN `users` ON `package_list`.`owner` = `users`.`id` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` LEFT JOIN `eras` ON `package_list`.`era` = `eras`.`id` LEFT JOIN `countries` ON `package_list`.`country` = `countries`.`id` WHERE '.$query.';');
        array_unshift($query_array, $query_pattern);
        call_user_func_array(array($sql,'bind_param'), $query_array);
    } else {
        $sql = $mysqli->prepare('SELECT `package_list`.`id`, `display_name`, `version`, `description`, `owner`, `users`.`nickname` AS `author`, `categories`.`text` AS `category`, `eras`.`text` AS `era`, `countries`.`text` AS `country` FROM `package_list` LEFT JOIN `users` ON `package_list`.`owner` = `users`.`id` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` LEFT JOIN `eras` ON `package_list`.`era` = `eras`.`id` LEFT JOIN `countries` ON `package_list`.`country` = `countries`.`id`;');
    }
    if ($sql->execute()) {
        $queryResult = $sql->get_result();
    
        $rows = array();
    
        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                while ($row = $queryResult->fetch_assoc()) {
                    $row["author"] = "<a href='?author=".$row["owner"]."'>".$row["author"]."</a>";
                    $row["display_name"] = "<a href='?package=".$row["id"]."'>".$row["display_name"]."</a>";
                    $row["id"] = "<a href='?package=".$row["id"]."'>".$row["id"]."</a>";
                    $rows[] = $row;
                }
                flushResponse(1, "Success!", $rows, $mysqli);
            }
        }
        flushResponse(-1, "Nothing found!".$sql->error, new stdClass(), $mysqli);
    }
    flushResponse(-1, "Unknown error: ".$sql->error, new stdClass(), $mysqli);
} else if (isset($_GET["searchFor"]) || isset($_POST["searchFor"])) {
    if (isset($_GET["searchFor"])) {
        $keyword = trim(urldecode($_GET["searchFor"]))."%";
    } else {
        $keyword = trim(urldecode($_POST["searchFor"]))."%";
    }
    if (!empty($keyword)) {
        $limit = 30;
        if (isset($_GET["limit"])) {
            $limit = $_GET["limit"];
        } else if (isset($_POST["limit"])) {
            $limit = $_POST["limit"];
        }
        $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `id` LIKE ? OR `display_name` LIKE ? OR `file_name` LIKE ? ORDER BY `display_name` LIMIT ?;');
        $sql->bind_param("sssi", $keyword, $keyword, $keyword, $limit);
        if ($sql->execute()) {
            $queryResult = $sql->get_result();
        
            $rows = array();
            $ids = array();
        
            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    while ($row = $queryResult->fetch_assoc()) {
                        $rows[] = $row;
                        array_push($ids, $row["id"]);
                    }
                }
            }

            if (count($rows) < $limit) {
                $keyword = "%".$keyword;
                $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `id` LIKE ? OR `display_name` LIKE ? OR `file_name` LIKE ? ORDER BY `display_name` LIMIT ?;');
                $sql->bind_param("sssi", $keyword, $keyword, $keyword, $limit);
                if ($sql->execute()) {
                    $queryResult = $sql->get_result();
                    if (!empty($queryResult)) {
                        if ($queryResult->num_rows > 0) {
                            while ($row = $queryResult->fetch_assoc()) {
                                if (!in_array($row["id"], $ids)) {
                                    array_push($rows, $row);
                                }
                            }
                        }
                    }
                }
            }

            if (count($rows)) {
                flushResponse(1, "Success!", $rows, $mysqli);
            }

            flushResponse(-1, "Nothing found!", new stdClass(), $mysqli);
        }
        flushResponse(-1, "Unknown error!", new stdClass(), $mysqli);
    } else {
        flushResponse(-1, "Nothing to search for!", new stdClass(), $mysqli);
    }
} else if (isset($_GET["packageFile"]) || isset($_POST["packageFile"])) {
    if (isset($_GET["packageFile"])) {
        $keyword = trim(urldecode($_GET["packageFile"]));
    } else {
        $keyword = trim(urldecode($_POST["packageFile"]));
    }
    if (!empty($keyword)) {
        $sql = $mysqli->prepare('SELECT * FROM `package_list` WHERE `file_name` = ?;');
        $sql->bind_param("s", $keyword);
        if ($sql->execute()) {
            $queryResult = $sql->get_result();
        
            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();
                    flushResponse(1, "There is file with such name.", $row, $mysqli);
                }
            }
            flushResponse(-1, "Nothing found!", new stdClass(), $mysqli);
        }
        flushResponse(-1, "Unknown error!", new stdClass(), $mysqli);
    } else {
        flushResponse(-1, "Nothing to search for!", new stdClass(), $mysqli);
    }
}
flushResponse(-1, "Bad request!", new stdClass(), $mysqli);

?>