<?php

if (!isset($index)) {
    header("Location: /?application");
    die();
}

$version_code = "AN ERROR OCCURED WHEN LOADING DATABASE";
$date_published = "";
$comment = "";
$filepath = "";

$sql = $mysqli->prepare('SELECT `version_name`, `deployed`, `comment`, `file_path` FROM `app_versions` ORDER BY `id` DESC LIMIT 1;');
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
<div class="card-body">
    <h1>Download Railworks download station client</h1>
    <p>
        <h5>Welcome to some kind of first open beta of our small but ambitious project.</h5><br>
        Please keep in mind that it's heavily under development and that this is kind of first open beta.<br>
        As of now there are only few Czech addons (mostly mine if I had to be honest), but I hope more and more content creators will use it and one day maybe it could cover whole TS community.<br>
        It's highly inspired by Trainz's Download Station (thus the name), so the main purpose is to kind of "determine" which file in route/scenario is included in which "package", and if it is available on server, to download and install it.<br>
        It even contains some Steam DLCs, but as Steam API isn't public and there's no way to obtain not even the DLCs list, or even files included in them, only "user reported" files are listed.
        Every start app checks for installed Steam DLC's, gets files included in them and reports them to server, so we can keep track of these files too.<br>
        In current version client app is very basic and can not do much except of displaying what's missing and eventually asking the user for downloading it if the package is available. Also the app does whole installation process, so the user does not have to.<br>
        If you want to download from our server through this app, you need to have account there, however you can use the app as an kind of RW_Tools replacement in purpose of displaying missing Assets in routes/scenarios even without user account.<br>
        Also the source code is fully opensource under MIT license and we welcome any bugfixes or improvements <a href="https://github.com/JachyHm/DLS-Server">in our GitHub repo</a>.
        If you find any error or the app wouldn't work at all, feel free to create new ticket there.<br>
    </p>
    <p>
        <div style="display: block; width: 75%; margin: 0 auto 2em auto"><img src="dls-screen.png" class="img-fluid"></div>
        The client itself is pretty much easy to understand, but in case of any uncertainties.
        Green rows are routes where every asset is available.
        Purple ones are ones where some assets are missing, but only in scenarios, and finally red ones are routes where some assets are missing in either route, or both route and scenario.<br>
        By double clicking any route, you can popup a window with assets used either in route itself or scenario with their availability<br>
        <div style="display: block; width: 75%; margin: 0 auto 2em auto"><img src="dls-screen2.png" class="img-fluid"></div>
    </p>
    <p>
        Also don't worry, the first start may take a while as it is crawling on every single file of every single route and scenario, but once these are loaded next starts should be a LOT faster.
        (btw. I hope everything should be fully in English, but some random error messages may show up in Czech, so feel free to create new issue with that too)<br>
    </p>
    <div class="text-center">
        <h3>Download section</h3>
        <p>Last version: <b><?php echo("$version_code </b>from $date_published");?><br>
        Version info: <?php echo($comment);?></p>
        <p><a href="<?php echo($filepath); ?>"><button type="button" class="btn btn-success">DOWNLOAD (Windows)</button></a>
        <a href="https://github.com/Zdendaki/RailworksDownloader"><button type="button" class="btn btn-success">SOURCE CODE (GitHub)</button></a></p>
        <p>Any other help with this application will be provided soon.</p>
    </div>
</div>
