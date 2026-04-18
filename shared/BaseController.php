<?php
/**
 * Base Controller for AV Control System
 *
 * This class provides common AJAX request handling for all zone controllers.
 * It handles receiver control requests (channel, volume, power) and remote
 * control requests for IR commands.
 *
 * Zones can extend this class to add specialized functionality while
 * inheriting the standard request handling.
 *
 * @author Seth Morrow
 * @version 3.0 (Refactored)
 */

class BaseController {

    /**
     * Zone-specific directory path
     * @var string
     */
    protected $zoneDir;

    /**
     * Whether to use anti-popping measures for channel changes
     * Set to true for zones with sensitive audio equipment
     * @var bool
     */
    protected $useAntiPopping = false;

    /**
     * Constructor
     *
     * @param string $zoneDir Directory path for the zone (usually __DIR__ from calling script)
     */
    public function __construct($zoneDir) {
        $this->zoneDir = $zoneDir;
    }

    /**
     * Enable anti-popping measures for channel changes
     */
    public function enableAntiPopping() {
        $this->useAntiPopping = true;
    }

    /**
     * Check if request is an AJAX request
     *
     * @return bool
     */
    public function isAjaxRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /**
     * Handle incoming request
     * Call this method from the zone's index.php
     *
     * @return bool True if request was handled (AJAX), false otherwise (page load)
     */
    public function handleRequest() {
        if ($this->isAjaxRequest()) {
            $this->handleAjaxRequest();
            return true;
        }
        return false;
    }

