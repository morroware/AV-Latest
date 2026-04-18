<?php
/**
 * WLED Control Handler (Shared)
 *
 * Handles POST requests to turn WLED devices on or off.
 * This is a shared file - zones include this with their $ZONE_DIR set.
 *
 * @author Seth Morrow
 * @version 3.0 (Refactored)
 */

// ZONE_DIR should be defined by the including script
if (!defined('ZONE_DIR')) {
    define('ZONE_DIR', dirname(__FILE__));
}

// Set Content-Type early so all responses (including early exits) are valid JSON
header('Content-Type: application/json');

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
$wledListFile = ZONE_DIR . '/WLEDlist.ini';
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

// Per-device timeouts (apply regardless of whether curl_multi is available)
$timeout = 3;
$connectTimeout = 2;

// Build handle list up front so the main loop is the same for single or
// parallel dispatch.  Pre-validation catches obviously-bad IPs without
// wasting a cURL handle.
$handles = [];
foreach ($wledDevices as $deviceIp) {
    $deviceIp = trim($deviceIp, '" ');
    if (!filter_var($deviceIp, FILTER_VALIDATE_IP)) {
        $failureCount++;
        $failures[] = ['ip' => $deviceIp, 'http_code' => 0, 'response' => 'Invalid IP address'];
        continue;
    }

    $ch = curl_init("http://$deviceIp$apiPath");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($payload)
    ]);

    $handles[] = ['ip' => $deviceIp, 'ch' => $ch];
}

if (!empty($handles)) {
    // Dispatch all requests in parallel — critical when one or more WLED
    // devices are offline, otherwise we pay the full timeout for each one
    // sequentially (up to N * $timeout seconds).
    $mh = curl_multi_init();
    foreach ($handles as $h) {
        curl_multi_add_handle($mh, $h['ch']);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running > 0) {
            // Block briefly waiting for activity to avoid a tight CPU loop.
            curl_multi_select($mh, 0.1);
        }
    } while ($running > 0);

    foreach ($handles as $h) {
        $response = curl_multi_getcontent($h['ch']);
        $httpCode = curl_getinfo($h['ch'], CURLINFO_HTTP_CODE);
        $curlError = curl_error($h['ch']);

        if ($response !== false && $response !== null && $httpCode === 200) {
            $successCount++;
        } else {
            $failureCount++;
            $failures[] = [
                'ip' => $h['ip'],
                'http_code' => $httpCode,
                'response' => ($response !== false && $response !== null && $response !== '') ? $response : $curlError
            ];
        }

        curl_multi_remove_handle($mh, $h['ch']);
        curl_close($h['ch']);
    }
    curl_multi_close($mh);
}

// Prepare response
$response = [
    'success' => ($failureCount === 0),
    'message' => $failureCount === 0
        ? "All devices successfully turned $action."
        : "$successCount devices succeeded, $failureCount devices failed.",
    'failures' => $failures
];

// Send JSON response (Content-Type already set at top of file)
echo json_encode($response);
exit;
