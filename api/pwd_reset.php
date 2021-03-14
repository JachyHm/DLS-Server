<?php
session_start();
require "../dls_db.php";
require "utils.php";
$_SESSION["resetPwd"] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["t"]) && isset($_POST["password"]) && isset($_POST["recaptcha_token"])) {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array('secret' => $captcha_secret, 'response' => $_POST["recaptcha_token"]);
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = json_decode(file_get_contents($url, false, $context));
    
        if ($result && $result->success && $result->score > 0.5 && $result->action == "pwd_reset" && $result->hostname == "dls.rw.jachyhm.cz") {
            header('Content-type: application/json');
            $pwd = trim($_POST["password"]);
            if (empty($_POST["t"])) {
                db_log(13, false, -1, $ip, $_POST["t"], "Password reset token not set!", $mysqli);
                flushResponse(400, "Pasword reset token must be set!", $mysqli);
            }
            if (empty($pwd)) {
                db_log(13, false, -1, $ip, $_POST["t"], "Password is empty!", $mysqli);
                flushResponse(400, "Pasword must be set!", $mysqli);
            }
            $sql = $mysqli->prepare('SELECT * FROM `pwd_resets` WHERE `token` = ?;');
            $sql->bind_param('s', $_POST["t"]);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();
                    $password_hash = password_hash($pwd, PASSWORD_DEFAULT);
                    $sql = $mysqli->prepare('UPDATE `users` SET `password`=? WHERE `id` = ?;');
                    $sql->bind_param('si', $password_hash, $row["user_id"]);
                    $sql->execute();

                    $sql = $mysqli->prepare('DELETE FROM `pwd_resets` WHERE `token` = ?;');
                    $sql->bind_param('s', $_POST["t"]);
                    $sql->execute();

                    db_log(13, true, $row["user_id"], $ip, $_POST["t"], "Password changed!", $mysqli);
                    flushResponse(200, "Pasword changed successfully!", $mysqli);
                }
            }

            db_log(13, false, -1, $ip, $_POST["t"], "Password reset token invalid!", $mysqli);
            flushResponse(404, "Pasword reset token already expired or not valid!", $mysqli);
        } else {
            db_log(13, false, $ip, $_POST["t"], "ReCaptcha failed!", $mysqli);
            flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
        }
    } else {
        flushResponse(400, "Not all parameters set!", $mysqli);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') { 
    if (isset($_GET["t"])) {
        date_default_timezone_set("Europe/Prague");
        $timestamp = date("Y-m-d H:i:s", time()-3600*48);
        $sql = $mysqli->prepare('DELETE FROM `pwd_resets` WHERE `generated` < ?;');
        $sql->bind_param('s', $timestamp);
        $sql->execute();

        $sql = $mysqli->prepare('SELECT * FROM `pwd_resets` WHERE `token` = ?;');
        $sql->bind_param('s', $_GET["t"]);
        $sql->execute();
        $queryResult = $sql->get_result();

        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                $mysqli->close();
                $_SESSION["resetPwd"] = $_GET["t"];
                header("Location: /");
                die();
            }
        }
    }
    $_SESSION["errorMessage"] = "Pasword reset token already expired or not valid!";
    header("Location: /");
    die();
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
