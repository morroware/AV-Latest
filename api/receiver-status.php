<?php
/**
 * Receiver Status API Endpoint
 *
 * Returns the current status of a receiver (channel, volume, capabilities).
 * Used for lazy-loading receiver information after page load.
 *
 * GET Parameters:
 *   - ip: Device IP address (required)
 *
 * Response:
 *   - success: boolean
 *   - channel: current channel number (or null if unreachable)
 *   - volume: current volume level (or null if not supported)
 *   - supportsVolume: boolean
 *   - error: error message (only on failure)
 */

// Include shared utilities
require_once __DIR__ . '/../shared/utils.php';

// Set JSON response header
header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate IP parameter
$deviceIp = isset($_GET['ip']) ? sanitizeInput($_GET['ip'], 'ip') : null;

if (!$deviceIp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing IP address']);
    exit;
}

// Fetch receiver status
$response = [
    'success' => true,
    'channel' => null,
    'volume' => null,
    'supportsVolume' => false
];

try {
    // Get current channel
    $channel = getCurrentChannel($deviceIp);
    if ($channel !== null) {
        $response['channel'] = $channel;
    } else {
        throw new Exception('Unable to get channel');
    }

    // Check if device supports volume control
    $supportsVolume = supportsVolumeControl($deviceIp);
    $response['supportsVolume'] = $supportsVolume;

    // Get current volume if supported
    if ($supportsVolume) {
        $volume = getCurrentVolume($deviceIp);
        $response['volume'] = $volume;
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = 'Device unreachable';
    logMessage("Receiver status error for $deviceIp: " . $e->getMessage(), 'error');
}

echo json_encode($response);
