<?php
session_start();
header('Content-type: application/json');
require "../dls_db.php";
require "utils.php";
require "emailResources.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_POST["recaptcha_token"]) || isset($_GET["recaptcha_token"])) {
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = array('secret' => $captcha_secret, 'response' => (isset($_POST["recaptcha_token"]) ? $_POST["recaptcha_token"] : $_GET["recaptcha_token"]));
        $options = array(
            'http' => array(
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            )
        );
        $context  = stream_context_create($options);
        $result = json_decode(file_get_contents($url, false, $context));

        if ($result && $result->success && $result->score > 0.5 && $result->action == "register" && $result->hostname == "dls.rw.jachyhm.cz") {
            if (isset($_POST["password"]) && isset($_POST["email"]) && isset($_POST["nickname"])) {
                $password = trim($_POST["password"]);
                $email = trim($_POST["email"]);
                $nick = trim($_POST["nickname"]);
                $update = isset($_GET["update"]);

                if ($update) {
                    if ($_SESSION["logged"] && $_SESSION["email"] == $email) {
                        $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ?;');
                        $sql->bind_param('s', $email);
                        $sql->execute();
                        $queryResult = $sql->get_result();
                
                        if (!empty($queryResult)) {
                            if ($queryResult->num_rows > 0) {
                                $row = $queryResult->fetch_assoc();
                
                                if (password_verify($password, $row["password"])) {
                                    $message = "";
                                    if (isset($_POST["new_password"]) && !empty(trim($_POST["new_password"]))) {
                                        $new_password = $_POST["new_password"];
                                        if (!password_verify($new_password, $row["password"])) {
                                            $pwd_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                            $sql = $mysqli->prepare('UPDATE `users` SET `password`=? WHERE `email`=?;');
                                            $sql->bind_param('ss', $pwd_hash, $email);
                                            $res = $sql->execute();
                                            if (!$res) {
                                                flushResponse(500, "Updating password failed!", $mysqli);
                                            }
                                            $message .= "Password ";
                                        } else {
                                            flushResponse(400, "New password must be different than old one!", $mysqli);
                                        }
                                    } 
                                    
                                    if (!empty($nick)) {
                                        $sql = $mysqli->prepare('UPDATE `users` SET `nickname`=? WHERE `email`=?;');
                                        $sql->bind_param('ss', $nick, $email);
                                        $res = $sql->execute();
                                        if (!$res) {
                                            flushResponse(500, "Updating nickname failed!", $mysqli);
                                        }
                                        $_SESSION["realname"] = $nick;
                                        if (strlen($message) > 0) {
                                            $message .= "and nickname ";
                                        } else {
                                            $message .= "Nickname ";
                                        }
                                    }
                    
                                    $code = 400;
                                    $message = "Nothing was updated!";
                    
                                    if (strlen($message) > 0) {
                                        $code = 200;
                                        $message .= "was successfully updated.";

                                        $_SESSION["successMessage"] = $message;
                                    }
                    
                                    db_log(12, true, $_SESSION["userid"], $ip, $email, "User data changed!", $mysqli);
                                    flushResponse($code, $message, $mysqli);
                                }
                            }
                        }
                    }
                    db_log(12, false, -1, $ip, $email, "Unpermitted user data change!", $mysqli);
                    flushResponse(403, "You don't have enough permissions to change another user data!", $mysqli);
                } elseif (!empty($password) && !empty($email) && !empty($nick)) {
                    $token = bin2hex(random_bytes(32));
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $sql = $mysqli->prepare('INSERT INTO `users`(`password`, `email`, `nickname`, `token`, `activated`) VALUES(?, ?, ?, ?, 1);');
                    $sql->bind_param('ssss', $password_hash, $email, $nick, $token);
                    try {
                        if ($sql->execute()) {
                            $to      = $_POST["email"];
                            $subject = 'Your RailWorks DLS registration confirm';
                            $message = EmailContents::emailVerify($token);
                            $headers  = 'MIME-Version: 1.0' . "\r\n";
                            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                            $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                                'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                                'X-Mailer: PHP/' . phpversion();
                            mail($to, $subject, $message, $headers);

                            $user_id = $sql->insert_id;
                            db_log(12, true, $user_id, $ip, $email, "User registered successfully!", $mysqli);
                            flushResponse(200, "Registered successfully!", $mysqli);
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

                    db_log(12, false, -1, $ip, $email, "Failed registration: $message!", $mysqli);
                    flushResponse(500, $message, $mysqli);
                }
            } elseif (isset($_GET["resend"]) && isset($_GET["email"])) {
                $email = $_GET["email"];

                $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ? AND NOT `valid_email`;');
                $sql->bind_param('s', $email);
                $sql->execute();
                $queryResult = $sql->get_result();

                if (!empty($queryResult)) {
                    if ($queryResult->num_rows > 0) {
                        $row = $queryResult->fetch_assoc();
                        $token   = $row["token"];
                        $to      = $row["email"];
                        $subject = 'Your RailWorks DLS registration confirm';
                        $message = EmailContents::emailVerify($token);
                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                        $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                            'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();
                        mail($to, $subject, $message, $headers);
                        
                        db_log(14, true, $row["id"], $ip, $email, "Verification email resent!", $mysqli);
                        flushResponse(200, "Verification email successfully resent!", $mysqli);
                    }
                }
                
                db_log(14, false, -1, $ip, $email, "Verification email not resent!", $mysqli);
                flushResponse(500, "Verification email not resent!", $mysqli);
            } elseif (isset($_GET["resetPwd"]) && isset($_GET["email"])) {
                $email = $_GET["email"];
                $sql = $mysqli->prepare('SELECT * FROM `users` WHERE `email` = ?;');
                $sql->bind_param('s', $email);
                $sql->execute();
                $queryResult = $sql->get_result();

                if (!empty($queryResult)) {
                    if ($queryResult->num_rows > 0) {
                        $row = $queryResult->fetch_assoc();
                        $user_id = $row["id"];
                        $username = $row["nickname"];

                        $token = bin2hex(random_bytes(32));
                        $sql = $mysqli->prepare('INSERT INTO `pwd_resets`(`user_id`, `token`) VALUES(?, ?);');
                        $sql->bind_param('is', $user_id, $token);
                        $sql->execute();
                        $to      = $email;
                        $subject = 'Your RailWorks DLS password reset';
                        $message = EmailContents::passwordReset($username, $token);
                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                        $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                            'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();
                        mail($to, $subject, $message, $headers);
                        db_log(13, false, $user_id, $ip, $email, "Password reset email sent!", $mysqli);
                    } else {
                        db_log(13, false, -1, $ip, $email, "Password reset email not sent!", $mysqli);
                    }
                } else {
                    db_log(13, false, -1, $ip, $email, "Password reset email not sent!", $mysqli);
                }
                flushResponse(200, "If $email is registered, we sent reset link to it!", $mysqli);
            }
        } else {
            flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
        }
    }
    flushResponse(400, "Missing required parameter!", $mysqli);
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
