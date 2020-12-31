<?php
session_start();
header('Content-type: application/json');
require "../dls_db.php";

if (isset($_POST["recaptcha_token"]) || isset($_GET["recaptcha_token"])) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array('secret' => '6LcDPNkZAAAAABy9d7WwurgUwvjFM5JzKvPIlNcK', 'response' => (isset($_POST["recaptcha_token"]) ? $_POST["recaptcha_token"] : $_GET["recaptcha_token"]));
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = json_decode(file_get_contents($url, false, $context));

    if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
        $ip=$_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
        $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip=$_SERVER['REMOTE_ADDR'];
    }

    if ($result && $result->success && $result->score > 0.5 && $result->action == "register" && $result->hostname == "dls.rw.jachyhm.cz") {
        if (isset($_POST["password"]) && isset($_POST["email"]) && isset($_POST["nickname"])) {
            $password = trim($_POST["password"]);
            $email = trim($_POST["email"]);
            $nick = trim($_POST["nickname"]);
            $update = isset($_GET["update"]);

            if ($update) {
                if ($_SESSION["logged"] && $_SESSION["email"] == $email) {
                    $message = "";
                    if (!empty($password)) {
                        $pwd_hash = password_hash($password, PASSWORD_DEFAULT);
                        $sql = $mysqli->prepare('UPDATE `users` SET `password`=? WHERE `email`=?;');
                        $sql->bind_param('ss', $pwd_hash, $email);
                        $res = $sql->execute();
                        if (!$res) {
                            $response->code = -1;
                            $response->message = "Updating password failed!";
    
                            $response_json = json_encode($response);
    
                            $mysqli->close();
                            die($response_json);
                        }
                        $message .= "Password ";
                    }
                    if (!empty($nick)) {
                        $sql = $mysqli->prepare('UPDATE `users` SET `nickname`=? WHERE `email`=?;');
                        $sql->bind_param('ss', $nick, $email);
                        $res = $sql->execute();
                        if (!$res) {
                            $response->code = -1;
                            $response->message = "Updating nickname failed!";
    
                            $response_json = json_encode($response);
    
                            $mysqli->close();
                            die($response_json);
                        }
                        $_SESSION["realname"] = $nick;
                        if (strlen($message) > 0) {
                            $message .= "and nickname ";
                        } else {
                            $message .= "Nickname ";
                        }
                    }
    
                    $response->code = -1;
                    $response->message = "Nothing was updated!";
    
                    if (strlen($message) > 0) {
                        $message .= "was successfully updated.";
    
                        $response->code = 1;
                        $response->message = $message;
                        $response->newNick = $nick;
                    }
    
                    $response_json = json_encode($response);
    
                    db_log(12, true, $_SESSION["userid"], $ip, $email, "User data changed!", $mysqli);
                    $mysqli->close();
                    die($response_json);
                }
                db_log(12, false, -1, $ip, $email, "Unpermitted user data change!", $mysqli);
            } elseif (!empty($password) && !empty($email) && !empty($nick)) {
                $token = bin2hex(random_bytes(32));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $sql = $mysqli->prepare('INSERT INTO `users`(`password`, `email`, `nickname`, `token`, `activated`) VALUES(?, ?, ?, ?, 1);');
                $sql->bind_param('ssss', $password_hash, $email, $nick, $token);
                try {
                    if ($sql->execute()) {
                        $to      = $_POST["email"];
                        $subject = 'Registrace do systému RailWorks download station';
                        $message = "<html><body>Kliknutím na níže uvedený link aktivujete svůj účet na RailWorks DLS.<br><a href=\"https://dls.rw.jachyhm.cz/api/activate?t=$token\">https://dls.rw.jachyhm.cz/api/activate</a></body></html>";
                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                        $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                            'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();
                        mail($to, $subject, $message, $headers);

                        $response->code = 1;
                        $response->message = "Registered successfully!";
                    
                        $response_json = json_encode($response);
                        $user_id = $sql->insert_id;
                    
                        db_log(12, true, $user_id, $ip, $email, "User registered successfully!", $mysqli);
                        $mysqli->close();
                        die($response_json);
                    } else {
                        $err = $mysqli->error;
                        $message;
                        if (strpos($err, "key 'email'") !== false) {
                            $message = "This email";
                        }
                        if (strpos($err, "key 'nickname'") !== false) {
                            $message = "This nickname";
                        }
                        if (strlen($message) > 0) {
                            $message .= " is already registered. Please use another.";
                        } else {
                            $message = "Unknown error occured during registration: ".$err;
                        }
                    }
                } catch (Exception $e) {
                    $err = $e->getMessage();
                    $message;
                    if (strpos($err, "key 'email'") !== false) {
                        $message = "This email";
                    }
                    if (strpos($err, "key 'nickname'") !== false) {
                        $message = "This nickname";
                    }
                    if (strlen($message) > 0) {
                        $message .= " is already registered. Please use another.";
                    } else {
                        $message = "Unknown error occured during registration: ".$err;
                    }
                }

                $response->code = -1;
                $response->message = $message;

                $response_json = json_encode($response);

                db_log(12, false, -1, $ip, $email, "Failed registration: $message!", $mysqli);
                $mysqli->close();
                die($response_json);
            }
        } elseif (isset($_GET["resend"]) && isset($_GET["email"])) {
            $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ? AND NOT `valid_email`;');
            $sql->bind_param('s', $_GET["email"]);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();
                    $token   = $row["token"];
                    $to      = $row["email"];
                    $subject = 'Registrace do systému RailWorks download station';
                    $message = "<html><body>Kliknutím na níže uvedený link aktivujete svůj účet na RailWorks DLS.<br><a href=\"https://dls.rw.jachyhm.cz/api/activate?t=$token\">https://dls.rw.jachyhm.cz/api/activate</a></body></html>";
                    $headers  = 'MIME-Version: 1.0' . "\r\n";
                    $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                    $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                        'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();
                    mail($to, $subject, $message, $headers);

                    $response->code = 1;
                    $response->message = "Verification email successfully resent!";

                    $response_json = json_encode($response);
                    
                    db_log(14, true, $row["id"], $ip, $email, "Verification email resent!", $mysqli);
                    $mysqli->close();
                    die($response_json);
                }
            }
            $response->code = -1;
            $response->message = "Verification email was not resent!";

            $response_json = json_encode($response);
            
            db_log(14, false, -1, $ip, $email, "Verification email not resent!", $mysqli);
            $mysqli->close();
            die($response_json);
        } elseif (isset($_GET["resetPwd"]) && isset($_GET["email"])) {
            $email = $_GET["email"];
            $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ?;');
            $sql->bind_param('s', $email);
            $sql->execute();
            $queryResult = $sql->get_result();

            if (!empty($queryResult)) {
                if ($queryResult->num_rows > 0) {
                    $row = $queryResult->fetch_assoc();

                    $token = bin2hex(random_bytes(32));
                    $sql = $mysqli->prepare('INSERT INTO `pwd_resets`(`user_id`, `token`) VALUES(?, ?);');
                    $sql->bind_param('is', $row["id"], $token);
                    $sql->execute();
                    $to      = $email;
                    $subject = 'Password reset';
                    $message = "<html><body>Here is your password reset link. If you did not ask for password reset, please ignore this message.<br><a href=\"https://dls.rw.jachyhm.cz/api/pwd_reset?t=$token\">https://dls.rw.jachyhm.cz/api/pwd_reset</a></body></html>";
                    $headers  = 'MIME-Version: 1.0' . "\r\n";
                    $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                    $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                        'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                        'X-Mailer: PHP/' . phpversion();
                    mail($to, $subject, $message, $headers);
                    db_log(13, false, $row["id"], $ip, $email, "Password reset email sent!", $mysqli);
                }
            }
                
            $response->code = 0;
            $response->message = "If $email is registered, we sent reset link to it!";

            $response_json = json_encode($response);

            db_log(13, false, -1, $ip, $email, "Password reset email not sent!", $mysqli);
            $mysqli->close();
            die($response_json);
        }
    } else {
        $response->code = -3;
        $response->message = "Sorry, but you seem to be a robot. And we definitelly do not want one here.";

        $response_json = json_encode($response);

        db_log(12, false, -1, $ip, $email, "ReCaptcha failed!", $mysqli);
        $mysqli->close();
        die($response_json);
    }
}
$response->code = -1;
$response->message = "Missing required parameter!";

$response_json = json_encode($response);

$mysqli->close();
die($response_json);
?>