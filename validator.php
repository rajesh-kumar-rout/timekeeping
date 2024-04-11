<?php

class Validator
{
    private function required($value, $param, $field, $message)
    {
        if ($param && $value === null) {
            throw new Exception($message ?? "The $field field is required");
        } else if(!$param && $value === null) {
            throw new Exception();
        }
    }

    private function type($value, $param, $field, $message)
    {
        if ($param === "string" && !is_string($value)) {
            throw new Exception($message ?? "The $field field must be a string");
        } else if ($param === "integer") {
            if (!is_integer($value)) {
                throw new Exception($message ?? "The $field field must be an integer");
            }
            $value = intval($value);
        } else if ($param === "array" && !is_array($value)) {
            throw new Exception($message ?? "The $field field must be an array");
        } else if ($param === "float" && !is_float($value)) {
            throw new Exception($message ?? "The $field field must be a decimal");
        }
    }

    private function min($value, $param, $field, $message)
    {
        if (is_string($value) && strlen($value) < $param) {
            throw new Exception($message ?? "The $field field should contain minimum $param characters");
        } else if (is_array($value) && count($value) < $param) {
            throw new Exception($message ?? "The $field field should contain minimum $param elements");
        } else if (is_numeric($value) && $value < $param) {
            throw new Exception($message ?? "The $field field should be greater than $param");
        }
    }

    private function max($value, $param, $field, $message)
    {
        if (is_string($value) && strlen($value) > $param) {
            throw new Exception($message ?? "The $field field should contain maximum $param characters");
        } else if (is_array($value) && count($value) > $param) {
            throw new Exception($message ?? "The $field field should contain maximum $param elements");
        } else if (is_numeric($value) && $value > $param) {
            throw new Exception($message ?? "The $field field should be lower than " . $param + 1);
        }
    }

    private function trim(&$value, $param)
    {
        if ($param === "left") {
            $value = ltrim($value);
        } else if ($param === "right") {
            $value = rtrim($value);
        } else {
            $value = trim($value);
        }
    }

    private function email($value, $param, $field, $message)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception($message ?? "The $field field should be a valid email address");
        }
    }

    private function field(&$value, $param, $field, $message)
    {
        $validator = new Validator();

        $errors = $validator->validate($value, [
            "schema" => $param["schema"],
            "messages" => $param["messages"] ?? []
        ]);

        if(!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
    }

    private function elements(&$value, $param, $field, $message)
    {
        $validator = new Validator();

        $schema = $param["rules"];
        $messages = $param["messages"] ?? [];

        $schema = array_map(function($item) use ($schema){
            return $schema;
        }, $value);

        $messages = array_map(function($item) use ($messages){
            return $messages;
        }, $value);

        $errors = $validator->validate($value, [
            "schema" => $schema,
            "messages" => $messages
        ]);

        if(!empty($errors)) {
            throw new Exception(json_encode($errors));
        }
    }

    public function validate(&$data, $config)
    {
        $schema = $config["schema"] ?? [];
        $messages = $config["messages"] ?? [];
        $errors = [];

        foreach ($schema as $field => $rules) {
            foreach ($rules as $rule => $param) {
                if (is_numeric($rule)) {
                    $rule = $param;
                    $param = null;
                }

                if (!method_exists($this, $rule)) {
                    throw new Exception("Invalid rule - $rule");
                }

                try {
                    $this->$rule($data[$field], $param, $field, $messages[$field][$rule] ?? null);
                    echo $data[$field] . "\n";
                } catch (Throwable $th) {
                    if ($th->getMessage()) {
                        $errors[$field] = $th->getMessage();
                    }
                    break;
                }
            }
        }

        return $errors;
    }
}

$validator = new Validator;

$data = [
    "email" => "             rajesh@gmail.com ",
    "student" => [
        "name" => "   ram  "
    ],
    "courses" => [
        "cdddddddddddddddddddddddddddddd",
        "    cpp"
    ],
    "students" => [
        [
            "name" => "   rajesh",
            "age" => 2333
        ],
        [
            "name" => "   rajesh",
            "age" => 23
        ],
    ]
];

print_r($validator->validate($data, [
    "schema" => [
        "email" => ["required" => 1, "type" => "string", "trim", "email"],
        "student" => ["required" => 1, "type" => "array", "min" => 1, "field" => [
            "schema" => [
                "name" => ["required" => 1, "type" => "string", "trim", "min" => 10]
            ],
            "messages" => [
                "name" => [
                    "min" => "Name must be 1-"
                ]
            ]
        ]],
        "courses" => ["required" => 1, "type" => "array", "elements" => [
            "rules" => ["required" => 1, "type" => "string", "trim", "min" => 11]
        ]],
        "students" => ["required" => 1, "type" => "array", "elements" => [
            "rules" => ["required" => 1, "type" => "array", "field" => [
                "schema" => [
                    "name" => ["required" => 1, "type" => "string", "trim", "min" => 2],
                    "age" => ["required" => 1, "type" => "integer", "min" => 2222],
                ]
            ]]
        ]]
    ]
]));

print_r($data);