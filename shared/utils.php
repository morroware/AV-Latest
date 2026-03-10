<?php
/**
 * Unified Utility Functions for AV Controls System
 *
 * This file consolidates all utility functions used across all zones.
 * It includes basic receiver control functions as well as advanced
 * DSP audio control functions for devices that support them.
 *
 * @author Seth Morrow
 * @version 3.0 (Refactored)
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
if (!defined('VOLUME_CONTROL_MODELS')) {
    define('VOLUME_CONTROL_MODELS', ['3G+4+ TX', '3G+AVP RX', '3G+AVP TX', '3G+WP4 TX', '2G/3G SX']);
}

// ============================================================================
// RECEIVER FORM GENERATION
// ============================================================================

/**
 * Generate the HTML for all receiver forms
 *
 * @return string HTML for all receiver forms
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
 * Generate a single receiver form (lazy-loaded)
 *
 * This function generates the receiver card HTML without making any blocking API calls.
 * The channel/volume status is fetched asynchronously via JavaScript after page load.
 *
 * @param string $receiverName Display name for the receiver
 * @param string $deviceIp IP address of the device
 * @param int $minVolume Minimum volume level
 * @param int $maxVolume Maximum volume level
 * @param int $volumeStep Volume adjustment step
 * @param bool $showPower Whether to show power controls
 * @return string HTML for the receiver form
 */
function generateReceiverForm($receiverName, $deviceIp, $minVolume, $maxVolume, $volumeStep, $showPower = true) {
    $escapedName = htmlspecialchars($receiverName);
    $escapedIp = htmlspecialchars($deviceIp);

    $html = "<div class='receiver receiver-loading' data-ip='" . $escapedIp . "' data-name='" . $escapedName . "' data-min-volume='$minVolume' data-max-volume='$maxVolume' data-volume-step='$volumeStep' data-show-power='" . ($showPower ? '1' : '0') . "'>";
    $html .= "<button type='button' class='receiver-title'>" . $escapedName . "</button>";

    // Loading placeholder
    $html .= "<div class='receiver-content'>";
    $html .= "<div class='receiver-loading-placeholder'>";
    $html .= "<span class='spinner'></span> Loading...";
    $html .= "</div>";
    $html .= "</div>";

    $html .= "</div>";

    return $html;
}

/**
 * Get transmitters list as JSON for JavaScript
 *
 * @return string JSON-encoded transmitters array
 */
function getTransmittersJson() {
    if (!defined('TRANSMITTERS')) {
        return '{}';
    }
    return json_encode(TRANSMITTERS);
}

// ============================================================================
// API COMMUNICATION
// ============================================================================

/**
 * Make API calls to AV devices
 *
 * @param string $method HTTP method (GET, POST, etc.)
 * @param string $deviceIp IP address of the device
 * @param string $endpoint API endpoint path
 * @param mixed $data Data to send (optional)
 * @param string $contentType Content type header
 * @return string API response
 * @throws Exception on failure
 */
function makeApiCall($method, $deviceIp, $endpoint, $data = null, $contentType = 'application/x-www-form-urlencoded') {
    $timeout = defined('API_TIMEOUT') ? API_TIMEOUT : 5;

    // Handle both full URLs (http://...) and bare IP addresses
    if (preg_match('#^https?://#i', $deviceIp)) {
        // Already has scheme - extract just the host/IP
        $parsed = parse_url($deviceIp);
        $deviceIp = $parsed['host'] ?? $deviceIp;
    }

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

// ============================================================================
// VOLUME CONTROL
// ============================================================================

/**
 * Get current volume level from device
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
 * Set volume level on device
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
 * Check if device supports volume control
 */
function supportsVolumeControl($deviceIp) {
    try {
        $model = getDeviceModel($deviceIp);
        return in_array($model, VOLUME_CONTROL_MODELS);
    } catch (Exception $e) {
        logMessage('Error checking volume control support: ' . $e->getMessage(), 'error');
        return false;
    }
}

// ============================================================================
// CHANNEL CONTROL
// ============================================================================

/**
 * Get current channel from device
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
 * Set channel on device
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

// ============================================================================
// DEVICE INFORMATION
// ============================================================================

/**
 * Cache for device models to avoid redundant API calls
 */
$_deviceModelCache = [];

/**
 * Get device model string with caching
 *
 * @param string $deviceIp Device IP address
 * @param bool $forceRefresh Force a fresh API call
 * @return string Device model or empty string on failure
 */
function getDeviceModel($deviceIp, $forceRefresh = false) {
    global $_deviceModelCache;

    // Return cached model if available
    if (!$forceRefresh && isset($_deviceModelCache[$deviceIp])) {
        return $_deviceModelCache[$deviceIp];
    }

    try {
        $response = makeApiCall('GET', $deviceIp, 'details/device/model');
        $data = json_decode($response, true);
        $model = isset($data['data']) ? $data['data'] : '';

        // Cache the result
        $_deviceModelCache[$deviceIp] = $model;

        return $model;
    } catch (Exception $e) {
        logMessage('Error getting device model: ' . $e->getMessage(), 'error');
        // Cache empty string to avoid repeated failed calls
        $_deviceModelCache[$deviceIp] = '';
        return '';
    }
}

// ============================================================================
// DSP AUDIO CONTROL (Advanced)
// ============================================================================

/**
 * Check if device supports DSP audio control
 */
function supportsDspControl($deviceIp) {
    $model = getDeviceModel($deviceIp);
    return (strpos($model, '3G+AVP TX') !== false || strpos($model, '3G+WP4 TX') !== false);
}

/**
 * Disable DSP Line audio
 */
function disableDspLineAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/dsp/line', '"off"', 'application/json');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage("DSP Line audio control not available for $deviceIp: " . $e->getMessage(), 'info');
        return false;
    }
}

