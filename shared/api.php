<?php
/**
 * Shared IR Remote Command API
 *
 * Handles POST requests from the frontend to send IR commands to
 * Just Add Power transmitters via fluxhandlerV2.sh.
 *
 * Usage: Zone api.php should simply include this:
 *   require_once __DIR__ . '/../shared/api.php';
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

/**
 * Send an API request to a Just Add Power device using cURL
 */
function sendApiRequest($url, $payload) {
    $ch = curl_init();

    try {
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

        return [
            'response' => $response,
            'httpCode' => $httpCode,
            'error' => $error
        ];
    } finally {
        if ($ch) {
            curl_close($ch);
        }
    }
}

/**
 * Load IR command payloads from a configuration file
 */
function loadPayloads($filename) {
    $payloads = [];

    if (file_exists($filename)) {
        $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines !== false) {
            foreach ($lines as $line) {
                $parts = explode('=', $line, 2);
                if (count($parts) === 2) {
                    $payloads[trim($parts[0])] = trim($parts[1]);
                }
            }
        }
    }

    return $payloads;
}

// Determine the calling zone's directory for loading payloads.txt
$zoneDir = defined('ZONE_DIR') ? ZONE_DIR : dirname($_SERVER['SCRIPT_FILENAME']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['device_url']) || !isset($_POST['action'])) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }

    $deviceUrl = rtrim($_POST['device_url'], '/');
    $action = $_POST['action'];

    $payloads = loadPayloads($zoneDir . '/payloads.txt');

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

echo json_encode(['error' => 'Invalid request method']);
exit;
