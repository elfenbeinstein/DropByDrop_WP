<?php
// header files to make read/write possible from itchio
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

$baseDir = 'PlayerData/';

// Function to read JSON file with shared lock
function read_json($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }
    $file = fopen($filePath, 'r');
    if ($file === false) {
        return false;
    }
    if (flock($file, LOCK_SH)) { 
        $data = fread($file, filesize($filePath));
        flock($file, LOCK_UN);  
        fclose($file);
        return $data;
    } else {
        fclose($file);
        return false;
    }
}

// Function to write JSON with exclusive lock (will create file if none was found for player)
function write_json($filePath, $data) {
    $file = fopen($filePath, 'c+');
    if ($file === false) {
        return false;
    }
    $result = true;
    if (flock($file, LOCK_EX)) { 
        ftruncate($file, 0);
        fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
        fflush($file);
        flock($file, LOCK_UN);
    } else {
        $result = false;
    }
    fclose($file);
    return $result;
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['id'])) {
        $playerId = basename($_GET['id']);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $playerId)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid ID format"]);
            exit;
        }
        $filePath = $baseDir . $playerId . '.json';
        if (file_exists($filePath)){
            $data = read_json($filePath);
            if ($data !== false) {
                header('Content-Type: application/json');
                echo $data;
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Error reading file"]);
            }
        }else {
            http_response_code(404);
            echo json_encode(["error" => "File not found"]);
        } 
    } else {
        $files = glob($baseDir . '*.json'); // Get all JSON files in the directory
        $allPlayers  = [];
        foreach ($files as $file) {
            $fileData = read_json($file);
            if ($fileData !== false) {
                $decodedData = json_decode($fileData, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Assuming each file contains a JSON object representing PlayerData
                    if (isset($decodedData['PlayerId'])) {
                        $allPlayers[] = $decodedData;
                    }
                }
            }
        }
        $response = ['Players' => $allPlayers];
        header('Content-Type: application/json');
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
}

// Handle POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_GET['id'])) {
        $playerId = basename($_GET['id']);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $playerId)) {
            http_response_code(400);
            echo json_encode(["error" => "Invalid ID format"]);
            exit;
        }
        $filePath = $baseDir . $playerId . '.json';

        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (write_json($filePath, $data)) {
                header('Content-Type: application/json');
                echo json_encode(["success" => "Data successfully written to file"]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Error writing file"]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["error" => "Invalid JSON data"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Missing id"]);
    }
}
?>