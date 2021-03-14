<?php
/**
 * Soubor definice udaju k DB.
 * 
 * PHP version 7.1
 * 
 * @category  DB
 * @package   DB
 * @author    JachyHm <jachym.hurtik@gmail.com>
 * @copyright 2016 - 2020 JachyHm 
 * @license   Free to share
 * @version   CVS: 1
 * @link      dls_db.php
 */

function db_log($action_id, $success, $user_id, $ip_adress, $token_used, $comment, $mysqli) 
{    
    /*$details = json_decode(file_get_contents("http://api.ipstack.com/{$ip_adress}?access_key=2e458825b1c081fef6854bc3316f02c9"));
    $country = $details->country_name;
    $city = $details->city;
    $lat = $details->latitude;
    $lon = $details->longitude;
    
    $origin = $city.", ".$country;*/

    $empty = "";

    $sql = $mysqli->prepare('INSERT INTO `action_log` (`action_id`, `success`, `user_id`, `ip_adress`, `location`, `longitude`, `latitude`, `token_used`, `comment`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);');
    $sql->bind_param('iiissssss', $action_id, $success, $user_id, $ip_adress, $empty,  $empty, $empty, $token_used, $comment);
    $sql->execute();
}

/* Definice udaju */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$host = 'localhost';
$user = 'username';
$pass = 'password';
$db = 'db_name';
$mysqli = new mysqli($host, $user, $pass, $db) or die($mysqli->error);
$mysqli->set_charset("utf8");

$captcha_secret = "secret";

if (!empty($_SERVER['HTTP_CLIENT_IP'])) { //check ip from share internet
    $ip=$_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) { //to check ip is pass from proxy
    $ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip=$_SERVER['REMOTE_ADDR'];
}