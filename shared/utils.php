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
if (!defined('API_TIMEOUT')) define('API_TIMEOUT', 2);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', 'error');
if (!defined('MAX_VOLUME')) define('MAX_VOLUME', 11);
if (!defined('MIN_VOLUME')) define('MIN_VOLUME', 0);
if (!defined('VOLUME_STEP')) define('VOLUME_STEP', 1);
if (!defined('HOME_URL')) define('HOME_URL', '/');
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
            $html .= generateReceiverForm($receiverName, $settings, MIN_VOLUME, MAX_VOLUME, VOLUME_STEP);
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
 * @param array $settings Receiver settings from RECEIVERS config
 * @param int $minVolume Minimum volume level
 * @param int $maxVolume Maximum volume level
 * @param int $volumeStep Volume adjustment step
 * @return string HTML for the receiver form
 */
function generateReceiverForm($receiverName, $settings, $minVolume, $maxVolume, $volumeStep) {
    $deviceIp = $settings['ip'] ?? '';
    $showPower = isset($settings['show_power']) ? (bool)$settings['show_power'] : true;
    $powerOnCommand = $settings['power_on_command'] ?? 'cec_tv_on.sh';
    $powerOffCommand = $settings['power_off_command'] ?? 'cec_tv_off.sh';
    $powerOnRepeat = isset($settings['power_on_repeat']) ? (bool)$settings['power_on_repeat'] : true;
    $powerOnFollowupCommand = $settings['power_on_followup_command'] ?? 'cec_watch_me.sh';
    $powerOnFollowupFallbackCommand = $settings['power_on_followup_fallback_command'] ?? 'cec_power_on_tv';
    $powerOnFollowupDelayMs = isset($settings['power_on_followup_delay_ms']) ? (int)$settings['power_on_followup_delay_ms'] : 7000;
    $powerOffPreCommand = $settings['power_off_pre_command'] ?? 'cec_watch_me.sh';
    $powerOffPreDelayMs = isset($settings['power_off_pre_delay_ms']) ? (int)$settings['power_off_pre_delay_ms'] : 3000;
    // Opt-in flag for TVs that need the extended CEC retry sequence (direct
    // binary retries + repeated one-touch-play source selects).  Useful for
    // Roku, some Samsung/LG sets, and any display with aggressive CEC
    // filtering.  When true, the client sends additional retries on both
    // power on and power off.
    $powerExtendedRetry = isset($settings['power_extended_retry']) ? (bool)$settings['power_extended_retry'] : false;

    $escapedName = htmlspecialchars($receiverName);
    $escapedIp = htmlspecialchars($deviceIp);
    $escapedPowerOn = htmlspecialchars($powerOnCommand);
    $escapedPowerOff = htmlspecialchars($powerOffCommand);
    $escapedPowerOnFollowup = htmlspecialchars($powerOnFollowupCommand);
    $escapedPowerOnFollowupFallback = htmlspecialchars($powerOnFollowupFallbackCommand);
    $escapedPowerOffPre = htmlspecialchars($powerOffPreCommand);

    $html = "<div class='receiver receiver-loading' data-ip='" . $escapedIp . "' data-name='" . $escapedName . "' data-min-volume='$minVolume' data-max-volume='$maxVolume' data-volume-step='$volumeStep' data-show-power='" . ($showPower ? '1' : '0') . "' data-power-on-command='" . $escapedPowerOn . "' data-power-off-command='" . $escapedPowerOff . "' data-power-on-repeat='" . ($powerOnRepeat ? '1' : '0') . "' data-power-on-followup-command='" . $escapedPowerOnFollowup . "' data-power-on-followup-fallback-command='" . $escapedPowerOnFollowupFallback . "' data-power-on-followup-delay-ms='" . max(0, $powerOnFollowupDelayMs) . "' data-power-off-pre-command='" . $escapedPowerOffPre . "' data-power-off-pre-delay-ms='" . max(0, $powerOffPreDelayMs) . "' data-power-extended-retry='" . ($powerExtendedRetry ? '1' : '0') . "'>";
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
 * Decode a JSON payload from a device and safely extract the `data` field.
 *
 * Returns null if the payload is not valid JSON or has no `data` field.
 * Using a helper avoids repeated `isset($x['data'])` checks against a
 * possibly-null `json_decode()` result across the codebase.
 */
function extractDeviceData($rawResponse) {
    if (!is_string($rawResponse) || $rawResponse === '') {
        return null;
    }
    $decoded = json_decode($rawResponse, true);
    if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
        return null;
    }
    return $decoded['data'];
}

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
        $value = extractDeviceData($response);
        return $value !== null && is_numeric($value) ? intval($value) : null;
    } catch (Exception $e) {
        logMessage('Error getting current volume: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Set volume level on device
 *
 * Uses fire-and-forget semantics — see setChannel() for rationale.
 */
function setVolume($deviceIp, $volume) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/audio/stereo/volume', $volume, 'text/plain');
        if (extractDeviceData($response) === 'OK') {
            return true;
        }
        logMessage("setVolume got unexpected response for $deviceIp: $response", 'debug');
        return true;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $isUnreachable = stripos($msg, 'Could not resolve') !== false
            || stripos($msg, 'Connection refused') !== false
            || stripos($msg, 'No route to host') !== false;

        if ($isUnreachable) {
            logMessage("setVolume failed — device unreachable: $msg", 'error');
            return false;
        }

        logMessage("setVolume for $deviceIp (non-fatal cURL issue): $msg", 'debug');
        return true;
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
        $value = extractDeviceData($response);
        return $value !== null && is_numeric($value) ? intval($value) : null;
    } catch (Exception $e) {
        logMessage('Error getting current channel: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * Set channel on device
 *
 * Uses fire-and-forget semantics: the device processes the command as soon
 * as the HTTP request body arrives.  Timeouts, empty replies, and connection
 * resets do NOT mean the command failed — only that the response was lost.
 * Only connection-refused / DNS failures indicate the command was never sent.
 */
function setChannel($deviceIp, $channel) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/channel', $channel, 'text/plain');
        if (extractDeviceData($response) === 'OK') {
            return true;
        }
        // Non-standard response but request was delivered — treat as success
        logMessage("setChannel got unexpected response for $deviceIp: $response", 'debug');
        return true;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        $isUnreachable = stripos($msg, 'Could not resolve') !== false
            || stripos($msg, 'Connection refused') !== false
            || stripos($msg, 'No route to host') !== false;

        if ($isUnreachable) {
            logMessage("setChannel failed — device unreachable: $msg", 'error');
            return false;
        }

        // Timeout, empty reply, reset — command was likely delivered
        logMessage("setChannel for $deviceIp (non-fatal cURL issue): $msg", 'debug');
        return true;
    }
}

// ============================================================================
// DEVICE INFORMATION
// ============================================================================

/**
 * In-process cache for device models (per-request)
 */
$_deviceModelCache = [];

/**
 * Persistent device model cache TTL (seconds).  Models never change on a
 * given device so a generous TTL is safe; 1 hour lets us absorb device
 * restarts or IP swaps within a reasonable window.
 */
if (!defined('DEVICE_MODEL_CACHE_TTL')) define('DEVICE_MODEL_CACHE_TTL', 3600);

/**
 * Path for the persistent device-model cache file.
 * Stored alongside utils.php so it inherits the same permissions.
 */
function _deviceModelCacheFile() {
    return __DIR__ . '/.device_model_cache.json';
}

/**
 * Load the persistent device model cache from disk.  Returns a map of
 * ip => ['model' => string, 'ts' => int]
 */
function _loadPersistentModelCache() {
    $path = _deviceModelCacheFile();
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Save the persistent device model cache.  Best-effort — failures are swallowed.
 */
function _savePersistentModelCache(array $cache) {
    $path = _deviceModelCacheFile();
    $json = json_encode($cache);
    if ($json === false) return;
    // Atomic write: write to tmp then rename
    $tmp = $path . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $json) === false) return;
    @rename($tmp, $path);
}

