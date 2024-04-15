<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function get_minute_from_hour($hour)
{
    $hour = strval($hour);

    if (str_contains($hour, ".")) {
        $hour = explode(".", $hour);
        return $minute = (intval($hour[0]) * 60) + 30;
    }

    return intval($hour) * 60;
}

function find_all($sql, $bindings = [])
{
    global $db;

    $stmt = $db->prepare($sql);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function find_one($sql, $bindings = [])
{
    global $db;

    $stmt = $db->prepare($sql);

    foreach ($bindings as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }

    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function die_dump($data)
{
    echo ("<pre>");
    print_r($data);
    die("</pre>");
}

function get_time_from_sec($sec)
{
    $min = intval($sec / 60);

    $hr = intval($min / 60);

    $min = $min % 60;

    if($hr > 0 && $min > 0) {
        return "$hr hour $min minute";
    } else if($hr > 0) {
        return "$hr hour";
    } else if($min > 0) {
        return "$min minute";
    } else {
        return "NA";
    }
}

$db = new PDO("mysql:host=localhost;dbname=timekeeping", "root", "");

$employee_id = 1;
$from = "2024-01-01";
$to = "2024-01-31";

$from = strtotime($from);
$to = strtotime($to);

$data = [];

for ($i = $from; $i <= $to; $i = strtotime(date("Y-m-d H:i:s", $i) . " +1 day")) {
    $current_time = find_one("
        SELECT 
            times.id,
            times.check_in, 
            times.check_out, 
            times.lunch_in, 
            times.lunch_out
        FROM employee_schedule AS schedule 
        INNER JOIN schedule_times AS times ON times.schedule_id = schedule.schedule_id  
        WHERE 
            schedule.employee_id = $employee_id AND 
            schedule.`from` <= '" . date("Y-m-d", $i) . "' AND 
            schedule.`to` >= '" . date("Y-m-d", $i) . "' AND
            times.day = " . date("N", $i) . "  
        LIMIT 1
    ");

    // returning with empty data if no scedule is found for current data
    if ($current_time == null) {
        $data[] = [
            "date" => date("Y-m-d", $i),
            "holiday" => true,
            "lb" => "",
            "up" => "",
            "actual_in" => "",
            "actual_out" => "",
            "punches" => [],
            "office_time" => "",
            "total_break" => "",
            "working_hour" => "",
            "mispunch" => "",
            "punctuality" => [
                "in" => "",
                "out" => ""
            ]
        ];
        continue;
    }

    $prev_time = find_one("
        SELECT 
            times.id,
            times.check_in, 
            times.check_out, 
            times.lunch_in, 
            times.lunch_out
        FROM employee_schedule AS schedule 
        INNER JOIN schedule_times AS times ON times.schedule_id = schedule.schedule_id  
        WHERE 
            schedule.employee_id = $employee_id AND 
            schedule.`from` <= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " -1 day")) . "' AND 
            schedule.`to` >= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " -1 day")) . "' AND
            times.day = " . date("N", strtotime(date("Y-m-d", $i) . " -1 day")) . "  
        LIMIT 1
    ");

    $next_time = find_one("
        SELECT 
            times.id,
            times.check_in, 
            times.check_out, 
            times.lunch_in, 
            times.lunch_out
        FROM employee_schedule AS schedule 
        INNER JOIN schedule_times AS times ON times.schedule_id = schedule.schedule_id  
        WHERE 
            schedule.employee_id = $employee_id AND 
            schedule.`from` <= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day")) . "' AND 
            schedule.`to` >= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day")) . "' AND
            times.day = " . date("N", strtotime(date("Y-m-d", $i) . " +1 day")) . "  
        LIMIT 1
    ");

    // calculating lower bound
    if ($prev_time == null) {
        $actual_in = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_in"]));

        $lb = date("Y-m-d H:i", strtotime("$actual_in -420 minute"));
    } else {
        if (strtotime($prev_time["check_out"]) >= strtotime($current_time["check_in"])) {
            $gap = abs(strtotime("2024-01-02 " . date("H:i", strtotime($current_time["check_in"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($prev_time["check_out"])))) / 3600;
            $gap = get_minute_from_hour($gap / 2);
        } else {
            $gap = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_in"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($prev_time["check_out"])))) / 3600;
            $gap = get_minute_from_hour($gap / 2);
        }

        $actual_in = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_in"]));

        $lb = date("Y-m-d H:i", strtotime("$actual_in -$gap minute"));
    }

    // calculating upper bound
    if ($next_time == null) {
        $actual_out = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_out"]));

        $ub = date("Y-m-d H:i", strtotime("$actual_out +420 minute"));
    } else {
        if (strtotime($next_time["check_in"]) <= strtotime($current_time["check_out"])) {
            $gap = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_out"]))) -
                strtotime("2024-01-02 " . date("H:i", strtotime($next_time["check_in"])))) / 3600;

            $gap = get_minute_from_hour($gap / 2) - 1;
        } else {
            $gap = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_out"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($next_time["check_in"])))) / 3600;

            $gap = get_minute_from_hour($gap / 2) - 1;
        }

        if (strtotime($current_time["check_in"]) >= strtotime($current_time["check_out"])) {
            $checkout_date = date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day"));
        } else {
            $checkout_date = date("Y-m-d", $i);
        }

        $actual_out = $checkout_date . " " . date("H:i", strtotime($current_time["check_out"]));

        $ub = date("Y-m-d H:i", strtotime("$actual_out +$gap minute"));
    }

    // retriving punches from db
    $punches = find_all("SELECT * FROM punches WHERE employee_id = $employee_id AND `timestamp` >= '$lb' AND `timestamp` < '$ub'");

    // returning with empty data if no punches found
    if (empty($punches)) {
        $data[] = [
            "date" => date("Y-m-d", $i),
            "holiday" => false,
            "lb" => $lb,
            "up" => $ub,
            "actual_in" => $actual_in,
            "actual_out" => $actual_out,
            "punches" => [],
            "office_time" => "",
            "total_break" => "",
            "working_hour" => "",
            "mispunch" => "",
            "punctuality" => [
                "in" => "",
                "out" => ""
            ]
        ];
        continue;
    }

    // formatting punches array
    $punches = array_map(fn($value) => [
        "time" => date("Y-m-d h:i A", strtotime($value["timestamp"])),
        "type" => $value["type"]
    ], $punches);

    // calculating in punctuality
    if (strtotime($punches[0]["time"]) >= strtotime($actual_in . " +15 minute")) {
        $in_punctuality = "LATE";
    } else if (strtotime($punches[0]["time"]) <= strtotime($actual_in . " -15 minute")) {
        $in_punctuality = "EARLY";
    } else {
        $in_punctuality = "IN-TIME";
    }

    // calculating out punctuality
    $last_punch = $punches[count($punches) - 1];

    if ($last_punch["type"] === "OUT") {
        if (strtotime($last_punch["time"]) >= strtotime($actual_out . " +15 minute")) {
            $out_punctuality = "LATE";
        } else if (strtotime($last_punch["time"]) <= strtotime($actual_out . " -15 minute")) {
            $out_punctuality = "EARLY";
        } else {
            $out_punctuality = "IN-TIME";
        }
    } else {
        $out_punctuality = "NA";
    }

    if ($last_punch["type"] === "OUT") {
        $office_time = strtotime($last_punch["time"]) - strtotime($punches[0]["time"]);
    } else {
        $office_time = 0;
    }

    // calculating mispunch and total break
    $total_break = 0;
    $mispunch = false;

    for ($x = 0; $x < count($punches) - 1; $x++) {
        if ($punches[$x]["type"] === "OUT" && $punches[$x + 1]["type"] === "IN") {
            $total_break += (strtotime($punches[$x + 1]["time"]) - strtotime($punches[$x]["time"]));
        }
        if ($punches[$x]["type"] === $punches[$x + 1]["type"]) {
            $mispunch = true;
        }
    }

    // calculating total working hour
    $working_hour = $office_time - $total_break;

    // calculating mispunch
    if ($last_punch["type"] !== "OUT" && strtotime(date("Y-m-d H:i")) >= strtotime($ub)) {
        $mispunch = true;
    }

    $data[] = [
        "date" => date("Y-m-d", $i),
        "lb" => $lb,
        "up" => $ub,
        "actual_in" => $actual_in,
        "actual_out" => $actual_out,
        "holiday" => false,
        "punches" => $punches,
        "office_time" => get_time_from_sec($office_time),
        "total_break" => get_time_from_sec($total_break),
        "working_hour" => get_time_from_sec($working_hour),
        "mispunch" => $mispunch,
        "punctuality" => [
            "in" => $in_punctuality,
            "out" => $out_punctuality
        ]
    ];
}

// die_dump($data);

?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <div class="container">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Actual In</th>
                    <th>Actual Out</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Office Time</th>
                    <th>Total Break</th>
                    <th>Working Hour</th>
                    <th>Mispunch</th>
                    <th>In Punctuality</th>
                    <th>Out Punctuality</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $item): ?>
                    <tr>
                        <td><?= date("Y-m-d", strtotime($item["date"])) ?></td>
                        <?php if (!empty($item["punches"])): ?>
                            <td><?= date("h:i A", strtotime($item["actual_in"])) ?></td>
                            <td><?= date("h:i A", strtotime($item["actual_out"])) ?></td>
                            <td><?= date("h:i A", strtotime($item["punches"][0]["time"])) ?? null ?></td>
                            <td><?= $item["punches"][count($item["punches"]) - 1]["type"] === "OUT" ? 
                            date("h:i A", strtotime($item["punches"][count($item["punches"]) - 1]["time"])) : "NA" ?>
                            </td>
                            <td><?= $item["office_time"] ?></td>
                            <td><?= $item["total_break"] ?></td>
                            <td><?= $item["working_hour"] ?></td>
                            <td><?= $item["mispunch"] ? "Yes" : "No" ?></td>
                            <td><?= $item["punctuality"]["in"] ?></td>
                            <td><?= $item["punctuality"]["out"] ?></td>
                        <?php else: ?>
                            <td colspan="10"></td>
                        <?php endif ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>