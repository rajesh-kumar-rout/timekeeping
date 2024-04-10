<?php require ("../includes/header.php") ?>
<div class="row">
    <div class="col-3">
        <div class="list-group">
            <a href="/admin/schedules.php" class="list-group-item list-group-item-action active">Schedules</a>
            <a href="/admin/employees.php" class="list-group-item list-group-item-action">Employees</a>
            <a href="/auth/logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
    </div>

    <div class="col-9">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>10:00 AM - 07:00 PM (Mon - Fri)</td>
                        <td>10/09/2022 10:00 PM</td>
                    </tr>
                    <tr>
                        <td>1</td>
                        <td>10:00 AM - 07:00 PM (Mon - Fri)</td>
                        <td>10/09/2022 10:00 PM</td>
                        <td>10:00 AM - 07:00 PM (Mon - Sat)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require ("../includes/footer.php") ?>