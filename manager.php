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

<script type="text/javascript">
    var selectedDeps = new Array();
    var featuredDeps = new Array();
    var searchTimeout;

    $(document).ready(function(){
        function resetProgress(){
            $('#progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
            return false;
        }

        $('#isUpdate').on('change', function(event){
            if ($('#isUpdate').is(':checked')) {
                $('#actualisation').show();
            } else {
                $('#actualisation').hide();
            }
        });

        $('#file').on('change', function(event) {
            $('#fname').html($('#file').val().split('\\').pop());
            checkFilename($('#file').val().split('\\').pop());
        });

        $('#image').on('change', function(event) {
            $('#imgname').html($('#image').val().split('\\').pop());
        });

        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });

        var form = document.forms.namedItem("form");
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            var re = /^[^?%*:|"><.]+$/;
            var target_path = $("#targetPath").val();
            if (re.test(target_path)) {
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
                        $('#progress-bar').css('width', percentComplete+'%').attr('aria-valuenow', percentComplete).html(percentComplete.toFixed(2)+'%');
                    }
                        
                    req.onload = function(oEvent) {
                        if (req.status >= 200 && req.status <= 299) {
                            var data = req.response;
                            resetProgress();
                            if (data.code < 200 || data.code > 299) {
                                $("#error-content").html(data.message);
                                $("#error").modal("show");
                                clearTimeout(errorTimeout);
                                errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                            } else {
                                window.location.replace('?package='+data.content.package_id);
                            }
                        } else {
                            $("#error-content").html("Error "+req.status);
                            $("#error").modal("show");
                            clearTimeout(errorTimeout);
                            errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                        }
                    };

                    req.open("POST", "api/upload.php", true);
                    req.send(reqData);
                } else {
                    $("#error-content").html("Uploaded file must be *.zip!");
                    $("#error").modal("show");
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                    event.stopPropagation();
                }
            } else {
                $("#error-content").html("Target path must be valid Windows folderpath from Assets folder!");
                $("#error").modal("show");
                clearTimeout(errorTimeout);
                errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                event.stopPropagation();
            }
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

        function search() {
            var req = new XMLHttpRequest();
            req.responseType = 'json';
                
            req.onload = function(oEvent) {
                if (req.status >= 200 && req.status <= 299) {
                    var data = req.response;
                    resetProgress();
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

        function checkFilename(fname) {
            var req = new XMLHttpRequest();
            req.responseType = 'json';
            
            req.onload = function(oEvent) {
                if (req.status >= 200 && req.status <= 299) {
                    var data = req.response;
                    if (data.code > 200 && data.code < 299) {
                        if ($('#isUpdate').is(':checked')) {
                            $('#actualisation').val(data.content.id);
                        } else {
                            $("#updatePkg").modal('show');
                            $("#update-yes").on('click', function() {
                                $('#isUpdate').attr('checked','');
                                $('#actualisation').val(data.content.id);
                                $('#actualisation').show();
                            });
                            $("#update-no").on('click', function() {
                                $('#file').val("");
                                $('#fname').html("Choose file to upload");
                            })
                            $("#update-close").on('click', function() {
                                $('#file').val("");
                                $('#fname').html("Choose file to upload");
                            })
                        }
                    }
                }
            };

            req.open("GET", "api/query?packageFile="+fname, true);
            req.send();
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
            <label for="targetPath">Target path where zip will be extracted</label>
            <div class="input-group mb-2">
                <div class="input-group-prepend">
                    <div class="input-group-text">Assets/</div>
                </div>
                <input type="text" class="form-control" id="targetPath" name="targetPath" required>
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
            <div class="col-md-2 form-group">
                <input type="checkbox" class="custom-control-input" id="isUpdate">
                <label class="custom-control-label" for="isUpdate">File update</label>
            </div>
            <div class="col-md-10 form-group">
                <select class="form-control" id="actualisation" name="actualisation" required style="display: none;">
                    <option selected disabled>Which package are you updating?</option>
                    <?php
                    $sql = $mysqli->prepare('SELECT `id`, `display_name` FROM `package_list` WHERE `owner` = ? ORDER BY `display_name`;');
                    $sql->bind_param('i', $_SESSION["userid"]);
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
            <button type="submit" class="btn btn-primary">Upload file</button>
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

<div class="modal fade" data-backdrop="static" aria-hidden="true" id="updatePkg" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Rewrite package?</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="update-close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>Such package already exists. Is this an update?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-dismiss="modal" id="update-yes">Yes</button>
        <button type="button" class="btn btn-secondary" data-dismiss="modal" id="update-no">No</button>
      </div>
    </div>
  </div>
</div>