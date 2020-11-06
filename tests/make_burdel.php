<?php
/*require "../dls_db.php";

function readable_random_string($length = 6)
{  
    $string = '';
    $vowels = array("a","e","i","o","u");  
    $consonants = array(
        'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 
        'n', 'p', 'r', 's', 't', 'v', 'w', 'x', 'y', 'z'
    );  

    $max = $length / 2;
    for ($i = 1; $i <= $max; $i++) {
        $string .= $consonants[rand(0, 19)];
        $string .= $vowels[rand(0, 4)];
    }

    return $string;
}

for ($i = 0; $i < 10000; $i++) {
    $randomFileName = readable_random_string(rand(3, 11));
    $randomFname = $randomFileName.".zip";
    $randomFPath = readable_random_string(rand(3, 11))."/".readable_random_string(rand(3, 11))."/";
    $randomUsr = 32+rand(0, 1)*3;
    $randomDesc = "";
    $category = rand(1, 7);
    $era = rand(1, 6);
    for ($z = 0; $z < rand(10, 150); $z++) {
        $randomDesc .= readable_random_string(rand(3, 11))." ";
    }
    $sql = $mysqli->prepare('INSERT INTO `package_list`(`file_name`, `display_name`, `category`, `era`, `country`, `version`, `owner`, `description`, `target_path`) VALUES(?, ?, ?, ?, -1, 1, ?, ?, ?);');
    $sql->bind_param('ssiiiss', $randomFname, $randomFileName, $category, $era, $randomUsr, $randomDesc, $randomFPath);
    if (!$sql->execute()) {
        print_r($mysqli->error);
    }
}

$mysqli->close();*/
die("Jupíjajéj skáza dokonána");