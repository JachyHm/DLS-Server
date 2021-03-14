<?php
header('Content-type: application/json');
require "../dls_db.php";
require "utils.php";

session_start();

$_SESSION["logged"] = false;
$_SESSION["userid"] = null;
$_SESSION["username"] = null;
$_SESSION["realname"] = null;
$_SESSION["email"] = null;
$_SESSION["token"] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST["email"]) && isset($_POST["password"])) {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
            $ip=$_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
            $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip=$_SERVER['REMOTE_ADDR'];
        }

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
            if (!$result || !$result->success || $result->score < 0.5 || $result->action != "login" || $result->hostname != "dls.rw.jachyhm.cz") {
                db_log(11, false, -1, $ip, $email, "ReCaptcha failed!", $mysqli);
                flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
            }
        } else {
            $sql = $mysqli->prepare('SELECT * FROM `action_log` WHERE (`ip_adress` = ? OR `token_used` = ?) AND NOT `success` AND `datetime` > CURRENT_TIMESTAMP - 300;');
            $sql->bind_param('ss', $ip, $email);
            $sql->execute();
            $queryResult = $sql->get_result();
        
            if (!empty($queryResult)) {
                if ($queryResult->num_rows >= 3) {
                    flushResponse(429, "Number of maximum unsuccesfull logins exceeded! Please try again later.", $mysqli);
                }
            }
        }

        $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ?;');
        $sql->bind_param('s', $email);
        $sql->execute();
        $queryResult = $sql->get_result();

        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                $row = $queryResult->fetch_assoc();

                if (password_verify($password, $row["password"])) {
                    if ($row["valid_email"]) {
                        if ($row["activated"]) {
                            $userid = $row["id"];
                            $_SESSION["logged"] = true;
                            $_SESSION["userid"] = $userid;
                            $_SESSION["realname"] = $row["nickname"];
                            $_SESSION["email"] = $row["email"];
                            $_SESSION["privileges"] = $row["privileges"];

                            if ($row["privileges"] < 0) {
                                db_log(11, false, -1, $ip, $email, "You were banned!", $mysqli);
                                flushResponse(403, "You were banned.", $mysqli);
                            }
                            
                            $sql = $mysqli->prepare('DELETE FROM `tokens` WHERE `datetime` < CURRENT_TIMESTAMP-86400 OR `user_id` = '.$userid.';');
                            $sql->execute();

                            $token = bin2hex(random_bytes(128));

                            $sql = $mysqli->prepare('INSERT INTO `tokens` (`user_id`, `token`) VALUES (?, ?);');
                            $sql->bind_param('is', $userid, $token);
                            $sql->execute();

                            $content = new stdClass();
                            $content->userid = $row["id"];
                            $content->realname = $row["nickname"];
                            $content->email = $row["email"];
                            $content->privileges = $row["privileges"];
                            $content->token = $token;
                            $_SESSION["token"] = $token;
                        
                            db_log(11, true, $userid, $ip, $token, "User logged in successfully.", $mysqli);
                            flushResponse(200, "User logged in successfully.", $mysqli, $content);
                        } else {
                            db_log(11, false, -1, $ip, $email, "Account not yet approved by moderator.", $mysqli);
                            flushResponse(403, "Account not yet approved by moderator.", $mysqli);
                        }
                    } else {
                        db_log(11, false, -1, $ip, $email, "Email not activated yet.", $mysqli);
                        flushResponse(403, "Email not activated yet.", $mysqli, -2);
                    }
                }
            }
        }
        db_log(11, false, -1, $ip, $email, "Bad login or password entered.", $mysqli);
        flushResponse(403, "Bad login or password entered.", $mysqli);
    } else {
        flushResponse(400, "Missing required parameters!", $mysqli);
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
