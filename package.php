<?php
require "dls_db.php";

$package_name = "";
$package_desc = "";
$author = "";
$author_id = "";
$category = "";
$category_id = "";
$country = "";
$country_id = "";
$era = "";
$era_id = "";
$country = "";
$filename = "";
$version = "";
$datetime = "";
$target_path = "";
$steamappid = "";

$sql = $mysqli->prepare('SELECT `package_list`.`id`, `file_name`, `display_name`, `version`, `owner`, `datetime`, `description`, `target_path`, `steamappid`, `users`.`nickname` AS `author`, `package_list`.`category` AS `category_id`, `categories`.`text` AS `category`, `era` AS `era_id`, `eras`.`text` AS `era`, `package_list`.`country` AS `country_id`, `countries`.`text` AS `country` FROM `package_list` LEFT JOIN `users` ON `package_list`.`owner` = `users`.`id` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` LEFT JOIN `eras` ON `package_list`.`era` = `eras`.`id` LEFT JOIN `countries` ON `package_list`.`country` = `countries`.`id` WHERE `package_list`.`id` = ?;');
$sql->bind_param('i', $package_id);
$sql->execute();
$queryResult = $sql->get_result();

if (!empty($queryResult)) {
    if ($queryResult->num_rows > 0) {
        $row = $queryResult->fetch_assoc();
        $package_name = $row["display_name"];
        $package_desc = $row["description"];
        $author = $row["author"];
        $author_id = $row["owner"];
        $category = $row["category"];
        $category_id = $row["category_id"];
        $country = $row["country"];
        $country_id = $row["country_id"];
        $era = $row["era"];
        $era_id = $row["era_id"];
        $country = $row["country"];
        $filename = $row["file_name"];
        $version = $row["version"];
        $datetime = $row["datetime"];
        $target_path = $row["target_path"];
        $steamappid = $row["steamappid"];
    } else {
        $_SESSION["errorMessage"] = "No such package!";
        echo("<script> window.location.replace('.') </script>");
        die();
    }
} else {
    $_SESSION["errorMessage"] = "No such package!";
    echo("<script> window.location.replace('.') </script>");
    die();
}
?>
<script type="text/javascript">
    var savedDeps = <?php
    $rows = array();
    $sql = $mysqli->prepare('SELECT `package_list`.* FROM `dependency_list` LEFT JOIN `package_list` ON `dependency_list`.`dependency_package_id` = `package_list`.`id` WHERE `dependency_list`.`package_id` = ?;');
    $sql->bind_param("i", $package_id);
    if ($sql->execute()) {
        $queryResult = $sql->get_result();
    
        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                while ($row = $queryResult->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }
    }
    echo(json_encode($rows));
    ?>;
    var selectedDeps = [...savedDeps];
    var featuredDeps = new Array();
    var searchTimeout;

    $(document).ready(function(){
        $("#files-toggle").on('click', function(event) {
            $("#files").toggle("fast", "linear");
            if ($("#files-toggle").html().includes("-")) {
                $("#files-toggle").html("<h3>Files +</h3>");
            } else {
                $("#files-toggle").html("<h3>Files -</h3>");
            }
        });
        $("#deps-toggle").on('click', function(event) {
            $("#dependencies").toggle("fast", "linear");
            if ($("#deps-toggle").html().includes("-")) {
                $("#deps-toggle").html("<h3>Dependencies +</h3>");
            } else {
                $("#deps-toggle").html("<h3>Dependencies -</h3>");
            }
        });
        $("#dependenciesModal").on('hidden.bs.modal', function(event) {
            selectedDeps = [...savedDeps];
            buildChB('#selected', selectedDeps, 'selected');
        });
        $("#saveDeps").on('click', function(event) {
            saveDeps();
        });

        var search_dep = $("#searchPkg");

        function onChBClick(id, type) {
            if (type === "featured") {
                selectedDeps.push(featuredDeps.find((o) => { return o.id === id }));
            } else if (type === "selected") {
                selectedDeps = $.grep(selectedDeps, function(e){ 
                    return e.id != id;
                });
            }
            buildChB('#featured', featuredDeps, 'featured');
            buildChB('#selected', selectedDeps, 'selected');
        }

        function buildChB(id, data, type) {
            $(id).html("");
            jQuery.each(data, function(i, val) {
                if (type === "selected") {
                    $(id).append('<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="'+type+'ChB'+val.id+'" checked><label class="custom-control-label" for="'+type+'ChB'+val.id+'">'+val.display_name+' (<'+val.id+'> '+val.file_name+')</label></div>');
                    $('#'+type+'ChB'+val.id).on('click', function() {onChBClick(val.id, type)});
                } else if (selectedDeps.find((o) => { return o.id === val.id }) == null) {
                    $(id).append('<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" id="'+type+'ChB'+val.id+'"><label class="custom-control-label" for="'+type+'ChB'+val.id+'">'+val.display_name+' (<'+val.id+'> '+val.file_name+')</label></div>');
                    $('#'+type+'ChB'+val.id).on('click', function() {onChBClick(val.id, type)});
                }
            });
        }
        buildChB('#selected', selectedDeps, 'selected');

        function saveDeps() {
            var ids = [];
            selectedDeps.forEach(function(item) {
                ids.push(item.id);
            });
            //reqData.append("depends", JSON.stringify(ids));
            $('#fakeForm').html('<form action="api/update" name="update_deps" method="post" style="display:none;"><input type="text" name="depends" value="'+JSON.stringify(ids)+'" /><input type="number" name="package_id" value="<?php echo($package_id); ?>"/></form>');
            document.forms['update_deps'].submit();

            /*var reqData = new FormData();
            reqData.append("package_id", );

            var req = new XMLHttpRequest();
            req.responseType = 'json';
                
            req.onload = function(oEvent) {
                if (req.status == 200) {
                    var data = req.response;
                    resetProgress();
                    if (data.code < 0) {
                        $("#error-content").html(data.message);
                        $("#error").modal("show");
                        clearTimeout(errorTimeout);
                        errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                        selectedDeps = [...savedDeps];
                        buildChB('#selected', selectedDeps, 'selected');
                    } else {
                        window.location.replace('?package='+data.content.package_id);
                    }
                } else {
                    $("#error-content").html("Error "+req.status);
                    $("#error").modal("show");
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                    selectedDeps = [...savedDeps];
                    buildChB('#selected', selectedDeps, 'selected');
                }
            };

            req.open("POST", "api/upload.php", true);
            req.send(reqData);*/
        }

        function search() {
            var req = new XMLHttpRequest();
            req.responseType = 'json';
                
            req.onload = function(oEvent) {
                if (req.status == 200) {
                    var data = req.response;
                    if (data.code < 0) {
                        $("#search-error").html(data.message);
                        $("#search-error").fadeIn();
                        clearTimeout(errorTimeout);
                        errorTimeout = setTimeout(function(){$("#search-error").fadeOut();}, 5000);
                        featuredDeps = new Array();
                        buildChB('#featured', featuredDeps, 'featured');
                    } else {
                        featuredDeps = data.content;
                        /*featuredDeps = new Array();
                        jQuery.each(data.content, function(i, val) {
                            featuredDeps.push(val);
                        });*/
                        buildChB('#featured', featuredDeps, 'featured');
                    }
                } else {
                    $("#search-error").html("Error "+req.status);
                    $("#search-error").fadeIn();
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#search-error").fadeOut();}, 5000);
                    featuredDeps = new Array();
                    buildChB('#featured', featuredDeps, 'featured');
                }
            };

            req.open("GET", "api/query?limit=20&searchFor="+search_dep.val(), true);
            req.send();
        }

        search_dep.on('change paste keyup', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(search, 400);
        });
    });
