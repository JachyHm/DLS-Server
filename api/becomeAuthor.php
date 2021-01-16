<?php
require "../dls_db.php";

$max_size = 5 * 1024 * 1024; // max file size (5mb)
$files_folder = '../files/requests_images/'; // upload directory

session_start();

function flushResponse($code, $message) 
{
    header('Content-type: application/json');

    $response = new stdClass();
    $response->code = $code;
    $response->message = $message;

    $response_json = json_encode($response);
    
    die($response_json);
}

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

//flushResponse(-1, "Not implemented yet!");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION["logged"]) && isset($_SESSION["userid"]) && isset($_POST["recaptcha_token"]) && isset($_POST["userid"]) && isset($_POST["realname"]) && isset($_POST["about"])) {
        if ($_SESSION["userid"] == $_SESSION["userid"]) {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
                $ip=$_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
                $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip=$_SERVER['REMOTE_ADDR'];
            }

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
                $mysqli->close();
                flushResponse(-1, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", new stdClass(), $mysqli);
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
                            $mysqli->close();
                            flushResponse(-1, "You already have another open request!");
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
                                    $mysqli->close();
                                    flushResponse(-1, "Critical error, unable to move file to target location!");
                                }
                                $files[$i] = $filename;
                                $i++;
                            } else {
                                $mysqli->close();
                                flushResponse(-1, "Max file size exceeded!");
                            }
                        } else {
                            $mysqli->close();
                            flushResponse(-1, "Unknown error during upload!");
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
                    $sql = $mysqli->prepare('INSERT INTO `become_author_requests` (`user_id`, `real_name`, `about`, `token`) VALUES (?, ?, ?, ?);');
                    $sql->bind_param('isss', $userid, $realname, $about, $token);
                    $sql->execute();

                    $request_id = $mysqli->insert_id;

                    $images = "";

                    foreach ($files as $file) {
                        $sql = $mysqli->prepare('INSERT INTO `become_author_request_images` (`request_id`, `image_path`) VALUES (?, ?);');
                        $sql->bind_param('is', $request_id, $file);
                        $sql->execute();

                        $images .= "<div style=\"background-color:transparent;\">
                        <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                            <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                    <div class=\"col_cont\" style=\"width:100% !important;\">
                                        <!--[if (!mso)&(!IE)]><!-->
                                        <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                            <!--<![endif]-->
                                            <div align=\"center\" class=\"img-container center autowidth\" style=\"padding-right: 0px;padding-left: 0px;\">
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr style=\"line-height:0px\"><td style=\"padding-right: 0px;padding-left: 0px;\" align=\"center\"><![endif]-->
                                                <img align=\"center\" alt=\"$file\" border=\"0\" class=\"center autowidth\" src=\"https://dls.rw.jachyhm.cz/files/requests_images/$file\" style=\"text-decoration: none; -ms-interpolation-mode: bicubic; height: auto; border: 0; width: 100%; max-width: 500px; display: block;\" title=\"$file\" width=\"500\"/>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                            </div>
                                            <!--[if (!mso)&(!IE)]><!-->
                                        </div>
                                        <!--<![endif]-->
                                    </div>
                                </div>
                                <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                            </div>
                        </div>
                    </div>";
                    }

                    $sql = $mysqli->prepare('SELECT `email` FROM `users` WHERE `privileges` > 1 AND `valid_email` = 1;');
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    $datetime = date("d.m.Y H:i:s");

                    $message_start = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional //EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:v=\"urn:schemas-microsoft-com:vml\">
    <head>
        <!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->
        <meta content=\"text/html; charset=utf-8\" http-equiv=\"Content-Type\"/>
        <meta content=\"width=device-width\" name=\"viewport\"/>
        <!--[if !mso]><!-->
        <meta content=\"IE=edge\" http-equiv=\"X-UA-Compatible\"/>
        <!--<![endif]-->
        <title>
        </title>
        <!--[if !mso]><!-->
        <link href=\"https://fonts.googleapis.com/css?family=Roboto\" rel=\"stylesheet\" type=\"text/css\"/>
        <!--<![endif]-->
        <style type=\"text/css\">		body { 			margin: 0; 			padding: 0; 		} 		table, 		td, 		tr { 			vertical-align: top; 			border-collapse: collapse; 		} 		* { 			line-height: inherit; 		} 		a[x-apple-data-detectors=true] { 			color: inherit !important; 			text-decoration: none !important; 		} 	
        </style>
        <style id=\"media-query\" type=\"text/css\">		@media (max-width: 520px) { 			.block-grid, 			.col { 				min-width: 320px !important; 				max-width: 100% !important; 				display: block !important; 			} 			.block-grid { 				width: 100% !important; 			} 			.col { 				width: 100% !important; 			} 			.col_cont { 				margin: 0 auto; 			} 			img.fullwidth, 			img.fullwidthOnMobile { 				max-width: 100% !important; 			} 			.no-stack .col { 				min-width: 0 !important; 				display: table-cell !important; 			} 			.no-stack.two-up .col { 				width: 50% !important; 			} 			.no-stack .col.num2 { 				width: 16.6% !important; 			} 			.no-stack .col.num3 { 				width: 25% !important; 			} 			.no-stack .col.num4 { 				width: 33% !important; 			} 			.no-stack .col.num5 { 				width: 41.6% !important; 			} 			.no-stack .col.num6 { 				width: 50% !important; 			} 			.no-stack .col.num7 { 				width: 58.3% !important; 			} 			.no-stack .col.num8 { 				width: 66.6% !important; 			} 			.no-stack .col.num9 { 				width: 75% !important; 			} 			.no-stack .col.num10 { 				width: 83.3% !important; 			} 			.video-block { 				max-width: none !important; 			} 			.mobile_hide { 				min-height: 0px; 				max-height: 0px; 				max-width: 0px; 				display: none; 				overflow: hidden; 				font-size: 0px; 			} 			.desktop_hide { 				display: block !important; 				max-height: none !important; 			} 		} 	
        </style>
    </head>
    <body class=\"clean-body\" style=\"margin: 0; padding: 0; -webkit-text-size-adjust: 100%; background-color: #FFFFFF;\">
        <!--[if IE]><div class=\"ie-browser\"><![endif]-->
        <table bgcolor=\"#FFFFFF\" cellpadding=\"0\" cellspacing=\"0\" class=\"nl-container\" role=\"presentation\" style=\"table-layout: fixed; vertical-align: top; min-width: 320px; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; width: 100%;\" valign=\"top\" width=\"100%\">
            <tbody>
                <tr style=\"vertical-align: top;\" valign=\"top\">
                    <td style=\"word-break: break-word; vertical-align: top;\" valign=\"top\">
                        <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td align=\"center\" style=\"background-color:#FFFFFF\"><![endif]-->
                        <div style=\"background-color:#5352ed;\">
                            <div class=\"block-grid mixed-two-up\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:#5352ed;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"333\" style=\"background-color:transparent;width:333px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num8\" style=\"display: table-cell; vertical-align: top; max-width: 320px; min-width: 328px; width: 333px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#ffffff;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; color: #ffffff; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 24px; line-height: 1.2; font-family: Roboto, Tahoma, Verdana, Segoe, sans-serif; word-break: break-word; mso-line-height-alt: 29px; margin: 0;\">
                                                            <span style=\"font-size: 24px;\"><strong>Railworks download station</strong>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td><td align=\"center\" width=\"166\" style=\"background-color:transparent;width:166px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num4\" style=\"display: table-cell; vertical-align: top; max-width: 320px; min-width: 164px; width: 166px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <div align=\"center\" class=\"img-container center fixedwidth\" style=\"padding-right: 0px;padding-left: 0px;\">
                                                    <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr style=\"line-height:0px\"><td style=\"padding-right: 0px;padding-left: 0px;\" align=\"center\"><![endif]-->
                                                    <img align=\"center\" alt=\"DLS logo\" border=\"0\" class=\"center fixedwidth\" src=\"https://dls.rw.jachyhm.cz/android-chrome-192x192.png\" style=\"text-decoration: none; -ms-interpolation-mode: bicubic; height: auto; border: 0; width: 100%; max-width: 50px; display: block;\" title=\"DLS logo\" width=\"50\"/>
                                                    <!--[if mso]></td></tr></table><![endif]-->
                                                </div>
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 0px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#555555;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:0px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; color: #555555; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 18px; line-height: 1.2; word-break: break-word; text-align: center; font-family: Roboto, Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 22px; margin: 0;\">
                                                            <span style=\"font-size: 18px;\"><strong>New author request was sent:</strong>
                                                            </span>
                                                        </p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 5px; padding-left: 5px; padding-top:0px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:0px; padding-bottom:5px; padding-right: 5px; padding-left: 5px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#555555;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; color: #555555; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; text-align: justify; font-family: Roboto, Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 17px; margin: 0;\">$about</p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#555555;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; color: #555555; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; mso-line-height-alt: 17px; margin: 0;\">Email: <strong>$user_email</strong>
                                                        </p>
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; mso-line-height-alt: 17px; margin: 0;\">Name: <strong>$realname</strong>
                                                        </p>
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; mso-line-height-alt: 17px; margin: 0;\">Request sent: <strong>$datetime</strong>
                                                        </p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"divider\" role=\"presentation\" style=\"table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;\" valign=\"top\" width=\"100%\">
                                                    <tbody>
                                                        <tr style=\"vertical-align: top;\" valign=\"top\">
                                                            <td class=\"divider_inner\" style=\"word-break: break-word; vertical-align: top; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; padding-top: 10px; padding-right: 10px; padding-bottom: 10px; padding-left: 10px;\" valign=\"top\">
                                                                <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"divider_content\" role=\"presentation\" style=\"table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-top: 1px solid #BBBBBB; width: 100%;\" valign=\"top\" width=\"100%\">
                                                                    <tbody>
                                                                        <tr style=\"vertical-align: top;\" valign=\"top\">
                                                                            <td style=\"word-break: break-word; vertical-align: top; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;\" valign=\"top\">
                                                                                <span>
                                                                                </span></td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>";

                    $message_end = "<div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <table border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"divider\" role=\"presentation\" style=\"table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;\" valign=\"top\" width=\"100%\">
                                                    <tbody>
                                                        <tr style=\"vertical-align: top;\" valign=\"top\">
                                                            <td class=\"divider_inner\" style=\"word-break: break-word; vertical-align: top; min-width: 100%; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; padding-top: 10px; padding-right: 10px; padding-bottom: 10px; padding-left: 10px;\" valign=\"top\">
                                                                <table align=\"center\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\" class=\"divider_content\" role=\"presentation\" style=\"table-layout: fixed; vertical-align: top; border-spacing: 0; border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-top: 1px solid #BBBBBB; width: 100%;\" valign=\"top\" width=\"100%\">
                                                                    <tbody>
                                                                        <tr style=\"vertical-align: top;\" valign=\"top\">
                                                                            <td style=\"word-break: break-word; vertical-align: top; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;\" valign=\"top\">
                                                                                <span>
                                                                                </span></td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top: 0px; padding-bottom: 0px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#555555;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; color: #555555; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; text-align: center; mso-line-height-alt: 17px; margin: 0;\"><strong>
                                                                <span style=\"font-size: 16px;\">Please place your answer to this user:
                                                                </span></strong>
                                                        </p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid two-up\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"250\" style=\"background-color:transparent;width:250px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num6\" style=\"display: table-cell; vertical-align: top; max-width: 320px; min-width: 246px; width: 250px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <div align=\"center\" class=\"button-container\" style=\"padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;\">
                                                    <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;\"><tr><td style=\"padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px\" align=\"center\"><v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" href=\"https://google.com\" style=\"height:31.5pt; width:140.25pt; v-text-anchor:middle;\" arcsize=\"10%\" stroke=\"false\" fillcolor=\"#4cd137\"><w:anchorlock/><v:textbox inset=\"0,0,0,0\"><center style=\"color:#ffffff; font-family:Tahoma, Verdana, sans-serif; font-size:16px\"><![endif]-->
                                                    <a href=\"https://dls.rw.jachyhm.cz/api/becomeAuthor?t=$token&action=approve\" style=\"-webkit-text-size-adjust: none; text-decoration: none; display: inline-block; color: #ffffff; background-color: #4cd137; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; width: auto; width: auto; border-top: 1px solid #4cd137; border-right: 1px solid #4cd137; border-bottom: 1px solid #4cd137; border-left: 1px solid #4cd137; padding-top: 5px; padding-bottom: 5px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; text-align: center; mso-border-alt: none; word-break: keep-all;\" target=\"_blank\">
                                                        <span style=\"padding-left:20px;padding-right:20px;font-size:16px;display:inline-block;\">
                                                            <span style=\"font-size: 16px; line-height: 2; word-break: break-word; mso-line-height-alt: 32px;\">Accept request
                                                            </span>
                                                        </span></a>
                                                    <!--[if mso]></center></v:textbox></v:roundrect></td></tr></table><![endif]-->
                                                </div>
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td><td align=\"center\" width=\"250\" style=\"background-color:transparent;width:250px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num6\" style=\"display: table-cell; vertical-align: top; max-width: 320px; min-width: 246px; width: 250px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <div align=\"center\" class=\"button-container\" style=\"padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;\">
                                                    <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;\"><tr><td style=\"padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px\" align=\"center\"><v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" href=\"https://google.com\" style=\"height:31.5pt; width:141.75pt; v-text-anchor:middle;\" arcsize=\"10%\" stroke=\"false\" fillcolor=\"#e84118\"><w:anchorlock/><v:textbox inset=\"0,0,0,0\"><center style=\"color:#ffffff; font-family:Tahoma, Verdana, sans-serif; font-size:16px\"><![endif]-->
                                                    <a href=\"https://dls.rw.jachyhm.cz/api/becomeAuthor?t=$token&action=deny\" style=\"-webkit-text-size-adjust: none; text-decoration: none; display: inline-block; color: #ffffff; background-color: #e84118; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; width: auto; width: auto; border-top: 1px solid #e84118; border-right: 1px solid #e84118; border-bottom: 1px solid #e84118; border-left: 1px solid #e84118; padding-top: 5px; padding-bottom: 5px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; text-align: center; mso-border-alt: none; word-break: keep-all;\" target=\"_blank\">
                                                        <span style=\"padding-left:20px;padding-right:20px;font-size:16px;display:inline-block;\">
                                                            <span style=\"font-size: 16px; line-height: 2; word-break: break-word; mso-line-height-alt: 32px;\">Decline request
                                                            </span>
                                                        </span></a>
                                                    <!--[if mso]></center></v:textbox></v:roundrect></td></tr></table><![endif]-->
                                                </div>
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <div style=\"background-color:transparent;\">
                            <div class=\"block-grid\" style=\"min-width: 320px; max-width: 500px; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; Margin: 0 auto; background-color: transparent;\">
                                <div style=\"border-collapse: collapse;display: table;width: 100%;background-color:transparent;\">
                                    <!--[if (mso)|(IE)]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background-color:transparent;\"><tr><td align=\"center\"><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:500px\"><tr class=\"layout-full-width\" style=\"background-color:transparent\"><![endif]-->
                                    <!--[if (mso)|(IE)]><td align=\"center\" width=\"500\" style=\"background-color:transparent;width:500px; border-top: 0px solid transparent; border-left: 0px solid transparent; border-bottom: 0px solid transparent; border-right: 0px solid transparent;\" valign=\"top\"><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 0px; padding-left: 0px; padding-top:5px; padding-bottom:5px;\"><![endif]-->
                                    <div class=\"col num12\" style=\"min-width: 320px; max-width: 500px; display: table-cell; vertical-align: top; width: 500px;\">
                                        <div class=\"col_cont\" style=\"width:100% !important;\">
                                            <!--[if (!mso)&(!IE)]><!-->
                                            <div style=\"border-top:0px solid transparent; border-left:0px solid transparent; border-bottom:0px solid transparent; border-right:0px solid transparent; padding-top:5px; padding-bottom:5px; padding-right: 0px; padding-left: 0px;\">
                                                <!--<![endif]-->
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#b9b9b9;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; color: #b9b9b9; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; text-align: center; mso-line-height-alt: 17px; margin: 0;\">This e-mail was generated automatically. Please do not respond to them.
                                                        </p>
                                                    </div>
                                                </div>
                                                <!--[if mso]></td></tr></table><![endif]-->
                                                <!--[if (!mso)&(!IE)]><!-->
                                            </div>
                                            <!--<![endif]-->
                                        </div>
                                    </div>
                                    <!--[if (mso)|(IE)]></td></tr></table><![endif]-->
                                    <!--[if (mso)|(IE)]></td></tr></table></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </div>
                        <!--[if (mso)|(IE)]></td></tr></table><![endif]--></td>
                </tr>
            </tbody>
        </table>
        <!--[if (IE)]></div><![endif]-->
    </body>
</html>";

                    if (!empty($queryResult)) {
                        while ($mail = $queryResult->fetch_assoc()["email"]) {
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
                    }

                    db_log(18, true, $userid, $ip, $_SESSION["token"], "Request successfully submited!", $mysqli);
                    $mysqli->close();
                    flushResponse(1, "Request successfully submited!");
                } else {
                    db_log(18, false, $userid, $ip, $_SESSION["token"], "You already have privileges to upload!", $mysqli);
                    $mysqli->close();
                    flushResponse(-1, "You already have privileges to upload!");
                }
            } else {
                $mysqli->close();
                flushResponse(-1, "Only logged users can perform author request!");
            }
        } else {
            flushResponse(-1, "Parameters mismatch!");
        }
    } else {
        flushResponse(-1, "Not all parameters set!");
    }
} else if ($_SERVER["REQUEST_METHOD"] === 'GET') {
    if (isset($_GET["t"]) && isset($_GET["action"]) && isset($_SESSION["logged"])) {
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
                        if ($action == "approve") {
                            $sql = $mysqli->prepare('UPDATE `become_author_requests` SET `closed` = 1, `success` = 1 WHERE `token` = ?;');
                            $sql->bind_param('s', $token);
                            $sql->execute();

                            $sql = $mysqli->prepare('UPDATE `users` SET `privileges` = 1 WHERE `id` = ?;');
                            $sql->bind_param('i', $row["user_id"]);
                            $sql->execute();
                            successMessage("User request for $username succesfully approved!");
                        } elseif ($action == "deny") {
                            $sql = $mysqli->prepare('UPDATE `become_author_requests` SET `closed` = 1, `success` = 0 WHERE `token` = ?;');
                            $sql->bind_param('s', $token);
                            $sql->execute();
                            successMessage("User request for $username succesfully denied!");
                        } else {
                            raiseError("Invalid action!");
                        }
                    } else {
                        raiseError("This request is already closed!");
                    }
                } else {
                    raiseError("No such author request found!");
                }
            } else {
                raiseError("You have to be at least global moderator to approve author requests!");
            }
        } else {
            raiseError("You have to be logged in to approve author requests!");
        }
    } else {
        raiseError("Not all parameters set!");
    }
} else {
    flushResponse(-1, "Bad request!");
}
?>
