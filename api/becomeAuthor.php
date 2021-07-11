<?php
require "../dls_db.php";
require "utils.php";
require "emailResources.php";

$max_size = 5 * 1024 * 1024; // max file size (5mb)
$files_folder = '../files/requests_images/'; // upload directory

session_start();

function raiseError($message) {
    $_SESSION["errorMessage"] = $message;
    header("Location: ../");
    die();
}

function successMessage($message) {
    $_SESSION["successMessage"] = $message;
    header("Location: ../");
    die();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && isset($_SESSION["userid"]) && isset($_POST["recaptcha_token"]) && isset($_POST["userid"]) && isset($_POST["realname"]) && isset($_POST["about"])) {
        if ($_SESSION["userid"] == $_SESSION["userid"]) {
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
            if (!$result || !$result->success || $result->score < 0.5 || $result->action != "claim_author" || $result->hostname != "dls.rw.jachyhm.cz") {
                db_log(18, false, -1, $ip, $_POST["userid"], "ReCaptcha failed!", $mysqli);
                flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
            }

            if ($_SESSION["logged"]) {
                if (isset($_SESSION["privileges"]) && $_SESSION["privileges"] == 0) {
                    $userid = $_SESSION["userid"];

                    $sql = $mysqli->prepare('SELECT * FROM `become_author_requests` WHERE `user_id` = ? AND `closed` = 0;');
                    $sql->bind_param('i', $userid);
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    if (!empty($queryResult)) {
                        if ($queryResult->num_rows > 0) {
                            db_log(18, false, $userid, $ip, $_SESSION["token"], "There already is another open request for this user!", $mysqli);
                            flushResponse(409, "You already have another open request!", $mysqli);
                        }
                    }

                    $i = 0;
                    $files = [];
                    foreach ($_FILES['images']['tmp_name'] as $tmpName) {
                        if (@is_uploaded_file($tmpName)) {
                            if ($_FILES['images']['size'][$i] < $max_size) {
                                $filename = bin2hex(random_bytes(16)).".".pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
                                $target_path = $files_folder.$filename;
                                if (!move_uploaded_file($tmpName, $target_path)) {
                                    flushResponse(500, "Critical error, unable to move file to target location!", $mysqli);
                                }
                                $files[$i] = $filename;
                                $i++;
                            } else {
                                flushResponse(413, "Max file size exceeded!", $mysqli);
                            }
                        } else {
                            flushResponse(500, "Unknown error during upload!", $mysqli);
                        }
                    }

                    $realname = $_POST["realname"];
                    $about = $_POST["about"];
                    $user_email = "";
                    
                    $sql = $mysqli->prepare('SELECT `email` FROM `users` WHERE `id` = ?;');
                    $sql->bind_param('i', $userid);
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    if (!empty($queryResult) && $queryResult->num_rows > 0) {
                        $user_email = $queryResult->fetch_assoc()["email"];
                    }

                    $token = bin2hex(random_bytes(128));
                    $sql = $mysqli->prepare('INSERT INTO `become_author_requests` (`user_id`, `real_name`, `about`, `token`, `refusal_statement`) VALUES (?, ?, ?, ?, "");');
                    $sql->bind_param('isss', $userid, $realname, $about, $token);
                    $sql->execute();

                    $request_id = $mysqli->insert_id;

                    $images = "";

                    foreach ($files as $file) {
                        $sql = $mysqli->prepare('INSERT INTO `become_author_request_images` (`request_id`, `image_path`) VALUES (?, ?);');
                        $sql->bind_param('is', $request_id, $file);
                        $sql->execute();

                        $images .= EmailContents::becomeAuthorImages($file);
                    }

                    $sql = $mysqli->prepare('SELECT `email` FROM `users` WHERE `privileges` > 1 AND `valid_email` = 1;');
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    $datetime = date("d.m.Y H:i:s");

                    $message_start = EmailContents::becomeAuthorStart($about, $user_email, $realname, $datetime);

                    $message_end = EmailContents::becomeAuthorEnd($token);

                    if (!empty($queryResult)) {
                        while ($row = $queryResult->fetch_assoc()) {
                            $mail    = $row["email"];
                            $to      = $mail;
                            $subject = 'New author request awaits confirmation';
                            $message = $message_start . $images . $message_end;
                            $headers  = 'MIME-Version: 1.0' . "\r\n";
                            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                            $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                                'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                                'X-Mailer: PHP/' . phpversion();
                            mail($to, $subject, $message, $headers);
                        }
                        $to      = "postman@jachyhm.cz";
                        $subject = 'New author request awaits confirmation';
                        $message = $message_start . $images . $message_end;
                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                        $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                            'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                            'X-Mailer: PHP/' . phpversion();
                        mail($to, $subject, $message, $headers);
                    }

                    db_log(18, true, $userid, $ip, $_SESSION["token"], "Request successfully submited!", $mysqli);
                    flushResponse(200, "Request successfully submited!", $mysqli);
                } else {
                    db_log(18, false, $userid, $ip, $_SESSION["token"], "You already have privileges to upload!", $mysqli);
                    flushResponse(409, "You already have privileges to upload!", $mysqli);
                }
            } else {
                flushResponse(403, "Only logged users can perform author request!", $mysqli);
            }
        } else {
            flushResponse(400, "Parameters mismatch!", $mysqli);
        }
    } elseif (isset($_SESSION["logged"]) && isset($_POST["t"]) && isset($_POST["action"]) && isset($_POST["recaptcha_token"])) {
        if (isset($_SESSION["logged"])) {
            $token = $_POST["t"];
            if ($_SESSION["logged"]) {
                if ($_SESSION["privileges"] > 1) {
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
                    if (!$result || !$result->success || $result->score < 0.5 || $result->action != "claim_author_deny" || $result->hostname != "dls.rw.jachyhm.cz") {
                        db_log(18, false, -1, $ip, $_POST["t"], "ReCaptcha failed!", $mysqli);
                        flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
                    }

                    $sql = $mysqli->prepare('SELECT * FROM `become_author_requests` LEFT JOIN `users` ON `become_author_requests`.`user_id` = `users`.`id` WHERE `become_author_requests`.`token` = ?;');
                    $sql->bind_param('s', $token);
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    if (!empty($queryResult) && $queryResult->num_rows > 0) {
                        $row = $queryResult->fetch_assoc();

                        if ($row["closed"] === 0) {
                            $action = $_POST["action"];
                            $username = $row["nickname"];
                            $userid = $row["user_id"];
                            $email = $row["email"];

                            if ($action == "denyWithMessage") {
                                if (isset($_POST["message"]) && !empty($_POST["message"])) {
                                    $message = trim($_POST["message"]);
                                    $sql = $mysqli->prepare('UPDATE `become_author_requests` SET `closed` = 1, `success` = 0, `refusal_statement` = ? WHERE `token` = ?;');
                                    $sql->bind_param('ss', $message, $token);
                                    $sql->execute();

                                    $to      = $email;
                                    $subject = 'Your RailWorks DLS become author confirmation';
                                    $message = EmailContents::becomeAuthorResponse($username, $message);
                                    $headers  = 'MIME-Version: 1.0' . "\r\n";
                                    $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                                    $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                                        'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                                        'X-Mailer: PHP/' . phpversion();
                                    mail($to, $subject, $message, $headers);

                                    flushResponse(200, "User request for $username succesfully denied!", $mysqli);
                                }
                                flushResponse(404, "None or empty statement supplied!", $mysqli);
                            } else {
                                flushResponse(404, "Invalid action!", $mysqli);
                            }
                        } else {
                            flushResponse(403, "This request is already closed!", $mysqli);
                        }
                    } else {
                        flushResponse(404, "No such author request found!", $mysqli);
                    }
                } else {
                    flushResponse(403, "You have to be at least global moderator to deny author requests!", $mysqli);
                }
            }
        }
        flushResponse(403, "You have to be logged in to approve author requests!", $mysqli);
    } else {
        flushResponse(400, "Not all parameters set!", $mysqli);
    }
} else if ($_SERVER["REQUEST_METHOD"] === 'GET') {
    if (isset($_GET["t"]) && isset($_GET["action"])) {
        if (isset($_SESSION["logged"])) {
            $token = $_GET["t"];
            if ($_SESSION["logged"]) {
                if ($_SESSION["privileges"] > 1) {
                    $sql = $mysqli->prepare('SELECT * FROM `become_author_requests` LEFT JOIN `users` ON `become_author_requests`.`user_id` = `users`.`id` WHERE `become_author_requests`.`token` = ?;');
                    $sql->bind_param('s', $token);
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    if (!empty($queryResult) && $queryResult->num_rows > 0) {
                        $row = $queryResult->fetch_assoc();

                        if ($row["closed"] === 0) {
                            $action = $_GET["action"];
                            $username = $row["nickname"];
                            $userid = $row["user_id"];
                            $email = $row["email"];

                            if ($action == "approve") {
                                $sql = $mysqli->prepare('UPDATE `become_author_requests` SET `closed` = 1, `success` = 1 WHERE `token` = ?;');
                                $sql->bind_param('s', $token);
                                $sql->execute();

                                $sql = $mysqli->prepare('UPDATE `users` SET `privileges` = 1 WHERE `id` = ?;');
                                $sql->bind_param('i', $userid);
                                $sql->execute();

                                $to      = $email;
                                $subject = 'Your RailWorks DLS become author confirmation';
                                $message = EmailContents::becomeAuthorResponse($username);
                                $headers  = 'MIME-Version: 1.0' . "\r\n";
                                $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
                                $headers .= 'From: RailWorks download station <dls.rw@jachyhm.cz>' . "\r\n" .
                                    'Reply-To: noreply@jachyhm.cz' . "\r\n" .
                                    'X-Mailer: PHP/' . phpversion();
                                mail($to, $subject, $message, $headers);

                                $mysqli->close();
                                successMessage("User request for $username succesfully approved!");
                            } elseif ($action == "deny") {
                                $mysqli->close();
                                $_SESSION["denyBecomeAuthor"] = $token;
                                header("Location: /");
                                die();
                            } else {
                                $mysqli->close();
                                raiseError("Invalid action!");
                            }
                        } else {
                            $mysqli->close();
                            raiseError("This request is already closed!");
                        }
                    } else {
                        $mysqli->close();
                        raiseError("No such author request found!");
                    }
                } else {
                    $mysqli->close();
                    raiseError("You have to be at least global moderator to approve author requests!");
                }
            }
        }
        $mysqli->close();
        raiseError("You have to be logged in to approve author requests!");
    } else {
        $mysqli->close();
        raiseError("Not all parameters set!");
    }
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
