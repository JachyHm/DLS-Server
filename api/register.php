<?php
session_start();
header('Content-type: application/json');
require "../dls_db.php";
require "utils.php";

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
                            $message = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional //EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">
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
                                                            <span style=\"font-size: 18px;\"><strong>Your RailWorks DLS registration confirm:</strong>
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
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; text-align: center; font-family: Roboto, Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 17px; margin: 0;\">By clicking following button you confirm your RailWorks DLS registration:<br></p>
                                                    </div>
                                                </div>
                                                <div align=\"center\" class=\"button-container\" style=\"padding-top:0px;padding-right:0px;padding-bottom:0px;padding-left:0px;\">
                                                    <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"border-spacing: 0; border-collapse: collapse; mso-table-lspace:0pt; mso-table-rspace:0pt;\"><tr><td style=\"padding-top: 0px; padding-right: 0px; padding-bottom: 0px; padding-left: 0px\" align=\"center\"><v:roundrect xmlns:v=\"urn:schemas-microsoft-com:vml\" xmlns:w=\"urn:schemas-microsoft-com:office:word\" href=\"https://google.com\" style=\"height:31.5pt; width:140.25pt; v-text-anchor:middle;\" arcsize=\"10%\" stroke=\"false\" fillcolor=\"#4cd137\"><w:anchorlock/><v:textbox inset=\"0,0,0,0\"><center style=\"color:#ffffff; font-family:Tahoma, Verdana, sans-serif; font-size:16px\"><![endif]-->
                                                    <a href=\"https://dls.rw.jachyhm.cz/api/activate?t=$token\" style=\"-webkit-text-size-adjust: none; text-decoration: none; display: inline-block; color: #ffffff; background-color: #4cd137; border-radius: 4px; -webkit-border-radius: 4px; -moz-border-radius: 4px; width: auto; width: auto; border-top: 1px solid #4cd137; border-right: 1px solid #4cd137; border-bottom: 1px solid #4cd137; border-left: 1px solid #4cd137; padding-top: 5px; padding-bottom: 5px; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; text-align: center; mso-border-alt: none; word-break: keep-all;\" target=\"_blank\">
                                                        <span style=\"padding-left:20px;padding-right:20px;font-size:16px;display:inline-block;\">
                                                            <span style=\"font-size: 16px; line-height: 2; word-break: break-word; mso-line-height-alt: 32px;\">Activate
                                                            </span>
                                                        </span></a>
                                                    <!--[if mso]></center></v:textbox></v:roundrect></td></tr></table><![endif]-->
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
                                                                                </span>
                                                                            </td>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>
                                                            </td>
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
                                                <!--[if mso]><table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tr><td style=\"padding-right: 10px; padding-left: 10px; padding-top: 10px; padding-bottom: 10px; font-family: Tahoma, Verdana, sans-serif\"><![endif]-->
                                                <div style=\"color:#b9b9b9;font-family:'Roboto', Tahoma, Verdana, Segoe, sans-serif;line-height:1.2;padding-top:10px;padding-right:10px;padding-bottom:10px;padding-left:10px;\">
                                                    <div style=\"line-height: 1.2; font-size: 12px; color: #b9b9b9; font-family: 'Roboto', Tahoma, Verdana, Segoe, sans-serif; mso-line-height-alt: 14px;\">
                                                        <p style=\"font-size: 14px; line-height: 1.2; word-break: break-word; text-align: center; mso-line-height-alt: 17px; margin: 0;\">This e-mail was generated automatically based on your registration request. Please do not respond to it.
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
                    } else {
                        db_log(13, false, -1, $ip, $email, "Password reset email not sent!", $mysqli);
                    }
                } else {
                    db_log(13, false, -1, $ip, $email, "Password reset email not sent!", $mysqli);
                }
                flushResponse(200, "If $email is registered, we sent reset link to it!", $mysqli);
            }
        } else {
            db_log(12, false, -1, $ip, $email, "ReCaptcha failed!", $mysqli);
            flushResponse(403, "Sorry, but you seem to be a robot. And we definitelly do not want one here.", $mysqli);
        }
    }
    flushResponse(400, "Missing required parameter!", $mysqli);
} else {
    flushResponse(405, "Protocol not supported!", $mysqli);
}
?>
