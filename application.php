<?php
require "dls_db.php";

$version_code = "AN ERROR OCCURED WHEN LOADING DATABASE";
$date_published = "";
$comment = "";
$filepath = "";

$sql = $mysqli->prepare('SELECT `version_name`, `deployed`, `comment`, `file_path` FROM `app_versions` ORDER BY `id` DESC LIMIT 1;');
$sql->bind_param('i', $author_id);
$sql->execute();
$queryResult = $sql->get_result();

if (!empty($queryResult)) {
    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        $version_code = $row["version_name"];
        $date_published = $row["deployed"];
        $comment = $row["comment"];
        $filepath = $row["file_path"];
    }
}
?>
<div class="card-body text-center">
    <h1>Download Railworks download station client</h1>
    <p>
        We are proud to introduce you Railworks download station client. With this application you can download your Train Simulator 2020 missing assets or download completely new ones. 
    </p>
    <div style="display: block; width: 75%; margin: 0 auto 2em auto"><img src="dls-screen.png" class="img-fluid"></div>

    <h3>Download section</h3>
    <p>Last version: <b><?php echo("$version_code </b>from $date_published");?><br>
    Version info: <?php echo($comment);?></p>
    <p><a href="<?php echo($filepath); ?>"><button type="button" class="btn btn-success">DOWNLOAD (Windows)</button></a>
    <a href="https://github.com/Zdendaki/RailworksDownloader"><button type="button" class="btn btn-success">SOURCE CODE (GitHub)</button></a></p>
    <p>Any other help with this application will be provided soon.</p>
</div>
