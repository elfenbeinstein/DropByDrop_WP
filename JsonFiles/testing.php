<?php
// Path to the JSON file
$file_path = 'data.json';

// Read JSON file
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file_path)) {
        $data = file_get_contents($file_path);
        header('Content-Type: application/json');
        echo $data;
    } else {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
    }
}

// Write to JSON file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
        echo json_encode(["success" => "Data successfully written to file"]);
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data"]);
    }
}
?>