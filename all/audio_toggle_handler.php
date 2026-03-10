<?php
/**
 * Audio Toggle Handler with Volume Operation Debugging
 */

require_once __DIR__ . '/../shared/utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

$audioZones = [
    'Bowling Bar Music' => '192.168.8.28',
    'Axe/Billiards Music' => '192.168.8.27',
    'Bowling Music' => '192.168.8.25',
    'Rink Music' => '192.168.8.15',
    'Facility Zone Pro' => '192.168.8.81'
];

$audioSources = [
    'rockbot' => 10,
    'wireless' => 8
];

$wirelessVolumeSettings = [
    'Bowling Bar Music' => 10,
    'Axe/Billiards Music' => 10,
    'Bowling Music' => 11,
    'Rink Music' => 5,
    'Facility Zone Pro' => 10
];

$volumeStorageFile = __DIR__ . '/saved_volumes.json';

// Only include debug output when LOG_LEVEL is set to 'debug'
$debugEnabled = defined('LOG_LEVEL') && strtolower(LOG_LEVEL) === 'debug';
$debugLog = [];

$response = [
    'success' => false,
    'message' => '',
    'results' => []
];

try {
    $source = $_POST['source'] ?? null;
    if (!$source || !isset($audioSources[$source])) {
        throw new Exception('Invalid audio source specified');
    }
    
    $targetChannel = $audioSources[$source];
    $successCount = 0;
    $failureCount = 0;
    
    // Step 1: Save volumes if switching TO wireless
    if ($source === 'wireless') {
        $debugLog[] = "Attempting to save current volumes";
        $volumeLevels = [];

        foreach ($audioZones as $zoneName => $zoneIp) {
            $currentVolume = getCurrentVolume($zoneIp);
            if ($currentVolume !== null) {
                $volumeLevels[$zoneName] = $currentVolume;
                $debugLog[] = "Got volume for $zoneName: $currentVolume";
            } else {
                $debugLog[] = "Failed to get volume for $zoneName";
            }
        }

        if (!empty($volumeLevels)) {
            $volumeData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'volumes' => $volumeLevels
            ];

            // Use file locking to prevent race conditions
            $lockFile = $volumeStorageFile . '.lock';
            $lockHandle = fopen($lockFile, 'c');
            if ($lockHandle && flock($lockHandle, LOCK_EX)) {
                $writeResult = file_put_contents($volumeStorageFile, json_encode($volumeData, JSON_PRETTY_PRINT));
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                if ($writeResult !== false) {
                    $debugLog[] = "Successfully saved volumes to file";
                } else {
                    $debugLog[] = "Failed to write volumes to file";
                }
            } else {
                $debugLog[] = "Failed to acquire lock for volume file";
                if ($lockHandle) fclose($lockHandle);
            }
        } else {
            $debugLog[] = "No volumes collected to save";
        }
    }
    
    // Step 2: Change channels FIRST
    foreach ($audioZones as $zoneName => $zoneIp) {
        $result = setChannel($zoneIp, $targetChannel);
        
        if ($result) {
            $successCount++;
            $response['results'][$zoneName] = ['success' => true, 'message' => 'Channel switched'];
        } else {
            $failureCount++;
            $response['results'][$zoneName] = ['success' => false, 'message' => 'Channel switch failed'];
        }
        
        usleep(200000); // 200ms delay
    }
    
    // Step 3: Handle volumes AFTER channel changes
    if ($source === 'wireless') {
        $debugLog[] = "Setting wireless volumes after 1 second delay";
        sleep(1);

        foreach ($audioZones as $zoneName => $zoneIp) {
            if (isset($wirelessVolumeSettings[$zoneName])) {
                $targetVolume = $wirelessVolumeSettings[$zoneName];
                $volumeResult = setVolume($zoneIp, $targetVolume);

                if ($volumeResult) {
                    $debugLog[] = "Successfully set $zoneName volume to $targetVolume";
                } else {
                    $debugLog[] = "Failed to set $zoneName volume to $targetVolume";
                }

                usleep(100000);
            }
        }

    } else if ($source === 'rockbot') {
        $debugLog[] = "Attempting to restore saved volumes after 1 second delay";
        sleep(1);

        if (file_exists($volumeStorageFile)) {
            $debugLog[] = "Volume file exists, reading content";

            // Use file locking to prevent race conditions during read
            $lockFile = $volumeStorageFile . '.lock';
            $lockHandle = fopen($lockFile, 'c');
            if ($lockHandle && flock($lockHandle, LOCK_SH)) {
                $content = file_get_contents($volumeStorageFile);
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);

                if ($content !== false) {
                    $debugLog[] = "File content read successfully";
                    $data = json_decode($content, true);

                    if ($data && isset($data['volumes'])) {
                        $savedVolumes = $data['volumes'];
                        $debugLog[] = "Found saved volumes";

                        foreach ($audioZones as $zoneName => $zoneIp) {
                            if (isset($savedVolumes[$zoneName])) {
                                $targetVolume = $savedVolumes[$zoneName];
                                $volumeResult = setVolume($zoneIp, $targetVolume);

                                if ($volumeResult) {
                                    $debugLog[] = "Successfully restored $zoneName volume to $targetVolume";
                                } else {
                                    $debugLog[] = "Failed to restore $zoneName volume to $targetVolume";
                                }

                                usleep(100000);
                            } else {
                                $debugLog[] = "No saved volume found for $zoneName";
                            }
                        }
                    } else {
                        $debugLog[] = "Invalid JSON format in volume file";
                    }
                } else {
                    $debugLog[] = "Failed to read volume file content";
                }
            } else {
                $debugLog[] = "Failed to acquire lock for reading volume file";
                if ($lockHandle) fclose($lockHandle);
            }
        } else {
            $debugLog[] = "Volume file does not exist";
        }
    }

    // Determine success
    if ($failureCount === 0) {
        $response['success'] = true;
        $sourceLabel = ($source === 'rockbot' ? 'RockBot Audio' : 'Wireless Mic');
        $response['message'] = "Successfully switched all zones to $sourceLabel";
    } else if ($successCount > 0) {
        $response['success'] = true;
        $response['message'] = "$successCount zones succeeded, $failureCount zones failed";
    } else {
        $response['message'] = "All zones failed to switch";
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error: ' . $e->getMessage();
    $debugLog[] = "Exception: " . $e->getMessage();
}

// Only include debug output when debug mode is enabled
if ($debugEnabled && !empty($debugLog)) {
    $response['volume_debug'] = $debugLog;
}

echo json_encode($response);
exit;
?>
