<div>
    <div class="card-body">
        <p></p><h1>Author requests</h1>
        <p></p>
    </div>
    <table class="table table-hover" id="table" data-pagination="true" data-page-list="[25, 50, 100, all]" data-page-size="25">
        <thead>
            <tr>
                <th scope="col">User name
                </th>
                <th scope="col">Real name
                </th>
                <th scope="col">Request date
                </th>
                <th scope="col">Status
                </th>
                <th scope="col">Action
                </th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sql = $mysqli->prepare('SELECT `become_author_requests`.`id` AS `request_id`, `user_id`, `real_name`, `datetime`, `closed`, `success`, `nickname`, `users`.`id`, `email` FROM `become_author_requests` LEFT JOIN `users` ON `become_author_requests`.`user_id` = `users`.`id` ORDER BY `closed`;');
        $sql->execute();
        $queryResult = $sql->get_result();

        if (!empty($queryResult)) {
            if ($queryResult->num_rows > 0) {
                while ($row = $queryResult->fetch_assoc()) { //<th>${row["request_id"]}</th>
                    echo("<tr><th><a href=\"/?author=${row["id"]}\">${row["nickname"]}</a></th><td><a href=\"mailto://${row["email"]}\">${row["real_name"]}</a></td><td>${row["datetime"]}</td><td>".(($row['closed'])?(($row['success'])?'Approved':'Declined'):'Open')."</td><td><a href=\"?admin=requests&reqId=${row["request_id"]}\"><button type='button' class='btn btn-primary'>View</button></a></td></tr>");
                }
            }
        }
        ?>
        </tbody>
    </table>
</div>
