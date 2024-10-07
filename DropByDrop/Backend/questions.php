<?php
// header files to make read/write possible from itchio
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

$file_path = 'Questions.json';

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

// Function to write to JSON file with exclusive lock
function write_json($filePath, $data) {
    $file = fopen($filePath, 'c');
    if ($file === false) {
        return false;
    }

    if (flock($file, LOCK_EX)) {  
        ftruncate($file, 0);   
        fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
        fflush($file);            
        flock($file, LOCK_UN);    
        fclose($file);
        return true;
    } else {
        fclose($file);
        return false;
    }
}

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($file_path)) {
        $data = read_json($file_path);
        if ($data !== false) {
            if (isset($_GET['ids'])){
                $ids = explode(',', $_GET['ids']); // Expecting comma-separated IDs
                $ids = array_map('intval', $ids); // Convert to integers

                $decodedData = json_decode($data, true);
                $filteredQuestions = ['Questions' => []];

                // Filter Questions
                foreach ($decodedData['Questions'] as $question) {
                    if (in_array($question['ID'], $ids)) {
                        $filteredQuestions['Questions'][] = $question;
                    }
                }

                // Send the filtered questions as JSON response
                header('Content-Type: application/json');
                echo json_encode($filteredQuestions);

            } else {
                header('Content-Type: application/json');
                echo $data;
            }
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error reading file"]);
        }
    } else {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
    } 
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        if (write_json($file_path, $data)){
            header('Content-Type: application/json');
            echo json_encode(["success" => "Data successfully written to file"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Error writing File"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Invalid JSON data or file path."]);
    }
}
?>