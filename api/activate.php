<?php
require "../dls_db.php";
require "utils.php";
session_start();
if (isset($_GET["t"]) && !empty(trim($_GET["t"]))) {
    $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `token` = ?;');
    $sql->bind_param('s', $_GET["t"]);
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            $row = $queryResult->fetch_assoc();
            if (!$row["valid_email"]) {
                $sql = $mysqli->prepare('UPDATE `users` SET `valid_email` = 1 WHERE `token` = ?;');
                $sql->bind_param('s', $_GET["t"]);
                if ($sql->execute()) {
                    $_SESSION["successMessage"] = "Email was verified successfully.";
                    db_log(14, true, $row["id"], $ip, $_GET["t"], "Email verified successfully!", $mysqli);
                    $mysqli->close();
                    header("Location: ../");
                    die();
                }
            } else {
                $_SESSION["errorMessage"] = "Email was already verified.";
                db_log(14, false, $row["id"], $ip, $_GET["t"], "Email was already verified!", $mysqli);
                $mysqli->close();
                header("Location: ../");
                die();
            }
        }
    }
    db_log(14, false, -1, $ip, $_GET["t"], "Invalid token!", $mysqli);
}
$_SESSION["errorMessage"] = "Invalid token!";
$mysqli->close();
header("Location: ../");
die();
?>