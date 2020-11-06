<?php
require "../dls_db.php";
session_start();
if (isset($_GET["t"])) {
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
                    $mysqli->close();
                    $_SESSION["successMessage"] = "Email was verified successfully.";
                    header("Location: ../");
                    die();
                }
            } else {
                $mysqli->close();
                $_SESSION["errorMessage"] = "Email was already verified.";
                header("Location: ../");
                die();
            }
        }
    }
}
$_SESSION["errorMessage"] = "Invalid token!";
header("Location: ../");
die();
?>