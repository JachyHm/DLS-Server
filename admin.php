<?php
if (!isset($index)) {
    header("Location: /?application");
    die();
}

if (!isset($_SESSION["logged"]) || !$_SESSION["logged"]) {
    $_SESSION["errorMessage"] = "You have to be logged in to manage server!";
    echo("<script> window.location.replace('.') </script>");
    die();
}

if (!isset($_SESSION["privileges"]) || $_SESSION["privileges"] <= 1) {
    $_SESSION["errorMessage"] = "You don't have permitions to manage server!";
    echo("<script> window.location.replace('.') </script>");
    die();
}

if ($_GET["admin"] == "manageClient" && (!isset($_SESSION["privileges"]) || $_SESSION["privileges"] <= 2)) {
    $_SESSION["errorMessage"] = "You don't have permitions to upload new client version!";
    echo("<script> window.location.replace('./?admin') </script>");
    die();
}
?>

<div class="container">
    <div class="row">
        <div class="col-4 card-body">
            <div id="list-example" class="list-group">
                <a class="list-group-item" href="?admin=users">Manage users</a>
                <a class="list-group-item" href="?admin=requests">Manage author requests</a>
                <a class="list-group-item" href="?admin=manageClient">Manage client versions</a>
            </div>
        </div>
        <div class="col-8">
        <?php
        $switch = $_GET["admin"];
        if ($switch == "manageClient") {
            include "admin/manageClient.php";
        } else if ($switch == "requests") {
            if (isset($_GET["reqId"])) {
                $request_id = $_GET["reqId"];
                include "admin/request.php";
            } else {
                include "admin/requestsList.php";
            }
        } else {
            include "admin/users.php";
        }
        ?>
        </div>
    </div>
</div>
