<?php
header('Content-type: application/json');
require "../dls_db.php";

session_start();

$_SESSION["logged"] = false;
$_SESSION["userid"] = null;
$_SESSION["username"] = null;
$_SESSION["realname"] = null;
$_SESSION["email"] = null;


if (isset($_POST["email"]) && isset($_POST["password"]) && isset($_POST["recaptcha_token"])) {
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = array('secret' => '6LcDPNkZAAAAABy9d7WwurgUwvjFM5JzKvPIlNcK', 'response' => $_POST["recaptcha_token"]);
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        )
    );
    $context  = stream_context_create($options);
    $result = json_decode(file_get_contents($url, false, $context));

    if ($result && $result->success && $result->score > 0.5 && $result->action == "login" && $result->hostname == "dls.rw.jachyhm.cz") {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);
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
                            $_SESSION["logged"] = true;
                            $_SESSION["userid"] = $row["id"];
                            $_SESSION["realname"] = $row["nickname"];
                            $_SESSION["email"] = $row["email"];
                            $_SESSION["privileges"] = $row["privileges"];

                            if ($row["privileges"] < 0) {
                                $response->code = -1;
                                $response->message = "You were banned.";
                            
                                $response_json = json_encode($response);
                            
                                $mysqli->close();
                                die($response_json); 
                            }

                            $response->code = 1;
                            $response->message = "User logged in successfully.";
                            $response->userid = $row["id"];
                            $response->realname = $row["nickname"];
                            $response->email = $row["email"];
                            $response->privileges = $row["privileges"];
                        
                            $response_json = json_encode($response);

                            $mysqli->close();
                            die($response_json);
                        } else {
                            $response->code = -1;
                            $response->message = "Account not yet approved by moderator.";
                        
                            $response_json = json_encode($response);
                        
                            $mysqli->close();
                            die($response_json); 
                        }
                    } else {
                        $response->code = -2;
                        $response->message = "Email not activated yet.";
                    
                        $response_json = json_encode($response);
                    
                        $mysqli->close();
                        die($response_json); 
                    }
                }
            }
        }

        $response->code = -3;
        $response->message = "Bad login or password entered.";

        $response_json = json_encode($response);

        $mysqli->close();
        die($response_json);
    } else {
        $response->code = -3;
        $response->message = "Sorry, but you seem to be a robot. And we definitelly do not want one here.";

        $response_json = json_encode($response);

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
?>