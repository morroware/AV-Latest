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

        try {
            $commandResponse = makeApiCall('POST', $deviceIp, 'command/cli', $powerCommand, 'text/plain');
            $responseData = json_decode($commandResponse, true);

            if (isset($responseData['data']) && $responseData['data'] === 'OK') {
                $response['success'] = true;
                $response['message'] = "Power command sent successfully.";
            } else {
                $response['message'] = "Error sending power command: Unexpected response.";
            }
        } catch (Exception $e) {
            $response['message'] = "Error sending power command: " . $e->getMessage();
            logMessage("Error sending power command: " . $e->getMessage(), 'error');
        }

        return $response;
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

        if (empty($action)) {
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
            $payload = 'echo "' . $payloads[$action] . '" | ./fluxhandlerV2.sh';
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
            $isUnreachable = stripos($msg, 'Could not resolve') !== false
                || stripos($msg, 'Connection refused') !== false
                || stripos($msg, 'No route to host') !== false;

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
     * Check if all receivers are unreachable (legacy alias)
     * @deprecated Use areAllReceiversUnreachable() for clarity
     * @return bool True if all receivers are unreachable
     */
    public function checkReceiversReachable() {
        return $this->areAllReceiversUnreachable();
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
        $allReceiversUnreachable = $this->checkReceiversReachable();

        // Include the template
        include $this->zoneDir . '/template.php';
    }
}
