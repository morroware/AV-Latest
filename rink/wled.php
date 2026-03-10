<?php
/**
 * Utility functions for AV Controls System
 */

// Set default values for configuration constants if they're not defined
if (!defined('API_TIMEOUT')) define('API_TIMEOUT', 5);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', 'error');
if (!defined('MAX_VOLUME')) define('MAX_VOLUME', 11);
if (!defined('MIN_VOLUME')) define('MIN_VOLUME', 0);
if (!defined('VOLUME_STEP')) define('VOLUME_STEP', 1);
if (!defined('HOME_URL')) define('HOME_URL', 'http://localhost');
if (!defined('ERROR_MESSAGES')) {
    define('ERROR_MESSAGES', [
        'connection' => 'Unable to connect to %s (%s). Please check the connection and try again.',
        'global' => 'Unable to connect to any receivers. Please check your network connection and try again.',
        'remote' => 'Unable to send remote command. Please try again.'
    ]);
}

/**
 * Function to generate the HTML for all receiver forms
 */
function generateReceiverForms() {
    if (!defined('RECEIVERS')) {
        return '<div class="error">No receivers configured</div>';
    }

    $html = '';
    foreach (RECEIVERS as $receiverName => $settings) {
        try {
            $html .= generateReceiverForm($receiverName, $settings['ip'], MIN_VOLUME, MAX_VOLUME, VOLUME_STEP, $settings['show_power']);
        } catch (Exception $e) {
            $html .= "<div class='receiver'><p class='warning'>Error generating form for " . htmlspecialchars($receiverName) . ": " . htmlspecialchars($e->getMessage()) . "</p></div>";
            logMessage("Error generating form for {$receiverName}: " . $e->getMessage(), 'error');
        }
    }
    return $html;
}

/**
 * Function to generate a single receiver form
 * Updated to remove form tags and update button for instant controls
 */
function generateReceiverForm($receiverName, $deviceIp, $minVolume, $maxVolume, $volumeStep, $showPower = true) {
    try {
        $currentChannel = getCurrentChannel($deviceIp);
        if ($currentChannel === null) {
            throw new Exception("Unable to get current channel");
        }
        $supportsVolume = supportsVolumeControl($deviceIp);
        
        $html = "<div class='receiver' data-ip='" . htmlspecialchars($deviceIp) . "'>";
        $html .= "<button type='button' class='receiver-title'>" . htmlspecialchars($receiverName) . "</button>";
        
        // Generate channel selection dropdown
        $html .= "<label for='channel_" . htmlspecialchars($receiverName) . "'>Channel:</label>";
        $html .= "<select id='channel_" . htmlspecialchars($receiverName) . "' class='channel-select' data-ip='" . htmlspecialchars($deviceIp) . "'>";
        if (defined('TRANSMITTERS')) {
            foreach (TRANSMITTERS as $transmitterName => $channelNumber) {
                $selected = ($channelNumber == $currentChannel) ? ' selected' : '';
                $html .= "<option value='$channelNumber'$selected>" . htmlspecialchars($transmitterName) . "</option>";
            }
        }
        $html .= "</select>";
        
        // Generate volume control if supported
        if ($supportsVolume) {
            $currentVolume = getCurrentVolume($deviceIp);
            if ($currentVolume === null) {
                $currentVolume = $minVolume;
            }
            $html .= "<label for='volume_" . htmlspecialchars($receiverName) . "'>Volume:</label>";
            $html .= "<input type='range' id='volume_" . htmlspecialchars($receiverName) . "' class='volume-slider' data-ip='" . htmlspecialchars($deviceIp) . "' min='$minVolume' max='$maxVolume' step='$volumeStep' value='$currentVolume'>";
            $html .= "<span class='volume-label'>$currentVolume</span>";
        }
        
        // Add power buttons if enabled
        if ($showPower) {
            $html .= "<div class='power-buttons'>";
            $html .= "<button type='button' class='power-on' onclick='sendPowerCommand(\"" . htmlspecialchars($deviceIp) . "\", \"cec_tv_on.sh\")'>Power On</button>";
            $html .= "<button type='button' class='power-off' onclick='sendPowerCommand(\"" . htmlspecialchars($deviceIp) . "\", \"cec_tv_off.sh\")'>Power Off</button>";
            $html .= "</div>";
        }
        
        $html .= "</div>";
        
        return $html;
    } catch (Exception $e) {
        // Simple error card when device is unreachable
        return "<div class='receiver'>" .
               "<button type='button' class='receiver-title'>" . htmlspecialchars($receiverName) . "</button>" .
               "<p style='text-align: center; color: #ff6b6b; padding: 1rem;'>" .
               "Device unreachable. Please check connection." .
               "</p></div>";
    }
}

