<script type="text/javascript">
    var onChangeTimer;
    $(document).ready(function(){
        function performQuery(){
            var query = 'api/query?keyword='+$("#keyword").val()+'&searchBy='+$("input[name='searchBy']:checked").val();
            if ($("#category").val()) {
                query += '&category='+$("#category").val();
            }
            if ($("#country").val()) {
                query += '&country='+$("#country").val();
            }
            if ($("#era").val()) {
                query += '&era='+$("#era").val();
            }
            $.get(query, function(data) {
                if (data.code < 0) {
                    $("#error-content").html(data.message);
                    $("#error").modal("show");
                    clearTimeout(errorTimeout);
                    errorTimeout = setTimeout(function(){$("#error").modal("hide");}, 5000);
                    $('#table').bootstrapTable('destroy');
                } else {
                    $('#table').bootstrapTable({data: data.content});
                    $('#table').bootstrapTable('load', data.content);
                }
            });
        }
        var delay = 400;
        $("#keyword").on('change paste keyup', function(){
            clearTimeout(onChangeTimer);
            onChangeTimer = setTimeout(performQuery, delay);
        });
        $("#category").on('change paste keyup', function(){
            clearTimeout(onChangeTimer);
            onChangeTimer = setTimeout(performQuery, delay);
        });
        $("#country").on('change paste keyup', function(){
            clearTimeout(onChangeTimer);
            onChangeTimer = setTimeout(performQuery, delay);
        });
        $("#era").on('change paste keyup', function(){
            clearTimeout(onChangeTimer);
            onChangeTimer = setTimeout(performQuery, delay);
        });
        $("#searchBy").on('change keyup', function(){
            clearTimeout(onChangeTimer);
            onChangeTimer = setTimeout(performQuery, delay);
        });
        performQuery();
    });
</script>
<div class="card-body">
    <p><h1>Welcome to RailWorks download station!</h1></p>
    <p>Below you can browse existing packages and directly download them to game through our application.</p>
</div>
<div>
    <form>
        <div class="form-row">
            <div class="col">
                <input type="text" class="form-control" placeholder="Text search" id="keyword">
            </div>
            <div class="col">
                <select class="form-control" placeholder="Category" id="category">
                    <option value="" selected disabled>Category</option>
                    <?php
                    require "dls_db.php";
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
            <div class="col">
                <select class="form-control" placeholder="Country" id="country">
                    <option value="" selected disabled>Country</option>
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
            <div class="col">
                <select class="form-control" placeholder="Era" id="era">
                    <option value="" selected disabled>Era</option>
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
        </div>
        <div class="form-row col" id="searchBy">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="0" name="searchBy" id="searchBy1" checked>
                <label class="form-check-label" for="searchBy1">By package name</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="1" name="searchBy" id="searchBy2">
                <label class="form-check-label" for="searchBy2">By author</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="2" name="searchBy" id="searchBy3">
                <label class="form-check-label" for="searchBy3">By description</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="3" name="searchBy" id="searchBy4">
                <label class="form-check-label" for="searchBy4">By ID</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" value="4" name="searchBy" id="searchBy5">
                <label class="form-check-label" for="searchBy5">By included file</label>
            </div>
        </div>
    </form>
</div>
<table class="table table-hover" id="table" data-pagination="true" data-page-list="[25, 50, 100, all]" data-page-size="25">
    <thead>
        <tr>
            <th scope="col" data-field="id">Package ID</th>
            <th scope="col" data-field="display_name">Package name</th>
            <th scope="col" data-field="author">Author</th>
            <th scope="col" data-field="category">Category</th>
            <th scope="col" data-field="era">Era</th>
        </tr>
    </thead>
    <!--<tbody>
        <tr>
            <th scope="row">1</th>
            <td>Testovací balíček 460</td>
            <td>JachyHm</td>
            <td>Locomotives</td>
            <td>V.</td>
            <td></td>
        </tr>
        <tr>
            <th scope="row">100071</th>
            <td>Testovací balíček 843</td>
            <td>Silprd</td>
            <td>Locomotives</td>
            <td>V.</td>
            <td></td>
        </tr>
        <tr class="table-error">
            <th scope="row">666</th>
            <td>Hrozná zbastlenina 742</td>
            <td>Daminátor3000</td>
            <td>Locomotives</td>
            <td>V.</td>
            <td></td>
        </tr>
    </tbody>-->
</table>

