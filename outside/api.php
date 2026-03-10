<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Function to send an API request using cURL
function sendApiRequest($url, $payload) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/plain',
        'User-Agent: JustOS API Tester'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $error
    ];
}

// Function to load payloads from a text file
function loadPayloads($filename) {
    $payloads = [];
    if (file_exists($filename)) {
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            list($action, $irCode) = explode('=', $line, 2);
            $payloads[trim($action)] = trim($irCode);
        }
    }
    return $payloads;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['device_url']) || !isset($_POST['action'])) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    $deviceUrl = rtrim($_POST['device_url'], '/');
    $action = $_POST['action'];
    
    // Load payloads from the text file
    $payloads = loadPayloads('payloads.txt');
    
    if (!isset($payloads[$action])) {
        echo json_encode(['error' => 'Invalid action']);
        exit;
    }

    $url = $deviceUrl . "/cgi-bin/api/command/cli";
    $payload = 'echo "' . $payloads[$action] . '" | ./fluxhandlerV2.sh';
    
    $result = sendApiRequest($url, $payload);
    
    if ($result['error'] || $result['httpCode'] >= 400) {
        echo json_encode([
            'error' => $result['error'] ?: "HTTP Error " . $result['httpCode']
        ]);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}

// If not a POST request, return an error
echo json_encode(['error' => 'Invalid request method']);
exit;