/**
 * Function to make API calls to devices
 */
function makeApiCall($method, $deviceIp, $endpoint, $data = null, $contentType = 'application/x-www-form-urlencoded') {
    $timeout = defined('API_TIMEOUT') ? API_TIMEOUT : 5;
    
    $apiUrl = 'http://' . $deviceIp . '/cgi-bin/api/' . $endpoint;
    $ch = curl_init($apiUrl);
    
    if ($ch === false) {
        throw new Exception('Failed to initialize cURL');
    }
    
    try {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        if ($data !== null) {
            if ($contentType === 'application/json' && !is_string($data)) {
                $data = json_encode($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: ' . $contentType));
        }

        $result = curl_exec($ch);
        
        if ($result === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 400) {
            throw new Exception('HTTP error: ' . $httpCode . ' - Response: ' . $result);
        }

        return $result;
    } finally {
        if ($ch) {
            curl_close($ch);
        }
    }
}

/**
 * Function to get current volume
 */
function getCurrentVolume($deviceIp) {
    try {
        $response = makeApiCall('GET', $deviceIp, 'details/audio/stereo/volume');
        $data = json_decode($response, true);
        return isset($data['data']) ? intval($data['data']) : null;
    } catch (Exception $e) {
        logMessage('Error getting current volume: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Function to get current channel
 */
function getCurrentChannel($deviceIp) {
    try {
        $response = makeApiCall('GET', $deviceIp, 'details/channel');
        $data = json_decode($response, true);
        return isset($data['data']) ? intval($data['data']) : null;
    } catch (Exception $e) {
        logMessage('Error getting current channel: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Function to set volume
 */
function setVolume($deviceIp, $volume) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/stereo/volume', $volume, 'text/plain');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error setting volume: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Function to set channel
 */
function setChannel($deviceIp, $channel) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/channel', $channel, 'text/plain');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error setting channel: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Function to check volume control support
 */
function supportsVolumeControl($deviceIp) {
    try {
        $response = makeApiCall('GET', $deviceIp, 'details/device/model');
        $data = json_decode($response, true);
        $model = $data['data'] ?? '';
        if (!defined('VOLUME_CONTROL_MODELS')) {
            define('VOLUME_CONTROL_MODELS', ['3G+4+ TX', '3G+AVP RX', '3G+AVP TX', '3G+WP4 TX', '2G/3G SX']);
        }
        return in_array($model, VOLUME_CONTROL_MODELS);
    } catch (Exception $e) {
        logMessage('Error checking volume control support: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Function to sanitize input
 */
function sanitizeInput($data, $type, $options = []) {
    switch ($type) {
        case 'int':
            $sanitized = filter_var($data, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => $options['min'] ?? PHP_INT_MIN,
                    'max_range' => $options['max'] ?? PHP_INT_MAX
                ]
            ]);
            break;
        case 'ip':
            $sanitized = filter_var($data, FILTER_VALIDATE_IP);
            break;
        case 'string':
            $sanitized = filter_var($data, FILTER_SANITIZE_STRING);
            break;
        default:
            $sanitized = null;
    }
    return $sanitized !== false ? $sanitized : null;
}

/**
 * Function to log messages
 */
function logMessage($message, $level = 'info') {
    if ($level === 'error' || strtolower(LOG_LEVEL) === $level) {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        error_log($formattedMessage, 3, __DIR__ . '/av_controls.log');
    }
}

/**
 * Function to load IR command payloads
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