    /**
     * Process AJAX requests
     */
    protected function handleAjaxRequest() {
        $response = ['success' => false, 'message' => ''];

        if (isset($_POST['receiver_ip'])) {
            $response = $this->handleReceiverRequest();
        } elseif (isset($_POST['device_url'])) {
            $response = $this->handleRemoteControlRequest();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Handle receiver control requests (channel, volume, power)
     *
     * @return array Response array
     */
    protected function handleReceiverRequest() {
        $response = ['success' => false, 'message' => ''];

        $deviceIp = sanitizeInput($_POST['receiver_ip'], 'ip');

        // Find receiver configuration
        $receiverConfig = $this->findReceiverConfig($deviceIp);

        // Handle power command
        if (isset($_POST['power_command'])) {
            return $this->handlePowerCommand($deviceIp, $receiverConfig);
        }

        // Handle volume-only update
        if (isset($_POST['volume']) && !isset($_POST['channel'])) {
            return $this->handleVolumeUpdate($deviceIp);
        }

        // Handle channel-only update
        if (isset($_POST['channel']) && !isset($_POST['volume'])) {
            return $this->handleChannelUpdate($deviceIp);
        }

        // Handle combined channel and volume update (legacy)
        return $this->handleCombinedUpdate($deviceIp);
    }

    /**
     * Find receiver configuration by IP
     *
     * @param string $deviceIp Device IP address
     * @return array|null Receiver configuration or null
     */
    protected function findReceiverConfig($deviceIp) {
        if (!defined('RECEIVERS')) {
            return null;
        }

        foreach (RECEIVERS as $name => $config) {
            if ($config['ip'] === $deviceIp) {
                return $config;
            }
        }
        return null;
    }

    /**
     * Handle power command
     *
     * @param string $deviceIp Device IP
     * @param array|null $receiverConfig Receiver configuration
     * @return array Response
     */
    protected function handlePowerCommand($deviceIp, $receiverConfig) {
        $response = ['success' => false, 'message' => ''];

        if (!$receiverConfig || !$receiverConfig['show_power']) {
            $response['message'] = "Power control not enabled for this receiver.";
            return $response;
        }

        $powerCommand = sanitizeInput($_POST['power_command'], 'string');
        $rawCommand = $_POST['power_command'] ?? '';

        logMessage("POWER DEBUG [{$deviceIp}]: raw='{$rawCommand}' sanitized='{$powerCommand}'", 'debug');

        try {
            $commandResponse = makeApiCall('POST', $deviceIp, 'command/cli', $powerCommand, 'text/plain');

            logMessage("POWER DEBUG [{$deviceIp}]: response='{$commandResponse}'", 'debug');

            // CEC CLI commands (cec_power_on_tv, cec_tv_on.sh, cec_watch_me.sh, etc.)
            // execute on the JAP device before the HTTP response returns.  The
            // device often replies OK, but some CEC helpers (notably
            // cec_watch_me.sh) take longer than API_TIMEOUT to finish the CEC
            // handshake with the display and return a slow/empty response.
            // A missing `data: OK` body therefore does NOT mean the command
            // failed — the CEC frame was already placed on the bus.  Treat
            // any completed HTTP round-trip as a successful dispatch.
            $response['success'] = true;
            $response['message'] = "Power command sent successfully.";
        } catch (Exception $e) {
            // Fire-and-forget semantics (matches handleRemoteControlRequest).
            // When a TCP connection was established, the CEC command was
            // dispatched — any later transport failure (timeout on read,
            // connection reset, empty reply) means the HTTP response was
            // lost or slow, NOT that the command failed.  The only true
            // failure is when we never reached the device: DNS failure,
            // connect refused, connect timeout, network unreachable.
            //
            // makeApiCall() prepends "[UNREACHABLE] " to the exception
            // message when CURLINFO_CONNECT_TIME was 0 (no TCP connection
            // ever completed).  We also keep string patterns as a
            // defense-in-depth fallback in case the connect-time check
            // misses an edge case (e.g. "Failed to connect to ... port ..."
            // covers both CURLE_COULDNT_CONNECT and connect-phase
            // CURLE_OPERATION_TIMEDOUT).
            $msg = $e->getMessage();
            $isUnreachable = $this->isTransportUnreachable($msg);

            logMessage("POWER DEBUG [{$deviceIp}]: exception='{$msg}'", 'debug');

            if ($isUnreachable) {
                $response['message'] = "Device unreachable: " . $msg;
                logMessage("Power command failed — device unreachable: {$msg}", 'error');
            } else {
                $response['success'] = true;
                $response['message'] = "Power command sent successfully.";
                logMessage("Power command dispatched (non-fatal cURL issue): {$msg}", 'debug');
            }
        }

        return $response;
    }

    /**
     * Invalidate the shared receiver-status cache for a device IP.
     * Best-effort: failures are silent.  Called after any command that
     * changes device state so subsequent lazy-loads return fresh data.
     */
    protected function invalidateReceiverStatusCache($deviceIp) {
        $cachePath = __DIR__ . '/.cache';
        if (!is_dir($cachePath)) return;
        foreach (glob($cachePath . '/receiver_status_' . $deviceIp . '.*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    /**
     * Handle volume-only update
     *
     * @param string $deviceIp Device IP
     * @return array Response
     */
    protected function handleVolumeUpdate($deviceIp) {
        $response = ['success' => false, 'message' => ''];

        if (!supportsVolumeControl($deviceIp)) {
            $response['message'] = "Device does not support volume control";
            logMessage("Volume control not supported for IP: $deviceIp", 'info');
            return $response;
        }

        $selectedVolume = sanitizeInput($_POST['volume'], 'int', ['min' => MIN_VOLUME, 'max' => MAX_VOLUME]);

        // sanitizeInput returns null on failure, or the validated integer (including 0)
        if ($selectedVolume === null) {
            $response['message'] = "Invalid volume value";
            return $response;
        }

        try {
            $volumeResponse = setVolume($deviceIp, $selectedVolume);
            $response['success'] = $volumeResponse;
            $response['message'] = "Volume: " . ($volumeResponse ? "Successfully updated" : "Update failed");
            logMessage("Volume updated for $deviceIp to $selectedVolume - Result: " . ($volumeResponse ? "Success" : "Failed"), 'info');
            if ($volumeResponse) {
                $this->invalidateReceiverStatusCache($deviceIp);
            }
        } catch (Exception $e) {
            $response['message'] = "Error updating volume: " . $e->getMessage();
            logMessage("Error updating volume: " . $e->getMessage(), 'error');
        }

        return $response;
    }

    /**
     * Handle channel-only update
     *
     * @param string $deviceIp Device IP
     * @return array Response
     */
    protected function handleChannelUpdate($deviceIp) {
        $response = ['success' => false, 'message' => ''];

        $selectedChannel = sanitizeInput($_POST['channel'], 'int');

        // Use strict null check - channel 0 may be valid
        if ($selectedChannel === null) {
            $response['message'] = "Invalid channel value";
            return $response;
        }

        try {
            // Use anti-popping if enabled for this zone
            if ($this->useAntiPopping && function_exists('setChannelWithoutPopping')) {
                $channelResponse = setChannelWithoutPopping($deviceIp, $selectedChannel);
            } else {
                $channelResponse = setChannel($deviceIp, $selectedChannel);
            }

            $response['success'] = $channelResponse;
            $response['message'] = "Channel: " . ($channelResponse ? "Successfully updated" : "Update failed");
            logMessage("Channel updated for $deviceIp to $selectedChannel - Result: " . ($channelResponse ? "Success" : "Failed"), 'info');
            if ($channelResponse) {
                $this->invalidateReceiverStatusCache($deviceIp);
            }
        } catch (Exception $e) {
            $response['message'] = "Error updating channel: " . $e->getMessage();
            logMessage("Error updating channel: " . $e->getMessage(), 'error');
        }

        return $response;
    }

    /**
     * Handle combined channel and volume update (legacy)
     *
     * @param string $deviceIp Device IP
     * @return array Response
     */
    protected function handleCombinedUpdate($deviceIp) {
        $response = ['success' => false, 'message' => ''];

        $selectedChannel = sanitizeInput($_POST['channel'], 'int');

        // Use strict null check for channel validation
        if ($selectedChannel === null || !$deviceIp) {
            $response['message'] = "Invalid channel or device";
            return $response;
        }

        try {
            // Use anti-popping if enabled
            if ($this->useAntiPopping && function_exists('setChannelWithoutPopping')) {
                $channelResponse = setChannelWithoutPopping($deviceIp, $selectedChannel);
            } else {
                $channelResponse = setChannel($deviceIp, $selectedChannel);
            }

            $channelSuccess = (bool)$channelResponse;
            $response['message'] .= "Channel: " . ($channelSuccess ? "Successfully updated" : "Update failed") . "\n";

            // Handle volume if supported
            $volumeSuccess = true; // Default to true if not applicable
            if (supportsVolumeControl($deviceIp) && isset($_POST['volume'])) {
                $selectedVolume = sanitizeInput($_POST['volume'], 'int', ['min' => MIN_VOLUME, 'max' => MAX_VOLUME]);
                if ($selectedVolume !== null) {
                    $volumeResponse = setVolume($deviceIp, $selectedVolume);
                    $volumeSuccess = (bool)$volumeResponse;
                    $response['message'] .= "Volume: " . ($volumeSuccess ? "Successfully updated" : "Update failed") . "\n";
                }
            }

            // Only report success if channel change succeeded (volume is secondary)
            $response['success'] = $channelSuccess;
            if ($channelSuccess || $volumeSuccess) {
                $this->invalidateReceiverStatusCache($deviceIp);
            }
        } catch (Exception $e) {
            $response['message'] = "Error updating settings: " . $e->getMessage();
            logMessage("Error updating settings: " . $e->getMessage(), 'error');
        }

        return $response;
    }

    /**
     * Handle remote control (IR) requests
     *
     * @return array Response
     */
    protected function handleRemoteControlRequest() {
        $response = ['success' => false, 'message' => ''];

        $deviceUrl = rtrim($_POST['device_url'], '/');
        $action = $_POST['action'] ?? '';

        // Validate device_url to prevent SSRF attacks
        $urlHost = $deviceUrl;
        if (preg_match('#^https?://#i', $urlHost)) {
            $parsed = parse_url($urlHost);
            $urlHost = $parsed['host'] ?? '';
        }
        $urlHost = preg_replace('/:[0-9]+$/', '', $urlHost);
        if (!filter_var($urlHost, FILTER_VALIDATE_IP)) {
            $response['message'] = "Invalid device URL";
            return $response;
        }

        // Use strict string check — empty() treats the literal string "0"
        // as empty, which blocks the digit-0 IR command and breaks
        // multi-digit favorites like channel 70.
        if ($action === '' || $action === null) {
            $response['message'] = "No action specified";
            return $response;
        }

        // Load payloads from zone-specific file
        $payloadsFile = $this->zoneDir . '/payloads.txt';
        $payloads = loadPayloads($payloadsFile);

        if (!isset($payloads[$action])) {
            $response['message'] = "Invalid action: " . htmlspecialchars($action);
            return $response;
        }

        try {
            // Escape payload content for shell safety.  payloads.txt is
            // admin-controlled but escapeshellarg() removes any risk of a
            // stray quote, backtick, or $ in a payload line being interpreted
            // by the remote shell.
            $payload = 'echo ' . escapeshellarg($payloads[$action]) . ' | ./fluxhandlerV2.sh';
            $result = makeApiCall('POST', $deviceUrl, 'command/cli', $payload, 'text/plain');
            $response['success'] = true;
            $response['message'] = "Command sent successfully";
        } catch (Exception $e) {
            // IR commands are fire-and-forget: the shell pipeline
            //   echo "<payload>" | ./fluxhandlerV2.sh
            // executes the IR transmission before the HTTP response is
            // generated.  Therefore HTTP errors, timeouts, connection
            // resets, and empty replies do NOT mean the command failed —
            // only that the response was lost or delayed.
            //
            // The only true failure is when the request never reached the
            // device at all (connection refused, DNS failure), meaning
            // fluxhandlerV2.sh never ran.
            $msg = $e->getMessage();
            $isUnreachable = $this->isTransportUnreachable($msg);

            if ($isUnreachable) {
                $response['message'] = "Device unreachable";
                logMessage("IR command failed — device unreachable: " . $msg, 'error');
            } else {
                // HTTP error, timeout, reset, empty reply — command was sent
                $response['success'] = true;
                $response['message'] = "Command sent successfully";
                logMessage("IR command sent (non-fatal cURL issue): " . $msg, 'debug');
            }
        }

        return $response;
    }

    /**
     * Classify a makeApiCall exception message as "device unreachable"
     * (request never arrived) vs "dispatched but response lost/slow".
     *
     * Primary signal: the "[UNREACHABLE]" prefix attached by makeApiCall()
     * when CURLINFO_CONNECT_TIME was 0 — that's a guaranteed connect-phase
     * failure.  The string-match list is a defense-in-depth fallback for
     * callers / cURL builds where the prefix might be absent.
     *
     * @param string $msg Exception message
     * @return bool
     */
    protected function isTransportUnreachable($msg) {
        if (strpos($msg, '[UNREACHABLE]') !== false) {
            return true;
        }
        // "Failed to connect to HOST port N after M ms: ..." covers both
        // CURLE_COULDNT_CONNECT (7) for connection refused and the
        // connect-phase variant of CURLE_OPERATION_TIMEDOUT (28).
        $needles = [
            'Could not resolve',
            'Connection refused',
            'No route to host',
            'Failed to connect',
            'Couldn\'t connect to server',
            'Network is unreachable',
            'Host is unreachable',
            'Host is down',
            'Name or service not known',
        ];
        foreach ($needles as $needle) {
            if (stripos($msg, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if all receivers are unreachable
     *
     * With lazy loading enabled, this check is skipped to avoid blocking page load.
     * Individual receiver status is checked asynchronously via JavaScript.
     *
     * @return bool Always returns false (lazy loading handles unreachable state)
     */
    public function areAllReceiversUnreachable() {
        // With lazy loading, we don't block page load for reachability checks
        // Each receiver's status is fetched asynchronously
        return false;
    }

    /**
     * Include the zone template
     *
     * @param array $vars Variables to pass to template
     */
    public function renderTemplate($vars = []) {
        // Make variables available to template
        extract($vars);

        // Check reachability
        $allReceiversUnreachable = $this->areAllReceiversUnreachable();

        // Include the template
        include $this->zoneDir . '/template.php';
    }
}
