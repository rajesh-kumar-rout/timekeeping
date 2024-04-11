<?php

require ("config.php");

class DB
{
    private $db;

    private static $instance;

    private function __construct()
    {
        $this->db = new PDO(sprintf("mysql:host=%s;dbname=%s", DB_HOST, DB_NAME), DB_USERNAME, DB_PASSWORD);
    }

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new DB();
        }

        return self::$instance;
    }

    public function insert($table, $data)
    {
        $columns = implode(",", array_keys($data));

        $placeholders = implode(",", array_map(fn($key) => ":$key", array_keys($data)));

        $stmt = $this->db->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");

        foreach ($data as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        $stmt->execute();

        return $this->db->lastInsertId();
    }

    public function update($param)
    {
        $table = $param["table"];
        $data = $param["data"];
        $where = $param["where"] ?? null;
        $bindings = $param["bindings"] ?? [];

        $values = implode(",", array_map(fn($key) => "$key = :$key", array_keys($data)));

        $sql = "UPDATE $table SET $values";

        if ($where) {
            $sql .= " WHERE $where";
        }

        $stmt = $this->db->prepare($sql);

        $bindings += $data;

        foreach ($bindings as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    }

    public function delete($table, $where = null, $bindings = [])
    {
        $sql = "DELETE FROM $table";

        if ($where) {
            $sql .= " WHERE $where";
        }

        $stmt = $this->db->prepare($sql);

        foreach ($bindings as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    }

    public function findAll($sql, $bindings = [])
    {
        $stmt = $this->db->prepare($sql);

        foreach ($bindings as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($sql, $bindings = [])
    {
        $data = $this->findAll($sql, $bindings);

        return empty($data) ? null : $data[0];
    }

    public function rawUpdate($sql, $bindings = [])
    {
        $stmt = $this->db->prepare($sql);

        foreach ($bindings as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }

        return $stmt->execute();
    }
}

$db = DB::instance();

// echo $db->insert("employees", [
//     "name" => "ram",
//     "email" => "ram@gmail.com"
// ]);
echo $db->update([
    "table" => "employees",
    "data" => [
        "name" => "ravan"
    ],
    "where" => "id = :id",
    "bindings" => [
        "id" => 5
    ]
]);
// echo $db->rawUpdate("UPDATE employees SET name = 'ram' WHERE id = :id", [
//     "id" => 5
// ]);
// echo $db->delete("employees", "id = :id", [
//     "id" => 4
// ]);
// print_r($db->find("SELECT * FROM employees WHERE id = :id", [
//     "id" => 1
// ]));