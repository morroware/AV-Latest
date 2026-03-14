<?php
/**
 * Shared Just Add Power Device Reboot Script
 *
 * Reads device IPs from the zone's config.php RECEIVERS constant
 * instead of hardcoding them. Include this from zone reboot.php files.
 *
 * Usage: Zone reboot.php should do:
 *   require_once __DIR__ . '/config.php';
 *   require_once __DIR__ . '/../shared/reboot.php';
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Collect device IPs from the zone's RECEIVERS constant
$devices = [];
if (defined('RECEIVERS') && is_array(RECEIVERS)) {
    foreach (RECEIVERS as $name => $config) {
        if (isset($config['ip'])) {
            $devices[] = $config['ip'];
        }
    }
}

if (empty($devices)) {
    echo "Error: No receivers configured. Check your zone's config.php.\n";
    exit(1);
}

/**
 * Send reboot command to a device
 */
function rebootDevice($ip) {
    $ch = curl_init();

    if ($ch === false) {
        return [
            'ip' => $ip,
            'success' => false,
            'response' => null,
            'error' => 'Failed to initialize cURL',
            'http_code' => 0
        ];
    }

    try {
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

        return [
            'ip' => $ip,
            'success' => ($httpCode >= 200 && $httpCode < 300),
            'response' => $response,
            'error' => $error,
            'http_code' => $httpCode
        ];
    } finally {
        if ($ch) {
            curl_close($ch);
        }
    }
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
