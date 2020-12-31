<?php
session_start();
require "../dls_db.php";
$_SESSION["resetPwd"] = null;
if (isset($_POST["t"]) && isset($_POST["password"])) {
    if (isset($_POST["recaptcha_token"])) {
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
                $response->code = -1;
                $response->message = "Pasword reset token already expired or not valid!";
            
                $response_json = json_encode($response);
            
                db_log(13, false, -1, $ip, $_POST["t"], "Password reset token invalid!", $mysqli);
                $mysqli->close();
                die($response_json);
            }
            if (empty($pwd)) {
                $response->code = -1;
                $response->message = "Pasword can not be empty!";
            
                $response_json = json_encode($response);
            
                db_log(13, false, -1, $ip, $_POST["t"], "Password is empty!", $mysqli);
                $mysqli->close();
                die($response_json);
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
                    db_log(13, true, $row["id"], $ip, $_POST["t"], "Password changed!", $mysqli);
                }
            }
                    
            $response->code = 1;
            $response->message = "Pasword changed successfully!";

            $response_json = json_encode($response);

            $mysqli->close();
            die($response_json);
        } else {
            $response->code = -3;
            $response->message = "Sorry, but you seem to be a robot. And we definitelly do not want one here.";
    
            $response_json = json_encode($response);
    
            db_log(13, false, $ip, $_POST["t"], "ReCaptcha failed!", $mysqli);
            $mysqli->close();
            die($response_json);
        }
    } else {
        $response->code = -1;
        $response->message = "Missing required parameter!";
    
        $response_json = json_encode($response);
    
        $mysqli->close();
        die($response_json);
    }
} elseif (isset($_GET["t"])) {
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
?>