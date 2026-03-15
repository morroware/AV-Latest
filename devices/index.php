<?php
/**
 * AV System Dashboard
 * Professional control interface for Just Add Power devices
 * Dynamically reads device data from devices.json and zone configs
 *
 * @author Seth Morrow
 * @version 4.0.0
 * @copyright 2025-2026
 */
declare(strict_types=1);

class AVDashboard
{
    private array $deviceIps = [];
    private const REBOOT_ENDPOINT = '/cgi-bin/api/command/cli';
    private const REBOOT_COMMAND = 'reboot';
    private const REQUEST_TIMEOUT = 3;
    private const THROTTLE_DELAY = 250000; // microseconds

    public function __construct()
    {
        $this->deviceIps = $this->loadAllDeviceIps();
    }

    /**
     * Load all device IPs from devices.json and transmitters.txt
     */
    private function loadAllDeviceIps(): array
    {
        $ips = [];
        $devicesFile = dirname(__DIR__) . '/devices.json';

        if (file_exists($devicesFile)) {
            $data = json_decode(file_get_contents($devicesFile), true) ?? [];
            foreach (($data['receivers'] ?? []) as $rx) {
                if (!empty($rx['ip']) && ($rx['enabled'] ?? true)) {
                    $ips[] = $rx['ip'];
                }
            }
            foreach (($data['transmitters'] ?? []) as $tx) {
                if (!empty($tx['ip']) && ($tx['enabled'] ?? true)) {
                    $ips[] = $tx['ip'];
                }
            }
        }

        return array_unique($ips);
    }

    /**
     * Build device data array for frontend consumption
     */
    public function getDeviceData(): array
    {
        $devices = [];
        $devicesFile = dirname(__DIR__) . '/devices.json';
        $devicesData = [];

        if (file_exists($devicesFile)) {
            $devicesData = json_decode(file_get_contents($devicesFile), true) ?? [];
        }

        $zoneMap = $this->buildZoneMap();

        foreach (($devicesData['receivers'] ?? []) as $rx) {
            if (!($rx['enabled'] ?? true)) continue;
            $ip = $rx['ip'] ?? '';
            if (empty($ip)) continue;

            $devices[] = [
                'ip' => $ip,
                'name' => $rx['name'] ?? 'Unknown',
                'type' => 'rx',
                'deviceType' => $rx['type'] ?? 'video',
                'show_power' => $rx['show_power'] ?? false,
                'zone' => $zoneMap[$ip] ?? 'other',
            ];
        }

        // Add transmitters from devices.json (now includes IPs)
        foreach (($devicesData['transmitters'] ?? []) as $tx) {
            if (!($tx['enabled'] ?? true)) continue;
            $ip = $tx['ip'] ?? null;
            $devices[] = [
                'ip' => $ip,
                'name' => $tx['name'] ?? 'Unknown',
                'type' => 'tx',
                'deviceType' => 'transmitter',
                'channel' => $tx['channel'] ?? 0,
                'model' => $tx['model'] ?? '',
                'show_power' => false,
                'zone' => 'attic',
            ];
        }

        return $devices;
    }

    private function buildZoneMap(): array
    {
        $map = [];
        $zoneDir = dirname(__DIR__);
        $zones = ['bowling', 'bowlingbar', 'rink', 'jesters', 'facility', 'outside'];

        foreach ($zones as $zone) {
            $configFile = $zoneDir . '/' . $zone . '/config.php';
            if (!file_exists($configFile)) continue;

            $content = file_get_contents($configFile);
            if (preg_match_all("/['\"]ip['\"]\s*=>\s*['\"](\d+\.\d+\.\d+\.\d+)['\"]/", $content, $matches)) {
                foreach ($matches[1] as $ip) {
                    if (!isset($map[$ip])) {
                        $map[$ip] = $zone;
                    }
                }
            }
        }

        return $map;
    }

    private function rebootDevice(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'error' => 'Invalid IP address'];
        }

        $ch = curl_init("http://{$ip}" . self::REBOOT_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => self::REBOOT_COMMAND,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/plain',
                'Content-Length: ' . strlen(self::REBOOT_COMMAND)
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => true,
            'error' => '',
            'http_code' => $httpCode,
            'response' => $response
        ];
    }

    private function handleSingleReboot(): void
    {
        $ip = $_POST['device_ip'] ?? '';
        $result = $this->rebootDevice($ip);
        $this->jsonResponse([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Reboot command sent to {$ip}"
                : $result['error']
        ]);
    }

    private function handleBulkReboot(): void
    {
        $results = ['success' => 0, 'failed' => 0, 'failures' => []];

        foreach ($this->deviceIps as $ip) {
            $result = $this->rebootDevice($ip);
            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['failures'][] = ['ip' => $ip, 'error' => $result['error']];
            }
            usleep(self::THROTTLE_DELAY);
        }

        $this->jsonResponse([
            'success' => true,
            'message' => "Reboot completed: {$results['success']} successful",
            'details' => $results
        ]);
    }

    private function jsonResponse(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    private function checkDeviceStatus(string $ip): string
    {
        $ch = curl_init("http://{$ip}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => false
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($result !== false && $httpCode > 0) ? 'online' : 'offline';
    }

    private function handleStatusCheck(): void
    {
        $statuses = [];
        foreach ($this->deviceIps as $ip) {
            $statuses[$ip] = $this->checkDeviceStatus($ip);
        }

        $this->jsonResponse([
            'success' => true,
            'statuses' => $statuses
        ]);
    }

    public function handleRequest(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'reboot_device':
                $this->handleSingleReboot();
                break;
            case 'reboot_all':
                $this->handleBulkReboot();
                break;
            case 'check_status':
                $this->handleStatusCheck();
                break;
            default:
                $this->jsonResponse(['success' => false, 'message' => 'Invalid action']);
        }
    }
}

