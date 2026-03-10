<?php
// wled.php: Handles POST requests to turn WLED devices on or off.

// Ensure this script only processes POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

// Parse input parameters
$action = $_POST['action'] ?? null; // Expecting 'on' or 'off'

if (!in_array($action, ['on', 'off'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use "on" or "off".']);
    exit;
}

// Load WLED device IPs from WLEDlist.ini
$wledListFile = 'WLEDlist.ini';
if (!file_exists($wledListFile)) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'WLEDlist.ini file not found.']);
    exit;
}

$wledDevices = [];
$iniData = parse_ini_file($wledListFile, true);
if (isset($iniData['WLEDs'])) {
    $wledDevices = array_values($iniData['WLEDs']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Invalid WLEDlist.ini format.']);
    exit;
}

// WLED API endpoint and payloads for on/off
$apiPath = '/json/state';
$payload = json_encode(['on' => ($action === 'on')]);

$successCount = 0;
$failureCount = 0;
$failures = [];

// Iterate through each device and send the request
foreach ($wledDevices as $deviceIp) {
    $url = "http://$deviceIp$apiPath";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        $successCount++;
    } else {
        $failureCount++;
        $failures[] = [
            'ip' => $deviceIp,
            'http_code' => $httpCode,
            'response' => $response
        ];
    }

    curl_close($ch);
}

// Prepare response
$response = [
    'success' => ($failureCount === 0),
    'message' => $failureCount === 0
        ? "All devices successfully turned $action."
        : "$successCount devices succeeded, $failureCount devices failed.",
    'failures' => $failures
];

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
