<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function get_boundiary($times, $day)
{
    for ($i = 0; $i < count($times); $i++) {
        if ($times[$i]["day"] == $day) {
            $prev_index = $i - 1;
            $prev_time = $times[$prev_index == -1 ? count($times) - 1 : $prev_index];

            $next_index = $i + 1;
            $next_time = $times[$next_index == count($times) ? 0 : $next_index];

            $start_time = abs(strtotime("2024-01-01 {$prev_time["check_out"]}") - strtotime("2024-01-02 {$times[$i]["check_in"]}"));

            $end_time = abs(strtotime("2024-01-02 {$next_time["check_in"]}") - strtotime("2024-01-01 {$times[$i]["check_out"]}"));

            return [
                "start_time" => $start_time / 3600,
                "end_time" => $end_time / 3600,
                "check_in" => date("H:i", strtotime($times[$i]["check_in"])),
                "check_out" => date("H:i", strtotime($times[$i]["check_out"]))
            ];
        }
    }
    return null;
}

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
        FROM employee_schedule AS es 
        INNER JOIN schedule_times AS times ON times.schedule_id = es.schedule_id  
        WHERE 
            es.employee_id = $employee_id AND 
            es.`from` <= '" . date("Y-m-d", $i) . "' AND 
            es.`to` >= '" . date("Y-m-d", $i) . "' AND
            times.day = " . date("N", $i) . "  
        LIMIT 1
    ");

    if ($current_time == null) {
        $data[] = [
            "date" => date("Y-m-d", $i),
            "holiday" => true,
            "punches" => []
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
        FROM employee_schedule AS es 
        INNER JOIN schedule_times AS times ON times.schedule_id = es.schedule_id  
        WHERE 
            es.employee_id = $employee_id AND 
            es.`from` <= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " -1 day")) . "' AND 
            es.`to` >= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " -1 day")) . "' AND
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
        FROM employee_schedule AS es 
        INNER JOIN schedule_times AS times ON times.schedule_id = es.schedule_id  
        WHERE 
            es.employee_id = $employee_id AND 
            es.`from` <= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day")) . "' AND 
            es.`to` >= '" . date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day")) . "' AND
            times.day = " . date("N", strtotime(date("Y-m-d", $i) . " +1 day")) . "  
        LIMIT 1
    ");

    if ($prev_time == null) {
        $lb = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_in"]));

        $actual_in = date("Y-m-d H:i", strtotime("$lb"));

        $lb = date("Y-m-d H:i", strtotime("$lb -420 minute"));
    } else {
        if (strtotime($prev_time["check_out"]) >= strtotime($current_time["check_in"])) {
            $ct = abs(strtotime("2024-01-02 " . date("H:i", strtotime($current_time["check_in"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($prev_time["check_out"])))) / 3600;
            $ct = get_minute_from_hour($ct / 2);
        } else {
            $ct = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_in"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($prev_time["check_out"])))) / 3600;
            $ct = get_minute_from_hour($ct / 2);
        }

        $lb = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_in"]));

        $actual_in = date("Y-m-d H:i", strtotime("$lb"));

        $lb = date("Y-m-d H:i", strtotime("$lb -$ct minute"));
    }

    if ($next_time == null) {
        $ub = date("Y-m-d", $i) . " " . date("H:i", strtotime($current_time["check_out"]));

        $actual_out = date("Y-m-d H:i", strtotime("$ct"));

        $ub = date("Y-m-d H:i", strtotime("$ct +420 minute"));
    } else {
        if (strtotime($next_time["check_in"]) <= strtotime($current_time["check_out"])) {
            $ct = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_out"]))) -
                strtotime("2024-01-02 " . date("H:i", strtotime($next_time["check_in"])))) / 3600;

            $ct = get_minute_from_hour($ct / 2) - 1;
        } else {
            $ct = abs(strtotime("2024-01-01 " . date("H:i", strtotime($current_time["check_out"]))) -
                strtotime("2024-01-01 " . date("H:i", strtotime($next_time["check_in"])))) / 3600;

            $ct = get_minute_from_hour($ct / 2) - 1;
        }

        if (strtotime($current_time["check_in"]) >= strtotime($current_time["check_out"])) {
            $sm = date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day"));
        } else {
            $sm = date("Y-m-d", $i);
        }

        $ub = $sm . " " . date("H:i", strtotime($current_time["check_out"]));

        $actual_out = date("Y-m-d H:i", strtotime("$ub"));

        $ub = date("Y-m-d H:i", strtotime("$ub +$ct minute"));
    }

    $punches = find_all("SELECT * FROM punches WHERE employee_id = $employee_id AND `timestamp` >= '$lb' AND `timestamp` < '$ub'");

    $item = [
        "date" => date("Y-m-d", $i),
        "check_in" => date("h:i A", strtotime($current_time["check_in"])),
        "check_out" => date("h:i A", strtotime($current_time["check_out"])),
        "holiday" => false,
        "punches" => array_map(fn($value) => [
            "time" => date("Y-m-d h:i A", strtotime($value["timestamp"])),
            "type" => $value["type"]
        ], $punches),
        "punctuality" => []
    ];

    $time = strtotime($item["date"] . " " . $item["check_in"]);

    if (strtotime($current_time["check_in"]) >= strtotime($current_time["check_out"])) {
        $timeout = strtotime(date("Y-m-d", strtotime(date("Y-m-d", $i) . " +1 day")) . " " . $item["check_out"]);
    } else {
        $timeout = strtotime($item["date"] . " " . $item["check_out"]);
    }

    $item["punctuality"]["in"] = empty($item["punches"]) ? "" : ($time == strtotime($item["punches"][0]["time"]) ? "IN_TIME" : ($time > strtotime($item["punches"][0]["time"]) ? "EARLY" : "LATE"));

    $item["punctuality"]["out"] = empty($item["punches"]) ? "" : ($item["punches"][count($item["punches"]) - 1]["type"] == "IN" ? "" : ($timeout == strtotime($item["punches"][count($item["punches"]) - 1]["time"]) ? "IN_TIME" : ($timeout > strtotime($item["punches"][count($item["punches"]) - 1]["time"]) ? "EARLY" : "LATE")));

    if (!empty($item["punches"])) {
        if ($item["punches"][count($item["punches"]) - 1]["type"] !== "OUT" || count($item["punches"]) === 1) {
            $item["time_in_office"] = "";
        } else {
            $item["time_in_office"] = (strtotime($item["punches"][count($item["punches"]) - 1]["time"]) - strtotime($item["punches"][0]["time"])) / 60;

            if ($item["time_in_office"] % 60 !== 0) {
                $item["time_in_office"] = (intval($item["time_in_office"] / 60)) . " hr" . " " . ($item["time_in_office"] % 60) . " min";
            } else {
                $item["time_in_office"] = intval($item["time_in_office"] / 60) . " hr";
            }
        }
    } else {
        $item["time_in_office"] = "";
    }

    $item["ub"] = $ub;
    $item["lb"] = $lb;
    $item["actual_in"] = $actual_in;
    $item["actual_out"] = $actual_out;

    if (!empty($item["punches"])) {
        if (
            $item["punches"][count($item["punches"]) - 1]["type"] !== "OUT" && strtotime(date("Y-m-d H:i")) >=
            strtotime($ub)
        ) {
            $item["mispunch"] = true;
        } else {
            $item["mispunch"] = false;
        }

        $total = 0;

        for ($x = 0; $x < count($item["punches"]) - 1; $x++) {
            if ($item["punches"][$x]["type"] === "OUT" && $item["punches"][$x + 1]["type"] === "IN") {
                $total += (strtotime($item["punches"][$x + 1]["time"]) - strtotime($item["punches"][$x]["time"]));
            }
        }

        $tm = $total / 60;

        if ($tm % 60 !== 0) {
            $item["total_working_hour"] = intval($tm / 60) . " hr " . ($tm % 60) . " min";
        } else {
            $item["total_working_hour"] = intval($tm / 60) . " hr ";
        }
    } else {
        $item["total_working_hour"] = "";
    }


    $data[] = $item;
}

die_dump($data);