/**
 * Get device model string with caching
 *
 * Uses a two-layer cache: an in-process array for the current request
 * and a short-lived file cache shared across requests.  Non-empty models
 * are considered stable and cached for DEVICE_MODEL_CACHE_TTL seconds.
 * Empty-string entries (unreachable device) are cached for a much shorter
 * window (60 s) so a reboot is picked up quickly.
 *
 * @param string $deviceIp Device IP address
 * @param bool $forceRefresh Force a fresh API call
 * @return string Device model or empty string on failure
 */
function getDeviceModel($deviceIp, $forceRefresh = false) {
    global $_deviceModelCache;

    if (!$forceRefresh && isset($_deviceModelCache[$deviceIp])) {
        return $_deviceModelCache[$deviceIp];
    }

    // Try persistent cache
    if (!$forceRefresh) {
        $persistent = _loadPersistentModelCache();
        if (isset($persistent[$deviceIp]['ts'], $persistent[$deviceIp]['model'])) {
            $age = time() - (int) $persistent[$deviceIp]['ts'];
            $ttl = $persistent[$deviceIp]['model'] === '' ? 60 : DEVICE_MODEL_CACHE_TTL;
            if ($age < $ttl) {
                $_deviceModelCache[$deviceIp] = $persistent[$deviceIp]['model'];
                return $persistent[$deviceIp]['model'];
            }
        }
    }

    try {
        $response = makeApiCall('GET', $deviceIp, 'details/device/model');
        $value = extractDeviceData($response);
        $model = is_string($value) ? $value : '';

        $_deviceModelCache[$deviceIp] = $model;

        $persistent = _loadPersistentModelCache();
        $persistent[$deviceIp] = ['model' => $model, 'ts' => time()];
        _savePersistentModelCache($persistent);

        return $model;
    } catch (Exception $e) {
        logMessage('Error getting device model: ' . $e->getMessage(), 'error');
        $_deviceModelCache[$deviceIp] = '';

        $persistent = _loadPersistentModelCache();
        $persistent[$deviceIp] = ['model' => '', 'ts' => time()];
        _savePersistentModelCache($persistent);

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
 * Set DSP audio state for a given type (line or hdmi)
 *
 * @param string $deviceIp Device IP address
 * @param string $type Audio type ('line' or 'hdmi')
 * @param bool $enabled True to enable, false to disable
 * @return bool Success status
 */
function setDspAudioState($deviceIp, $type, $enabled) {
    try {
        $state = $enabled ? '"on"' : '"off"';
        $response = makeApiCall('POST', $deviceIp, "command/audio/dsp/$type", $state, 'application/json');
        return extractDeviceData($response) === 'OK';
    } catch (Exception $e) {
        logMessage("DSP $type audio control not available for $deviceIp: " . $e->getMessage(), 'info');
        return false;
    }
}

/**
 * Disable HDMI audio (mute)
 */
function disableHdmiAudio($deviceIp) {
    try {
        $response = makeApiCall('POST', $deviceIp, 'command/hdmi/audio/mute', null);
        return extractDeviceData($response) === 'OK';
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
        return extractDeviceData($response) === 'OK';
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
        return extractDeviceData($response) === 'OK';
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
        return extractDeviceData($response) === 'OK';
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
            setDspAudioState($deviceIp, 'line', false);
            setDspAudioState($deviceIp, 'hdmi', false);
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
                setDspAudioState($deviceIp, 'hdmi', true);
                setDspAudioState($deviceIp, 'line', true);
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
 * Maximum log file size (bytes) before rotation. 1 MB by default.
 */
if (!defined('LOG_MAX_BYTES')) define('LOG_MAX_BYTES', 1048576);

/**
 * Number of rotated log files to retain (plus the active log).
 */
if (!defined('LOG_ROTATE_KEEP')) define('LOG_ROTATE_KEEP', 2);

/**
 * Rotate a log file when it exceeds LOG_MAX_BYTES.
 * Keeps LOG_ROTATE_KEEP historical copies as .1, .2, ...
 *
 * Rotation is best-effort: any I/O failure is silently swallowed so that a
 * full disk or permissions glitch cannot break the calling request.
 */
function rotateLogFileIfNeeded($logFile) {
    if (!is_string($logFile) || $logFile === '' || !file_exists($logFile)) {
        return;
    }
    $size = @filesize($logFile);
    if ($size === false || $size < LOG_MAX_BYTES) {
        return;
    }

    // Shift existing .N files back, drop the oldest
    for ($i = LOG_ROTATE_KEEP; $i >= 1; $i--) {
        $src = $logFile . '.' . $i;
        $dst = $logFile . '.' . ($i + 1);
        if ($i === LOG_ROTATE_KEEP && file_exists($src)) {
            @unlink($src);
            continue;
        }
        if (file_exists($src)) {
            @rename($src, $dst);
        }
    }
    @rename($logFile, $logFile . '.1');
}

/**
 * Log messages to file
 */
function logMessage($message, $level = 'info') {
    static $levelHierarchy = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];
    $configLevel = $levelHierarchy[strtolower(LOG_LEVEL)] ?? 1;
    $messageLevel = $levelHierarchy[$level] ?? 1;

    if ($messageLevel >= $configLevel) {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        $logFile = defined('LOG_FILE') ? LOG_FILE : __DIR__ . '/av_controls.log';
        rotateLogFileIfNeeded($logFile);
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