/**
 * Enable DSP Line audio
 */
function enableDspLineAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/dsp/line', '"on"', 'application/json');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage("DSP Line audio control not available for $deviceIp: " . $e->getMessage(), 'info');
        return false;
    }
}

/**
 * Disable DSP HDMI audio
 */
function disableDspHdmiAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/dsp/hdmi', '"off"', 'application/json');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage("DSP HDMI audio control not available for $deviceIp: " . $e->getMessage(), 'info');
        return false;
    }
}

/**
 * Enable DSP HDMI audio
 */
function enableDspHdmiAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/dsp/hdmi', '"on"', 'application/json');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage("DSP HDMI audio control not available for $deviceIp: " . $e->getMessage(), 'info');
        return false;
    }
}

/**
 * Disable HDMI audio (mute)
 */
function disableHdmiAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/hdmi/audio/mute', null);
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error disabling HDMI audio: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Enable HDMI audio (unmute)
 */
function enableHdmiAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/hdmi/audio/unmute', null);
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error enabling HDMI audio: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Disable stereo audio output (mute)
 */
function disableStereoAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/cli', 'audio_out.sh mute', 'text/plain');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error disabling stereo audio: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Enable stereo audio output (unmute)
 */
function enableStereoAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/cli', 'audio_out.sh unmute', 'text/plain');
        $data = json_decode($response, true);
        return isset($data['data']) && $data['data'] === 'OK';
    } catch (Exception $e) {
        logMessage('Error enabling stereo audio: ' . $e->getMessage(), 'error');
        return false;
    }
}

/**
 * Set channel with comprehensive anti-popping measures
 * Uses reduced timing for better responsiveness while maintaining audio quality
 */
function setChannelWithoutPopping($deviceIp, $channel) {
    $supportsDsp = false;
    $currentVolume = null;
    $audioDisabled = false;
    $channelChangeResult = false;

    try {
        $supportsDsp = supportsDspControl($deviceIp);
        $currentVolume = getCurrentVolume($deviceIp);

        // Step 1: Set volume to zero (reduced from 1s to 500ms)
        if ($currentVolume !== null && $currentVolume > 0) {
            setVolume($deviceIp, 0);
            usleep(500000); // 500ms
        }

        // Step 2: Disable audio outputs
        if ($supportsDsp) {
            disableDspLineAudio($deviceIp);
            disableDspHdmiAudio($deviceIp);
        }
        disableHdmiAudio($deviceIp);
        disableStereoAudio($deviceIp);
        $audioDisabled = true;

        usleep(500000); // 500ms (reduced from 2s)

        // Step 3: Change channel
        $channelChangeResult = setChannel($deviceIp, $channel);

        // Step 4: Wait for channel to stabilize (reduced from 3s to 1.5s)
        usleep(1500000); // 1.5s

    } catch (Exception $e) {
        logMessage('Error setting channel with anti-popping: ' . $e->getMessage(), 'error');
        $channelChangeResult = false;
    }

    // ALWAYS restore audio - this runs regardless of success/failure
    try {
        if ($audioDisabled) {
            enableStereoAudio($deviceIp);
            enableHdmiAudio($deviceIp);
            if ($supportsDsp) {
                enableDspHdmiAudio($deviceIp);
                enableDspLineAudio($deviceIp);
            }
        }

        usleep(300000); // 300ms (reduced from 1s)

        // Restore volume
        if ($currentVolume !== null && $currentVolume > 0) {
            setVolume($deviceIp, $currentVolume);
        }
    } catch (Exception $re) {
        logMessage("Error re-enabling audio for $deviceIp: " . $re->getMessage(), 'error');
    }

    return $channelChangeResult;
}

// ============================================================================
// INPUT VALIDATION
// ============================================================================

/**
 * Sanitize user input
 *
 * @param mixed $data The data to sanitize
 * @param string $type The type of sanitization (int, ip, string)
 * @param array $options Additional options for validation
 * @return mixed Sanitized value or null on failure
 */
function sanitizeInput($data, $type, $options = []) {
    if ($data === null) {
        return null;
    }

    switch ($type) {
        case 'int':
            // Handle string "0" correctly
            if (is_numeric($data)) {
                $intVal = intval($data);
                $min = $options['min'] ?? PHP_INT_MIN;
                $max = $options['max'] ?? PHP_INT_MAX;
                if ($intVal >= $min && $intVal <= $max) {
                    return $intVal;
                }
            }
            return null;
        case 'ip':
            $sanitized = filter_var($data, FILTER_VALIDATE_IP);
            return $sanitized !== false ? $sanitized : null;
        case 'string':
            // FILTER_SANITIZE_STRING is deprecated in PHP 8.1+
            // Use htmlspecialchars for safe output and strip_tags to remove HTML
            if (!is_string($data)) {
                return null;
            }
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        default:
            return null;
    }
}

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Log messages to file
 */
function logMessage($message, $level = 'info') {
    if ($level === 'error' || strtolower(LOG_LEVEL) === $level) {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        $logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/av_controls.log';
        error_log($formattedMessage, 3, $logFile);
    }
}

// ============================================================================
// PAYLOAD LOADING
// ============================================================================

/**
 * Load IR command payloads from file
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