</script>
<div class="container">
    <div class="card-body">
        <p><h1><?php echo($package_name);?></h1></p>
        <p><?php echo($package_desc)?></p>

        <?php 
        if (isset($_SESSION["logged"]) && $_SESSION["logged"] && (($author_id == $_SESSION["userid"] && $_SESSION["privileges"] > 0) || $_SESSION["privileges"] > 1)) {
            echo('<button type="button" class="btn btn-primary" data-toggle="modal" data-target="#editPackage">Edit package</button><button type="button" class="btn btn-primary" data-toggle="modal" data-target="#dependenciesModal">Edit dependencies</button>');
        }
        ?>
    </div>
    
    <div class="row">
        <div class="col-md-6">
        <table class="table">
            <tbody>
                <tr>
                    <th scope="row">Package ID:</th>
                    <td><?php echo($package_id)?></td>
                </tr>
                <tr>
                    <th scope="row">Author:</th>
                    <td><?php echo("<a href='?author=$author_id'>$author</a>")?></td>
                </tr>
                <tr>
                    <th scope="row">Category:</th>
                    <td><?php echo($category)?></td>
                </tr>
                <tr>
                    <th scope="row">Era:</th>
                    <td><?php echo($era)?></td>
                </tr>
                <tr>
                    <th scope="row">Country:</th>
                    <td><?php echo($country)?></td>
                </tr>
                <tr>
                    <th scope="row">Filename:</th>
                    <td><?php echo($filename)?></td>
                </tr>
                <tr>
                    <th scope="row">Version:</th>
                    <td><?php echo($version)?></td>
                </tr>
                <tr>
                    <th scope="row">Created:</th>
                    <td><?php echo($datetime)?></td>
                </tr>
                <tr>
                    <th scope="row">Target path:</th>
                    <td><?php echo($target_path)?></td>
                </tr>
            </tbody>
        </table>
        </div>
        <?php
        if (file_exists("files/images/$package_id.png") || ($steamappid != 0 && $package_name != "Unknown DLC placeholder")) {
            if (file_exists("files/images/$package_id.png")) {
                $image = "files/images/$package_id.png";
            } else {
                $image = "https://steamcdn-a.akamaihd.net/steam/apps/$steamappid/header.jpg";
            }
            ?>
            <div class="col-md-6">
                <img src=<?php echo($image); ?> width="770" class="img-fluid"></img>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
    $sql = $mysqli->prepare('SELECT `dependency_package_id`, `package_list`.`display_name`, `categories`.`text` AS `category`, `users`.`nickname`, `package_list`.`owner` FROM `dependency_list` LEFT JOIN `package_list` ON `dependency_list`.`dependency_package_id` = `package_list`.`id` LEFT JOIN `categories` ON `package_list`.`category` = `categories`.`id` LEFT JOIN `users` ON `package_list`.`owner` = `users`.`id` WHERE `package_id` = ?;');
    $sql->bind_param('i', $package_id);
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            ?>
            <div id="deps-toggle" style="padding: 1.25rem 0 0.25rem 1.25rem">
                <h3>Dependencies +</h3>
            </div>
            <div id="dependencies" style="display: none">       
                <table class="table table-hover" id="table" data-pagination="true" data-page-list="[25, 50, 100, all]" data-page-size="25">
                    <thead>
                        <tr>
                            <th scope="col" data-field="id">Package ID</th>
                            <th scope="col" data-field="display_name">Package name</th>
                            <th scope="col" data-field="author">Author</th>
                            <th scope="col" data-field="category">Category</th>
                        </tr>
                    </thead>
                    <tbody>
            <?php
            while ($row = $queryResult->fetch_assoc()) {
                echo("<tr><td><a href='?package=".$row["dependency_package_id"]."'>".$row["dependency_package_id"]."</a></td><td><a href='?package=".$row["dependency_package_id"]."'>".$row["display_name"]."</a></td><td><a href='?author=".$row["owner"]."'>".$row["nickname"]."</a></td><td>".$row["category"]."</td></tr>");
            }
            ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
    ?>

    <?php
    $sql = $mysqli->prepare('SELECT `fname` FROM `file_list` WHERE `package_id` = ? ORDER BY `fname`;');
    $sql->bind_param('i', $package_id);
    $sql->execute();
    $queryResult = $sql->get_result();

    if (!empty($queryResult)) {
        if ($queryResult->num_rows > 0) {
            ?>
    <div style="padding: 1.25rem 0 0.25rem 1.25rem" id="files-toggle">
        <h3>Files +</h3>
    </div>

    <div id="files" style="padding-bottom: 1.25rem; display: none">       
        <ul class="list-group list-group-flush">
            <?php
            while ($row = $queryResult->fetch_assoc()) {
                echo("<li class='list-group-item'>".$row["fname"]."</li>");
            }
            ?>
        </ul>
    </div>
            <?php
        }
    }
    ?>
</div>
<?php
if (isset($_SESSION["logged"]) && $_SESSION["logged"] && ($author_id == $_SESSION["userid"] || $_SESSION["privileges"] > 1)) {
?>
    <div class="modal" id="editPackage" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form class="update-form" autocomplete="off" action="/api/update" method="post">
                    <input name="package_id" type="hidden" value="<?php echo($package_id);?>"/>
                    <div class="modal-header">
                        <h5 class="modal-title">Edit package</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="packageName">Package name</label>
                            <input type="text" class="form-control" id="packageName" name="package_name" value="<?php echo($package_name);?>">
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select class="form-control" id="category" name="category" required>
                                <?php 
                                echo("<option value='$category_id' selected disabled>$category</option>");
                                
                                $sql = $mysqli->prepare('SELECT * FROM `categories`;');
                                $sql->execute();
                                $queryResult = $sql->get_result();

                                if (!empty($queryResult)) {
                                    while ($row = $queryResult->fetch_assoc()) {
                                        echo("<option value='".$row["id"]."'>".$row["text"]."</option>");
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select class="form-control" id="country" name="country" required>
                                <?php 
                                echo("<option value='$country_id' selected disabled>$country</option>");
                                
                                $sql = $mysqli->prepare('SELECT * FROM `countries`;');
                                $sql->execute();
                                $queryResult = $sql->get_result();

                                if (!empty($queryResult)) {
                                    while ($row = $queryResult->fetch_assoc()) {
                                        echo("<option value='".$row["id"]."'>".$row["text"]."</option>");
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group row">
                            <div class="col-md-6 form-group">
                                <label for="era">Era</label>
                                <select class="form-control" id="era" name="era" required>
                                <?php 
                                echo("<option value='$era_id' selected disabled>$era</option>");
                                
                                $sql = $mysqli->prepare('SELECT * FROM `eras`;');
                                $sql->execute();
                                $queryResult = $sql->get_result();

                                if (!empty($queryResult)) {
                                    while ($row = $queryResult->fetch_assoc()) {
                                        echo("<option value='".$row["id"]."'>".$row["text"]."</option>");
                                    }
                                }
                                ?>
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="version">Version</label>
                                <input type="text" class="form-control" id="version" value="<?php echo($version);?>" required disabled>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="targetPath">Target path where zip will be extracted</label>
                            <div class="input-group mb-2">
                                <div class="input-group-prepend">
                                    <div class="input-group-text">Assets/</div>
                                </div>
                                <input type="text" class="form-control" id="targetPath" name="target_path" value="<?php echo($target_path);?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="desc">Description</label>
                            <textarea class="form-control" id="desc" rows="3" name="description"><?php echo($package_desc);?></textarea>
                        </div>
                        <?php
                        if ($_SESSION["privileges"] > 1) {
                            ?>
                            <div class="form-group">
                                <label for="owner">Owner</label>
                                <select class="form-control" id="owner" name="owner" required>
                                    <?php
                                    $sql = $mysqli->prepare('SELECT `id`, `nickname` FROM `users` WHERE `valid_email` = 1 AND `activated` = 1 ORDER BY `nickname`;');
                                    $sql->bind_param('i', $package_id);
                                    $sql->execute();
                                    $queryResult = $sql->get_result();

                                    if (!empty($queryResult)) {
                                        if ($queryResult->num_rows > 0) {
                                            while ($row = $queryResult->fetch_assoc()) {
                                                echo('<option value="'.$row["id"].'" '.(($row["id"] == $author_id) ? "selected" : "").'>'.$row["nickname"].'</option>\n');
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}
?>
<div class="modal fade" tabindex="-1" id="dependenciesModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add dependencies</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <label for="searchPkg">Search package</label>
                        <input type="text" class="form-control" id="searchPkg" placeholder="Package name...">
                    </div>
                    <div class="error" id="search-error"></div>
                </form>
                <p><h5>Matches found</h5></p>
                <div id="featured">
                </div>
                <p><h5>Selected</h5></p>
                <div id="selected">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="saveDeps">Save dependencies</button>
            </div>
        </div>
    </div>
</div>
<div id="fakeForm"></div>