$dashboard = new AVDashboard();
$dashboard->handleRequest();
$deviceDataJson = json_encode($dashboard->getDeviceData(), JSON_THROW_ON_ERROR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Manager - Castle AV</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- Sticky top bar -->
    <header class="topbar">
        <div class="topbar-inner">
            <a href="/" class="topbar-brand" title="Back to Home">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Device Manager</span>
            </a>
            <div class="topbar-status" id="status-summary">
                <span class="status-dot dot-checking"></span>
                <span id="status-text">Checking devices...</span>
            </div>
            <div class="topbar-actions">
                <button id="refresh-btn" class="icon-btn" title="Refresh status">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                </button>
                <button id="reboot-all-btn" class="icon-btn icon-btn-danger" title="Reboot all devices">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/></svg>
                </button>
            </div>
        </div>
    </header>

    <main class="main">
        <!-- Search + Filters bar -->
        <div class="toolbar">
            <div class="search-wrap">
                <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                <input type="text" id="search" placeholder="Search by name, IP, or zone..." autocomplete="off">
                <button id="search-clear" class="search-clear" title="Clear search">&times;</button>
            </div>
            <div class="filters" id="filters">
                <button class="chip active" data-filter="all">All</button>
                <span class="chip-divider"></span>
                <button class="chip" data-filter="rx">Receivers</button>
                <button class="chip" data-filter="tx">Transmitters</button>
                <span class="chip-divider"></span>
                <button class="chip" data-filter="bowling">Bowling</button>
                <button class="chip" data-filter="bowlingbar">Bowling Bar</button>
                <button class="chip" data-filter="rink">Rink</button>
                <button class="chip" data-filter="jesters">Jesters</button>
                <button class="chip" data-filter="facility">Facility</button>
                <button class="chip" data-filter="outside">Outside</button>
            </div>
        </div>

        <!-- Device count & view toggle -->
        <div class="list-header">
            <span class="result-count" id="result-count">0 devices</span>
            <div class="view-toggle">
                <button class="vt-btn active" data-view="list" title="List view">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
                <button class="vt-btn" data-view="grid" title="Card view">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                </button>
            </div>
        </div>

        <!-- Device list (single container, JS renders everything) -->
        <div id="device-container" class="device-list"></div>

        <!-- Empty state -->
        <div id="empty-state" class="empty-state" style="display:none">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <p id="empty-message">No devices found</p>
        </div>
    </main>

    <!-- Reboot confirmation modal -->
    <div id="reboot-modal" class="modal-overlay">
        <div class="modal" role="dialog" aria-labelledby="modal-title">
            <div class="modal-top">
                <h3 id="modal-title">Reboot All Devices?</h3>
                <button class="modal-x" id="modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p>This sends a reboot command to <strong>every device</strong> on the network. All AV outputs will drop temporarily.</p>
            </div>
            <div class="modal-actions">
                <button id="cancel-reboot" class="btn btn-ghost">Cancel</button>
                <button id="confirm-reboot" class="btn btn-danger">Reboot All</button>
            </div>
        </div>
    </div>

    <!-- Single device reboot modal -->
    <div id="single-reboot-modal" class="modal-overlay">
        <div class="modal" role="dialog" aria-labelledby="single-modal-title">
            <div class="modal-top">
                <h3 id="single-modal-title">Reboot Device?</h3>
                <button class="modal-x single-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">
                <p>Send reboot command to <strong id="single-reboot-name"></strong> (<span id="single-reboot-ip"></span>)?</p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-ghost single-modal-close">Cancel</button>
                <button id="single-reboot-confirm" class="btn btn-danger">Reboot</button>
            </div>
        </div>
    </div>

    <!-- Toast container -->
    <div id="toasts" class="toast-stack"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>window.DEVICE_DATA = <?php echo $deviceDataJson; ?>;</script>
    <script src="script.js"></script>
</body>
</html>
