<?php
/**
 * Just Add Power Device Reboot Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// All devices to reboot
$devices = [
    '192.168.8.12', '192.168.8.20', '192.168.8.17', '192.168.8.21', '192.168.8.25',
    '192.168.8.60', '192.168.8.61', '192.168.8.62', '192.168.8.63', '192.168.8.15',
    '192.168.8.13', '192.168.8.50', '192.168.8.51', '192.168.8.52', '192.168.8.53',
    '192.168.8.54', '192.168.8.55', '192.168.8.30', '192.168.8.10', '192.168.8.18',
    '192.168.8.19', '192.168.8.11', '192.168.8.27', '192.168.8.16', '192.168.8.26',
    '192.168.8.70'
];

/**
 * Send reboot command to a device
 */
function rebootDevice($ip) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, "http://{$ip}/cgi-bin/api/command/cli");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'reboot');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/plain',
        'Content-Length: 6'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'ip' => $ip,
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'response' => $response,
        'error' => $error,
        'http_code' => $httpCode
    ];
}

// Results array
$results = [];

// Process all devices
echo "Rebooting All Devices...\n";
foreach ($devices as $ip) {
    echo "Rebooting {$ip}... ";
    $result = rebootDevice($ip);
    $results[] = $result;
    
    if ($result['success']) {
        echo "SUCCESS\n";
    } else {
        echo "FAILED (HTTP {$result['http_code']}" . ($result['error'] ? ": {$result['error']}" : "") . ")\n";
    }
    
    // Small delay between devices
    usleep(250000); // 0.25 second delay
}

// Summary
$successCount = count(array_filter($results, function($r) { return $r['success']; }));
echo "\nReboot Summary:\n";
echo "Devices: {$successCount}/" . count($devices) . " successful\n";

// Log failures if any
$failures = array_filter($results, function($r) { return !$r['success']; });

if (!empty($failures)) {
    echo "\nFailed devices:\n";
    foreach ($failures as $failure) {
        echo "{$failure['ip']}: HTTP {$failure['http_code']}" . ($failure['error'] ? " - {$failure['error']}" : "") . "\n";
    }
}

echo "\nDevices will reboot in approximately 90 seconds.\n";
