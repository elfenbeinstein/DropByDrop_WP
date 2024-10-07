<?php
// header files to make read/write possible from itchio
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

$file_path = 'Registration.json';

// generate a unique house code
function generateHouseCode($existingCodes) {
    do {
        $code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    } while (in_array($code, $existingCodes));
    return $code;
}

function fileLock($filePath, $callback) {
    $file = fopen($filePath, 'c+');
    if ($file === false) {
        http_response_code(500);
        echo json_encode(["error" => "Could not open file"]);
        return false;
    }
    if (flock($file, LOCK_EX)) { 
        $result = $callback($file);
        fflush($file);
        flock($file, LOCK_UN);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Could not lock the file"]);
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
    if (file_exists($file_path)) {
        fileLock($file_path, function($file) use ($file_path) {
            // read json file
            $fileSize = filesize($file_path);
            $json_data = $fileSize > 0 ? fread($file, $fileSize) : '{"Households":[]}';
            $data = json_decode($json_data, true);
    
            if ($data === null) {
                $data = ["Households" => []];
            }
            
            // if we send a code --> return a new player id
            if (isset($_GET['code'])) {
                $houseCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
                if (preg_match('/^[A-Z0-9]{4}$/i', $houseCode)) {
                    $found = false;
    
                    foreach ($data['Households'] as &$household) {
                        if ($household['HouseCode'] === $houseCode) {
                            $newPlayerId = str_pad(count($household['Players']), 2, '0', STR_PAD_LEFT);
                            $id = $houseCode . '-' . $newPlayerId;
                            $household['Players'][] = $id;
                            $found = true;
                            echo json_encode(["player_id" => $id]);
                            break;
                        }
                    }
    
                    if (!$found) {
                        http_response_code(404);
                        echo json_encode(["error" => "Household not found"]);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(["error" => "Invalid house code format"]);
                }
            } else {
                $existingCodes = array_column($data['Households'], 'HouseCode');
                $newHouseCode = generateHouseCode($existingCodes);
                $newPlayerId = $newHouseCode . '-00';
    
                $data['Households'][] = [
                    "HouseCode" => $newHouseCode,
                    "Players" => [$newPlayerId]
                ];
    
                echo json_encode(["house_code" => $newHouseCode, "player_id" => $newPlayerId]);
            }
    
            // Write the updated data back to the file
            ftruncate($file, 0);
            rewind($file);
            fwrite($file, json_encode($data, JSON_PRETTY_PRINT));
    
            return true;
        });
    } else {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
    } 
}
?>