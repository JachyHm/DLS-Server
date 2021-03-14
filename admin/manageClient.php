<script type="text/javascript">
    $(document).ready(function(){		
        $('#file').on('change', function(event) {
			var fname = $('#file').val().split('\\').pop();
			if (fname.split('.').pop().toLowerCase() == 'exe') {
				$('#filelabel').html(fname);
			} else {
				$('#file').val("");
				$('#filelabel').html("Choose DLS executable");
				$("#error-content").html("Uploaded file must be .exe!");
				$("#error").modal("show");
				clearTimeout(errorTimeout);
				errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
			}
        });
		
        var form = document.forms.namedItem("form");
        form.addEventListener('submit', function(e) {
            e.preventDefault();
			
			if ($('#file').val().split('\\').pop().split('.').pop().toLowerCase() == 'exe') {
				var reqData = new FormData(form);

				var req = new XMLHttpRequest();
				req.responseType = 'json';

				req.upload.onprogress = function(event) {
					var percentComplete = (event.loaded/event.total)*100;
					$('#progress-bar').css('width', percentComplete+'%').attr('aria-valuenow', percentComplete).html(percentComplete.toFixed(2)+'%');
				}
					
				req.onload = function(oEvent) {
					if (req.status >= 200 && req.status <= 299) {
						var data = req.response;
						if (data.code < 200 || data.code > 299) {
							$("#error-content").html(data.message);
							$("#error").modal("show");
							clearTimeout(errorTimeout);
							errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
						} else {
							window.location.replace('?application');
						}
					} else {
						$('#progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
						$("#error-content").html("Error "+req.status);
						$("#error").modal("show");
						clearTimeout(errorTimeout);
						errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
					}
				};

				req.open("POST", "api/uploadClient.php", true);
				req.send(reqData);
			} else {
				$('#progress-bar').css('width', '0%').attr('aria-valuenow', 0).html();
				$("#error-content").html("Uploaded file must be *.exe!");
				$("#error").modal("show");
				clearTimeout(errorTimeout);
				errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
				event.stopPropagation();
			}
        });

	});
</script>
<div>
    <div class="card-body">
        <p></p><h1>Upload application update</h1>
        <p></p>
    </div>
    <form name="form" id="form" class="needs-validation" enctype="multipart/form-data" method="post" novalidate="">
        <div class="form-group row">
            <div class="col-md-6 form-group">
                <input type="text" placeholder="Version number..." class="form-control" id="version" name="version" required="">
            </div>
            <div class="col-md-6 form-group">
                <div class="custom-file">

                    <input type="file" class="custom-file-input" id="file" name="file" accept="application/vnd.microsoft.portable-executable" required="">
                    <label class="custom-file-label" for="file" id="filelabel">Choose DLS executable</label>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label for="desc">Description</label>
            <textarea class="form-control" id="desc" rows="3" name="description"></textarea>
        </div>
        <div class="form-group">
            <div class="progress">
                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%">
                </div>
            </div>
        </div>
        <div class="form-group">
            <button type="submit" class="btn btn-primary">Upload update</button>
        </div>
    </form>
</div>
