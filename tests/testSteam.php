<?php

header('Content-type: application/json');
$response = file_get_contents("http://store.steampowered.com/api/appdetails/?appids=1364039");
die($response);