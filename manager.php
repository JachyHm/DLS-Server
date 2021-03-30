<?php
if (!isset($_SESSION["logged"]) || !$_SESSION["logged"]) {
    $_SESSION["errorMessage"] = "You have to be logged in to upload packages!";
    echo("<script> window.location.replace('.') </script>");
    die();
}

if (!isset($_SESSION["privileges"]) || $_SESSION["privileges"] <= 0) {
    $_SESSION["errorMessage"] = "You don't have permitions to upload packages!";
    echo("<script> window.location.replace('.') </script>");
    die();
}
?>
<!-- https://cdnjs.cloudflare.com/ajax/libs/jszip/3.6.0/jszip.min.js -->
<script type="text/javascript">
    var selectedDeps = new Array();
    var featuredDeps = new Array();
    var searchTimeout;
    var localFiles = new Array();
    var serverFiles = new Array();
    var duplicatePackages = new Array();
    var validatedUpload = false;
    var mustBeUpdate = -1;
    var isUpdateChecked = false;
    var uploadPending = false;
    var targetPath = "";
    var availablePackages = [];
    var verificationFile = "";
    var extensionExp = /(?:\.([^.]+))?$/;

    var nameChanged = false;
    var categoryChanged = false;
    var countryChanged = false;
    var eraChanged = false;
    var descChanged = false;
    var targetPathChanged = false;

    $(document).ready(function(){
        updateButton();

        <?php
        if (isset($_GET["manager"]) && !empty($_GET["manager"])) {
            $package_id = $_GET["manager"];
            echo("fillValuesWithOriginal($package_id);");
        }
        ?>

        $('#actualisation option').each(function() { 
            availablePackages.push( $(this).attr('value') );
        });

        function resetProgress(){
            $('#progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
            return false;
        }

        function displayError(message) {
            $("#error-content").html(message);
            $("#error").modal("show");
            clearTimeout(errorTimeout);
            errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
        }

        function fillValuesWithOriginal(packageId, withTargetPath = false) {
            var req = new XMLHttpRequest();
            var reqData = new FormData();
            req.responseType = 'json';
            
            req.onload = function(oEvent) {
                if (req.status >= 200 && req.status <= 299) {
                    var data = req.response;
                    if (data.code >= 200 && data.code <= 299) {
                        updatedPackage = data.content;
                        if (!nameChanged) {
                            $("#packName").val(updatedPackage.display_name);
                        }
                        if (!categoryChanged) {
                            $("#category").val(updatedPackage.category);
                        }
                        if (!countryChanged) {
                            $("#country").val(updatedPackage.country);
                        }
                        if (!eraChanged) {
                            $("#era").val(updatedPackage.era);
                        }
                        $("#version").val(updatedPackage.version+1);
                        if (!descChanged) {
                            $("#desc").val(updatedPackage.description);
                        }
                        if (!targetPathChanged) {
                            $("#targetPath").val(updatedPackage.target_path);
                            cleanTargetPath();
                        }
                        $('#isUpdate').prop("checked", true);
                        onUpdateStateChanged();
                        //$('#isUpdate').prop("disabled", true);
                        $('#actualisation').show();
                        $('#actualisation').val(updatedPackage.id);
                    }
                }
                $('#actualisation').prop("disabled", false);
                updateButton();
            };

            reqData.append("id", packageId);

            req.open("POST", "api/query", true);
            req.send(reqData);
            $('#actualisation').prop("disabled", true);
        }

        function updateTargetPathHint() {
            if (!verificationFile) {
                $('#targetPathLabel').html("Target path where zip will be extracted");
            } else {
                $('#targetPathLabel').html("Target path where zip will be extracted, e.g. file \""+verificationFile+".xml\" will now be placed into \"Assets/"+targetPath+verificationFile+".xml\"");
            }
        }

        $('#packName').bind('input', function() {
            nameChanged = $(this).val();
        });

        $('#category').bind('input', function() {
            categoryChanged = $(this).val();
        });
        
        $('#country').bind('input', function() {
            countryChanged = $(this).val();
        });

        $('#era').bind('input', function() {
            eraChanged = $(this).val();
        });

        $('#description').bind('input', function() {
            descChanged = $(this).val();
        });

        $('#actualisation').bind('input', function() {
            fillValuesWithOriginal($('#actualisation').val(), true);
        });

        function onUpdateStateChanged() {
            if ($('#isUpdate').is(':checked')) {
                $('#actualisation').show();
                isUpdateChecked = true;
            } else {
                $('#actualisation').hide();
                isUpdateChecked = false;
            }
            updateButton();
        }

        $('#isUpdate').bind('input', function() {
            onUpdateStateChanged();
        });

        $('#file').bind('input', function() {
            validatedUpload = false;
            updateButton();
            var file = event.target.files[0];
            JSZip.loadAsync(file).then(function(content) {
                localFiles = [];
                for (const item in content.files) {
                    const ext = extensionExp.exec(item.toLowerCase())[1];
                    if (!content.files[item].dir && (ext == "xml" || ext == "bin")) {
                        localFiles.push(item.replace(/\.[^/.]+$/, "").toLowerCase());
                    }
                }
                verificationFile = localFiles[Math.floor(Math.random()*(localFiles.length-1))];
                updateTargetPathHint();
                if (localFiles.length == 0) {
                    displayError("Uploaded file does not contain any assets!");
                    $('#file').val("");
                    $('#fname').html("Choose file to upload");
                    return;
                }
                getServerFiles();
            }, function(e) {
                displayError("Your file either seems not to be zip file, or it is corrupted.");
                $('#file').val("");
                $('#fname').html("Choose file to upload");
            });
            $('#fname').html($('#file').val().split('\\').pop());
        });

        function cleanTargetPath() {
            targetPath = $('#targetPath').val().trim().replace(/\\/g, "/").toLowerCase();
            var lastChar = targetPath.substr(-1);
            if (lastChar !== '/' && targetPath.length > 0) {
                targetPath += '/';
            }
            var firstChar = targetPath.charAt(0);
            if (firstChar == '/') {
                targetPath = targetPath.substr(1);
            }
            updateTargetPathHint();
        }

        $('#targetPath').on('change', function(event) {
            cleanTargetPath();
            checkFiles();
        });

        $('#targetPath').bind('input', function() {
            targetPathChanged = $('#targetPath').val();
            cleanTargetPath();
        });

        $('#image').bind('input', function() {
            $('#imgname').html($('#image').val().split('\\').pop());
        });

        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false || !validatedUpload) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        var form = document.forms.namedItem("form");
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!uploadPending && validatedUpload) {
                var re = /^[^?%*:|"><.]+$/;
                //var target_path = $("#targetPath").val();
                if (re.test(targetPath)) {
                    if ($('#file').val().split('\\').pop().split('.').pop().toLowerCase() == 'zip') {
                        var reqData = new FormData(form);

                        if (!$('#isUpdate').is(':checked')) {
                            reqData.delete("actualisation");
                        }
                        //oData.append("token", "This is some extra data");
                        var ids = [];
                        selectedDeps.forEach(function(item) {
                            ids.push(item.id);
                        });
                        reqData.append("depends", JSON.stringify(ids));

                        var req = new XMLHttpRequest();
                        req.responseType = 'json';
                        req.onloadstart = function(){
                            resetProgress();
                            return true;
                        };

                        req.upload.onprogress = function(event) {
                            var percentComplete = (event.loaded/event.total)*100;
                            uploadPending = percentComplete < 100;
                            updateButton();
                            $('#progress-bar').css('width', percentComplete+'%').attr('aria-valuenow', percentComplete).html(percentComplete.toFixed(2)+'%');
                        }
                            
                        req.onload = function(oEvent) {
                            var data = req.response;
                            resetProgress();
                            if (req.status >= 200 && req.status <= 299) {
                                if (data.code < 200 || data.code > 299) {
                                    displayError(data.message);
                                } else {
                                    window.location.replace('?package='+data.content.package_id);
                                }
                            } else {
                                if (data != null) {
                                    displayError(data.message);
                                } else {
                                    displayError("Error "+req.status);
                                }
                            }
                            uploadPending = false;
                            updateButton();
                        };

                        req.open("POST", "api/upload.php", true);
                        req.send(reqData);
                        uploadPending = true;
                        updateButton();
                    } else {
                        displayError("Uploaded file must be *.zip or *.rwp!");
                        e.stopPropagation();
                    }
                } else {
                    displayError("Target path must be valid Windows folderpath from Assets folder!");
                    e.stopPropagation();
                }
            }
        });

        function updateButton() {
            $('#submitButton').prop("disabled", !validatedUpload || uploadPending || (mustBeUpdate >= 0 && (!isUpdateChecked || mustBeUpdate != $('#actualisation').val())));
        }

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

        function search() {
            var req = new XMLHttpRequest();
            req.responseType = 'json';
                
            req.onload = function(oEvent) {
                var data = req.response;
                if (req.status >= 200 && req.status <= 299) {
                    if (data.code < 200 || data.code > 299) {
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
                    if (data != null) {
                        $("#search-error").html(data.message);
                    } else {
                        $("#search-error").html("Error "+req.status);
                    }
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

        search_dep.bind('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(search, 400);
        });

        $('#updateModalYes').on('click', function(event) {
            fillValuesWithOriginal(duplicatePackages[0]);
        });
        
        $('#updateModalNo').on('click', function(event) {
            displayError("Unable to continue. Please remove duplicate files before proceeding!");
        });

        function checkFiles() {
            duplicatePackages = [];
            Object.entries(serverFiles).forEach(function(serverItem) {
                const [key, value] = serverItem;
                localFiles.forEach(function(localItem) {
                    if (key.toLowerCase() == targetPath + localItem && !duplicatePackages.includes(value)) {
                        duplicatePackages.push(value);
                    }
                });
            });
            if (duplicatePackages.length == 1 && availablePackages.includes(duplicatePackages[0])) {
                validatedUpload = true;
                mustBeUpdate = duplicatePackages[0];
                if (mustBeUpdate != $('#actualisation').val()) {
                    $("#updateModalContent").html("One or more files from this package are already included in package "+duplicatePackages[0]+"!<br>Is this an update of it?");
                    $("#updateModalTitle").html("Duplicate files found!");
                    $("#updateModal").modal("show");
                }
            } else if (duplicatePackages.length > 0) {
                displayError("Packages "+duplicatePackages.join(", ")+" already include files from this package!<br>Please remove them either from this package or from existing ones before proceeding.");
                mustBeUpdate = -1;
                validatedUpload = false;
                $('#file').val("");
                $('#fname').html("Choose file to upload");
            } else {
                mustBeUpdate = -1;
                validatedUpload = localFiles.length > 0;
            }
            updateButton();
        }

        function getServerFiles() {
            var req = new XMLHttpRequest();
            var reqData = new FormData();
            req.responseType = 'json';
            
            req.onload = function(oEvent) {
                if (req.status >= 200 && req.status <= 299) {
                    var data = req.response;
                    if (data.code >= 200 && data.code <= 299) {
                        serverFiles = data.content;
                        checkFiles();
                    }
                } else {
                    displayError("Unable to verify uploaded file!");
                }
            };

            reqData.append("validateUpload", JSON.stringify(localFiles));

            req.open("POST", "api/query", true);
            req.send(reqData);
        }
    });
</script>
<div class="container">
    <div class="card-body">
        <p><h1>Package manager</h1></p>
    </div>
    <form name="form" id="form" class="needs-validation" enctype="multipart/form-data"  method="post" novalidate>
        <div class="row flex">
            <div class="col-md-6 form-group">
                <label for="packName">Package name</label>
                <input type="text" class="form-control" id="packName" name="packageName" required>
            </div>
            <div class="col-md-6 form-group">
                <label for="category">Category</label>
                <select class="form-control" id="category" name="category" required>
                    <option value="" selected disabled>Please select...</option>
                    <?php
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
        </div>
        
        <div class="row flex">
            <div class="col-md-4 form-group">
                <label for="country">Country</label>
                <select class="form-control" id="country" name="country" required>
                    <option value="" selected disabled>Please select...</option>
                    <?php
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
            <div class="col-md-4 form-group">
                <label for="era">Era</label>
                <select class="form-control" id="era" name="era" required>
                    <option value="" selected disabled>Please select...</option>
                    <?php
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
            <div class="col-md-4 form-group">
                <label for="version">Version</label>
                <input type="text" class="form-control" id="version" name="version" value="1" required disabled>
            </div>
        </div>
        <div class="form-group">
            <label id="targetPathLabel" for="targetPath">Target path where zip will be extracted</label>
            <div class="input-group mb-2">
                <div class="input-group-prepend">
                    <div class="input-group-text">Assets/</div>
                </div>
                <input type="text" class="form-control" id="targetPath" name="targetPath">
            </div>
        </div>
        <div class="form-group">
            <label for="desc">Description</label>
            <textarea class="form-control" id="desc" rows="3" name="description"></textarea>
        </div>
        <div class="form-group">
            <div class="custom-file">
                <!--<input type="file" class="form-control-file" id="file">-->
                <input type="file" class="custom-file-input" id="file" name="file" required>
                <label class="custom-file-label" for="file" id="fname" required>Choose file to upload</label>
            </div>
        </div>
        <div class="form-group">
            <div class="custom-file">
                <input type="file" class="custom-file-input" id="image" name="image" accept="image/png" />
                <label class="custom-file-label" for="image" id="imgname" required>Choose image to upload as package profile photo</label>
            </div>
        </div>
        <div class="form-group">
            <div class="progress">
                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%"></div>
            </div>
        </div>
        <div class="row flex custom-control custom-switch">
            <div class="col-md-4 form-group">
                <input type="checkbox" class="custom-control-input" id="isUpdate">
                <label class="custom-control-label" for="isUpdate">This is an update of existing package</label>
            </div>
            <div class="col-md-8 form-group">
                <select class="form-control" id="actualisation" name="actualisation" required style="display: none;">
                    <option selected disabled>Which package are you updating?</option>
                    <?php
                    if ($_SESSION["privileges"] > 1) {
                        $sql = $mysqli->prepare('SELECT `id`, `display_name` FROM `package_list` WHERE `category` < 8 ORDER BY `display_name`;');
                    } else {
                        $sql = $mysqli->prepare('SELECT `id`, `display_name` FROM `package_list` WHERE `owner` = ? ORDER BY `display_name`;');
                        $sql->bind_param('i', $_SESSION["userid"]);
                    }
                    $sql->execute();
                    $queryResult = $sql->get_result();

                    if (!empty($queryResult)) {
                        while ($row = $queryResult->fetch_assoc()) {
                            echo("<option value='".$row["id"]."'>".$row["display_name"]."</option>\n");
                        }
                    }
                    ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#dependenciesModal">Add dependencies</button>
            <button type="submit" class="btn btn-primary" id="submitButton">Upload file</button>
        </div>
    </form>
</div>

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
                <button type="button" class="btn btn-primary" data-dismiss="modal">Save dependencies</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" tabindex="-1" id="updateModal">
    <div class="modal-dialog ">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="updateModalTitle">Duplicate files found!</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <p id="updateModalContent">Nope, nebudeš nahrávat.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal" id="updateModalYes">Yes</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="updateModalNo">No</button>
            </div>
        </div>
    </div>
</div>