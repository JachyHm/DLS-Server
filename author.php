<?php
require "dls_db.php";

function obfuscate_email($email)
{
    $em   = explode("@", $email);
    $name = implode('@', array_slice($em, 0, count($em)-1));
    $len  = floor(strlen($name)/2);

    return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);   
}

$author_name = "";
$email = "";
$date_registered = "";
$total_packages = 0;
$privileges = "";

$sql = $mysqli->prepare('SELECT `nickname`, `email`, `date_created`, `privileges` FROM `users` WHERE `id` = ?;');
$sql->bind_param('i', $author_id);
$sql->execute();
$queryResult = $sql->get_result();

if (!empty($queryResult)) {
    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        $author_name = $row["nickname"];
        $email = $row["email"];
        $date_registered = $row["date_created"];
        $privileges = $row["privileges"];
    } else {
        $_SESSION["errorMessage"] = "No such author!";
        echo("<script> window.location.replace('.') </script>");
        die();
    }
} else {
    $_SESSION["errorMessage"] = "No such author!";
    echo("<script> window.location.replace('.') </script>");
    die();
}

$sql = $mysqli->prepare('SELECT `package_list`.`id`, `display_name`, `categories`.`text` AS `category` FROM `package_list` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` WHERE `owner` = ?;');
$sql->bind_param('i', $author_id);
$sql->execute();
$queryResult = $sql->get_result();
if (!empty($queryResult)) {
    $total_packages = $queryResult->num_rows;
}
?>
<div class="container">
    <div class="card-body">
        <p><h1><?php echo($author_name);?></h1></p>
    </div>
    
    <div class="row flex">
    
        <div class="col-sm-auto">
            <img src=<?php echo("https://www.gravatar.com/avatar/".md5(strtolower(trim($email)))."?s=200&d=monsterid");?> class="profile-picture"></img>
        </div>
        <div class="col-md">
            <table class="table">
                <tbody>
                    <tr>
                        <th scope="row">Email address:</th>
                        <td>
                            <?php
                            if (isset($_SESSION["logged"]) && $_SESSION["logged"]) {
                                echo("<a href='mailto://$email'>$email</a>");
                            } else {
                                echo(obfuscate_email($email));
                            }?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Total packages:</th>
                        <td><?php echo($total_packages); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Privileges:</th>
                        <td><?php echo($privileges); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Date registered:</th>
                        <td><?php echo((new DateTime($date_registered))->format('d. m. Y'));; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
    </div>
    <?php
    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            ?>
            <div style="padding: 1.25rem 0 0.25rem 1.25rem">
                <h3>Packages</h3>
            </div>

            <div id="dependencies" style="padding-bottom: 1.25rem">       
                <table class="table table-hover" id="table" data-pagination="true" data-page-list="[25, 50, 100, all]" data-page-size="25">
                    <thead>
                        <tr>
                            <th scope="col" data-field="id">Package ID</th>
                            <th scope="col" data-field="display_name">Package name</th>
                            <th scope="col" data-field="category">Category</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        while ($row = $queryResult->fetch_assoc()) {
                            echo("<tr><td><a href='?package=".$row["id"]."'>".$row["id"]."</a></td><td><a href='?package=".$row["id"]."'>".$row["display_name"]."</a></td><td>".$row["category"]."</td></tr>");
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
    ?>
</div>
