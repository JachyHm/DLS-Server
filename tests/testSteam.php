<?php

//header('Content-type: application/json');
$id = $_GET["id"];

function getResponse($url, &$response = null)
{
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1:9050");
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($ch);
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    return $response_code;
}

$response = "";
$url = "http://store.steampowered.com/api/appdetails/?appids=$id";
$response_code = getResponse($url, $response);

die($response);
//file_get_contents("http://store.steampowered.com/api/appdetails/?appids=$id");
//die(file_get_contents($url));

//$response = file_get_contents("http://store.steampowered.com/api/appdetails/?appids=$id");
//die($response);