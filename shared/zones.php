<?php
/**
 * Zone Management Utilities
 *
 * Provides functions for loading, validating, and managing zones.
 * Uses zones.json as the single source of truth for zone configuration.
 *
 * @author Seth Morrow
 * @version 1.1
 */

define('ZONES_CONFIG_FILE', dirname(__DIR__) . '/zones.json');

/**
 * Static cache for zones configuration
 * Prevents multiple file reads within the same request
 */
$_zonesConfigCache = null;

/**
 * Load zones configuration from JSON file with caching
 *
 * @param bool $forceReload Force reload from file (after save operations)
 * @return array The zones configuration or empty array on failure
 */
function loadZonesConfig(bool $forceReload = false): array
{
    global $_zonesConfigCache;

    // Return cached config if available and not forcing reload
    if ($_zonesConfigCache !== null && !$forceReload) {
        return $_zonesConfigCache;
    }

    if (!file_exists(ZONES_CONFIG_FILE)) {
        $_zonesConfigCache = ['zones' => [], 'specialLinks' => [], 'settings' => []];
        return $_zonesConfigCache;
    }

    $content = file_get_contents(ZONES_CONFIG_FILE);
    $config = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Failed to parse zones.json: ' . json_last_error_msg());
        $_zonesConfigCache = ['zones' => [], 'specialLinks' => [], 'settings' => []];
        return $_zonesConfigCache;
    }

    $_zonesConfigCache = $config;
    return $_zonesConfigCache;
}

/**
 * Clear the zones configuration cache
 * Call this after modifying the zones config file
 */
function clearZonesConfigCache(): void
{
    global $_zonesConfigCache;
    $_zonesConfigCache = null;
}

/**
 * Save zones configuration to JSON file with atomic write and file locking
 *
 * @param array $config The configuration to save
 * @return bool Success status
 */
function saveZonesConfig(array $config): bool
{
    $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        error_log('Failed to encode zones config: ' . json_last_error_msg());
        return false;
    }

    // Use atomic write with temp file to prevent corruption
    $tempFile = ZONES_CONFIG_FILE . '.tmp.' . getmypid();
    $lockFile = ZONES_CONFIG_FILE . '.lock';

    // Acquire exclusive lock
    $lockHandle = fopen($lockFile, 'c');
    if ($lockHandle === false) {
        error_log('Failed to open lock file for zones config');
        return false;
    }

    // Wait up to 5 seconds for lock
    $lockAcquired = false;
    for ($i = 0; $i < 50; $i++) {
        if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
            $lockAcquired = true;
            break;
        }
        usleep(100000); // 100ms
    }

    if (!$lockAcquired) {
        fclose($lockHandle);
        error_log('Failed to acquire lock for zones config (timeout)');
        return false;
    }

    try {
        // Write to temp file first
        $result = file_put_contents($tempFile, $json);

        if ($result === false) {
            throw new Exception('Failed to write temp file');
        }

        // Atomic rename
        if (!rename($tempFile, ZONES_CONFIG_FILE)) {
            throw new Exception('Failed to rename temp file');
        }

        // Clear cache so next read gets fresh data
        clearZonesConfigCache();

        return true;
    } catch (Exception $e) {
        error_log('Failed to save zones config: ' . $e->getMessage());
        @unlink($tempFile); // Clean up temp file
        return false;
    } finally {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}

/**
 * Get list of enabled zone IDs
 *
 * @return array List of zone IDs
 */
function getEnabledZoneIds(): array
{
    $config = loadZonesConfig();
    $zones = [];

    foreach ($config['zones'] ?? [] as $zone) {
        if (!empty($zone['enabled']) && !empty($zone['id'])) {
            $zones[] = $zone['id'];
        }
    }

    return $zones;
}

/**
 * Get list of zones visible in navigation
 *
 * @return array List of zone configurations
 */
function getNavigationZones(): array
{
    $config = loadZonesConfig();
    $zones = [];

    foreach ($config['zones'] ?? [] as $zone) {
        if (!empty($zone['enabled']) && !empty($zone['showInNav'])) {
            $zones[] = $zone;
        }
    }

    return $zones;
}

/**
 * Get special links for navigation
 *
 * @return array List of special link configurations
 */
function getSpecialLinks(): array
{
    $config = loadZonesConfig();
    $links = [];

    foreach ($config['specialLinks'] ?? [] as $link) {
        if (!empty($link['enabled']) && !empty($link['showInNav'])) {
            $links[] = $link;
        }
    }

    return $links;
}

/**
 * Check if a zone ID is valid (exists and is enabled)
 *
 * @param string $zoneId The zone ID to validate
 * @return bool True if valid
 */
function isValidZone(string $zoneId): bool
{
    return in_array($zoneId, getEnabledZoneIds(), true);
}

/**
 * Get zone configuration by ID
 *
 * @param string $zoneId The zone ID
 * @return array|null Zone configuration or null if not found
 */
function getZoneById(string $zoneId): ?array
{
    $config = loadZonesConfig();

    foreach ($config['zones'] ?? [] as $zone) {
        if (($zone['id'] ?? '') === $zoneId) {
            return $zone;
        }
    }

    return null;
}

/**
 * Add a new zone to the configuration
 *
 * @param array $zoneData Zone configuration data
 * @return array Result with 'success' and 'message' keys
 */
function addZone(array $zoneData): array
{
    $config = loadZonesConfig();

    // Validate required fields
    if (empty($zoneData['id'])) {
        return ['success' => false, 'message' => 'Zone ID is required'];
    }

    // Sanitize zone ID (lowercase, alphanumeric only)
    $zoneId = preg_replace('/[^a-z0-9]/', '', strtolower($zoneData['id']));
    if (empty($zoneId)) {
        return ['success' => false, 'message' => 'Zone ID must contain alphanumeric characters'];
    }

    // Check if zone already exists
    foreach ($config['zones'] ?? [] as $zone) {
        if (($zone['id'] ?? '') === $zoneId) {
            return ['success' => false, 'message' => 'Zone ID already exists'];
        }
    }

    // Create new zone entry
    $newZone = [
        'id' => $zoneId,
        'name' => $zoneData['name'] ?? ucfirst($zoneId),
        'description' => $zoneData['description'] ?? '',
        'enabled' => true,
        'showInNav' => $zoneData['showInNav'] ?? true,
        'icon' => $zoneData['icon'] ?? 'default',
        'color' => $zoneData['color'] ?? ($config['settings']['defaultColor'] ?? '#00C853')
    ];

    $config['zones'][] = $newZone;

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Zone added successfully', 'zone' => $newZone];
}

/**
 * Update an existing zone
 *
 * @param string $zoneId The zone ID to update
 * @param array $updates The fields to update
 * @return array Result with 'success' and 'message' keys
 */
function updateZone(string $zoneId, array $updates): array
{
    $config = loadZonesConfig();
    $found = false;

    foreach ($config['zones'] as &$zone) {
        if (($zone['id'] ?? '') === $zoneId) {
            // Don't allow changing the ID
            unset($updates['id']);

            // Merge updates
            $zone = array_merge($zone, $updates);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'message' => 'Zone not found'];
    }

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Zone updated successfully'];
}

/**
 * Remove a zone from configuration (does not delete directory)
 *
 * @param string $zoneId The zone ID to remove
 * @param bool $deleteDirectory Whether to also delete the zone directory
 * @return array Result with 'success' and 'message' keys
 */
function removeZone(string $zoneId, bool $deleteDirectory = false): array
{
    $config = loadZonesConfig();
    $found = false;

    foreach ($config['zones'] as $index => $zone) {
        if (($zone['id'] ?? '') === $zoneId) {
            array_splice($config['zones'], $index, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'message' => 'Zone not found'];
    }

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    // Optionally delete the directory
    if ($deleteDirectory) {
        $zoneDir = dirname(__DIR__) . '/' . $zoneId;
        if (is_dir($zoneDir)) {
            if (!deleteDirectory($zoneDir)) {
                return [
                    'success' => true,
                    'message' => 'Zone removed from config but failed to delete directory'
                ];
            }
        }
    }

    return ['success' => true, 'message' => 'Zone removed successfully'];
}

/**
 * Recursively delete a directory
 *
 * @param string $dir Directory path
 * @return bool Success status
 */
function deleteDirectory(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }

    $files = array_diff(scandir($dir), ['.', '..']);

    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

/**
 * Create zone directory with template files
 *
 * @param string $zoneId The zone ID
 * @param string|null $copyFrom Optional zone ID to copy from
 * @return array Result with 'success' and 'message' keys
 */
function createZoneDirectory(string $zoneId, ?string $copyFrom = null): array
{
    $baseDir = dirname(__DIR__);
    $zoneDir = $baseDir . '/' . $zoneId;

    if (is_dir($zoneDir)) {
        return ['success' => false, 'message' => 'Zone directory already exists'];
    }

    if (!mkdir($zoneDir, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create zone directory'];
    }

    // If copying from another zone
    if ($copyFrom && is_dir($baseDir . '/' . $copyFrom)) {
        $sourceDir = $baseDir . '/' . $copyFrom;
        $filesToCopy = ['config.php', 'template.php', 'index.php', 'transmitters.txt',
                        'payloads.txt', 'favorites.ini', 'WLEDlist.ini'];

        foreach ($filesToCopy as $file) {
            if (file_exists($sourceDir . '/' . $file)) {
                if (!copy($sourceDir . '/' . $file, $zoneDir . '/' . $file)) {
                    // Rollback: delete the incomplete zone directory
                    deleteDirectory($zoneDir);
                    return ['success' => false, 'message' => 'Failed to copy file: ' . $file];
                }
            }
        }

        return ['success' => true, 'message' => 'Zone directory created from template'];
    }

    // Create default template files
    $templateDir = $baseDir . '/zone-templates';

    // Create from templates if available, otherwise use defaults
    if (is_dir($templateDir)) {
        $files = scandir($templateDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (!copy($templateDir . '/' . $file, $zoneDir . '/' . $file)) {
                // Rollback: delete the incomplete zone directory
                deleteDirectory($zoneDir);
                return ['success' => false, 'message' => 'Failed to copy template file: ' . $file];
            }
        }
    } else {
        // Create minimal default files
        createDefaultZoneFiles($zoneDir, $zoneId);
    }

    return ['success' => true, 'message' => 'Zone directory created successfully'];
}

/**
 * Create default zone files
 *
 * @param string $zoneDir Zone directory path
 * @param string $zoneId Zone ID
 */
function createDefaultZoneFiles(string $zoneDir, string $zoneId): void
{
    $zoneName = ucfirst($zoneId);

    // config.php
    $configContent = <<<PHP
<?php
/**
 * {$zoneName} Zone Configuration
 * Generated: {DATE}
 */

const RECEIVERS = [
    // Add receivers here
    // 'Device Name' => ['ip' => '192.168.8.XX', 'show_power' => false],
];

const TRANSMITTERS = [
    // Add transmitters here
    // 'Input Name' => channel_number,
];

const MAX_VOLUME = 11;
const MIN_VOLUME = 0;
const VOLUME_STEP = 1;
const HOME_URL = '/';
const LOG_LEVEL = 'error';
const API_TIMEOUT = 2;

const REMOTE_CONTROL_COMMANDS = [
    'power', 'guide', 'up', 'down', 'left', 'right', 'select',
    'channel_up', 'channel_down', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'last', 'exit'
];

const VOLUME_CONTROL_MODELS = ['3G+4+ TX', '3G+AVP RX', '3G+AVP TX', '3G+WP4 TX', '2G/3G SX'];

const ERROR_MESSAGES = [
    'connection' => 'Unable to connect to %s (%s). Please check the connection and try again.',
    'global' => 'Unable to connect to any receivers. Please check your network connection and try again.',
    'remote' => 'Unable to send remote command. Please try again.',
];

const LOG_FILE = __DIR__ . '/av_controls.log';

PHP;
    $configContent = str_replace('{DATE}', date('Y-m-d H:i:s'), $configContent);
    file_put_contents($zoneDir . '/config.php', $configContent);

    // index.php
    $indexContent = <<<'PHP'
<?php
/**
 * Zone AV Control Interface
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/shared/utils.php';
require_once dirname(__DIR__) . '/shared/BaseController.php';

$controller = new BaseController(__DIR__);

if ($controller->handleRequest()) {
    // AJAX request was handled
}

$allReceiversUnreachable = $controller->checkReceiversReachable();

include __DIR__ . '/template.php';

ob_end_flush();

PHP;
    file_put_contents($zoneDir . '/index.php', $indexContent);

    // template.php
    $templateContent = <<<'PHP'
<?php $zoneName = basename(__DIR__); $settingsPath = "../settings.php?zone=" . urlencode($zoneName); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Castle AV Control System</title>
    <link rel="stylesheet" href="../shared/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- LiveCode browser widget compatibility layer -->
    <script src="../livecode-compat.js"></script>
    <script src="../shared/script.js"></script>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <div class="logo-container">
                    <img src="../logo.png" alt="Castle AV Controls Logo" class="logo" onclick="handleLogoClick(event)" style="cursor: pointer">
                </div>
                <h1><?php echo ucfirst($zoneName); ?> AV Controls</h1>
            </div>

            <div class="header-buttons">
                <a href="../index.html" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
            </div>
        </header>

        <?php if ($allReceiversUnreachable): ?>
            <div class="global-error"><?php echo ERROR_MESSAGES['global']; ?></div>
        <?php endif; ?>

        <div id="response-message"></div>

        <div class="main-container">
            <section id="av-controls" class="section">
                <div class="receivers-wrapper">
                    <?php echo generateReceiverForms(); ?>
                </div>
            </section>

            <section id="remote-control" class="section">
                <h2>Remote Control</h2>

                <div class="remote-selectors">
                    <div id="transmitter-select">
                        Select Transmitter: Loading transmitters...
                    </div>

                    <div id="favorite-channels-select">
                        Favorite Channels: Loading favorites...
                    </div>
                </div>

                <div class="remote-container">
                    <div class="button-row">
                        <button onclick="sendCommand('power')">Power</button>
                        <button onclick="sendCommand('guide')">Guide</button>
                    </div>

                    <div class="navigation-pad">
                        <button onclick="sendCommand('up')">&#9650;</button>
                        <div class="nav-row">
                            <button onclick="sendCommand('left')">&#9664;</button>
                            <button onclick="sendCommand('select')">OK</button>
                            <button onclick="sendCommand('right')">&#9654;</button>
                        </div>
                        <button onclick="sendCommand('down')">&#9660;</button>
                    </div>

                    <div class="button-row">
                        <button onclick="sendCommand('channel_up')">CH +</button>
                        <button onclick="sendCommand('channel_down')">CH -</button>
                    </div>

                    <div class="number-pad">
                        <button onclick="sendCommand('1')">1</button>
                        <button onclick="sendCommand('2')">2</button>
                        <button onclick="sendCommand('3')">3</button>
                        <button onclick="sendCommand('4')">4</button>
                        <button onclick="sendCommand('5')">5</button>
                        <button onclick="sendCommand('6')">6</button>
                        <button onclick="sendCommand('7')">7</button>
                        <button onclick="sendCommand('8')">8</button>
                        <button onclick="sendCommand('9')">9</button>
                        <button onclick="sendCommand('last')">Last</button>
                        <button onclick="sendCommand('0')">0</button>
                        <button onclick="sendCommand('exit')">Exit</button>
                    </div>
                </div>

                <div id="error-message" class="error-message">
                    <strong>Error!</strong> <span id="error-text"></span>
                </div>
            </section>
        </div>
    </div>

    <script>
        function handleLogoClick(event) {
            if (event.ctrlKey) {
                window.location.href = '<?php echo $settingsPath; ?>';
            }
        }
    </script>
</body>
</html>

PHP;
    file_put_contents($zoneDir . '/template.php', $templateContent);

    // transmitters.txt (empty)
    file_put_contents($zoneDir . '/transmitters.txt', "");

    // payloads.txt (empty with header)
    file_put_contents($zoneDir . '/payloads.txt', "; IR command payloads\n; Format: command=sendir,...\n");

    // favorites.ini
    file_put_contents($zoneDir . '/favorites.ini', "; Favorite Channels Configuration\n; Format: ChannelNumber=ChannelName\n");

    // WLEDlist.ini
    file_put_contents($zoneDir . '/WLEDlist.ini', "; WLED Device IP Addresses\n; Format: ip1 = \"192.168.X.X\"\n");
}

/**
 * Duplicate an existing zone
 *
 * @param string $sourceZoneId Source zone to copy
 * @param string $newZoneId New zone ID
 * @param string $newZoneName New zone display name
 * @return array Result with 'success' and 'message' keys
 */
function duplicateZone(string $sourceZoneId, string $newZoneId, string $newZoneName): array
{
    // Validate source zone exists
    $sourceZone = getZoneById($sourceZoneId);
    if (!$sourceZone) {
        return ['success' => false, 'message' => 'Source zone not found'];
    }

    // Sanitize new zone ID
    $newZoneId = preg_replace('/[^a-z0-9]/', '', strtolower($newZoneId));
    if (empty($newZoneId)) {
        return ['success' => false, 'message' => 'Invalid zone ID'];
    }

    // Check if new zone already exists in config
    if (getZoneById($newZoneId)) {
        return ['success' => false, 'message' => 'Zone ID already exists'];
    }

    // Check if directory already exists on filesystem
    $baseDir = dirname(__DIR__);
    if (is_dir($baseDir . '/' . $newZoneId)) {
        return ['success' => false, 'message' => 'Zone directory already exists on filesystem'];
    }

    // CREATE DIRECTORY FIRST to prevent race condition
    $dirResult = createZoneDirectory($newZoneId, $sourceZoneId);
    if (!$dirResult['success']) {
        return $dirResult;
    }

    // Directory created successfully, now add to configuration
    $result = addZone([
        'id' => $newZoneId,
        'name' => $newZoneName ?: ucfirst($newZoneId),
        'description' => 'Duplicated from ' . $sourceZone['name'],
        'showInNav' => true,
        'icon' => $sourceZone['icon'] ?? 'default',
        'color' => $sourceZone['color'] ?? '#00C853'
    ]);

    if (!$result['success']) {
        // Rollback: delete the directory we just created
        deleteDirectory($baseDir . '/' . $newZoneId);
        return $result;
    }

    return ['success' => true, 'message' => 'Zone duplicated successfully'];
}

/**
 * Reorder zones in the configuration
 *
 * @param array $orderedIds Array of zone IDs in desired order
 * @return array Result with 'success' and 'message' keys
 */
function reorderZones(array $orderedIds): array
{
    $config = loadZonesConfig();
    $currentZones = $config['zones'] ?? [];
    $reorderedZones = [];

    // Build map of existing zones
    $zoneMap = [];
    foreach ($currentZones as $zone) {
        $zoneMap[$zone['id']] = $zone;
    }

    // Reorder based on provided IDs
    foreach ($orderedIds as $id) {
        if (isset($zoneMap[$id])) {
            $reorderedZones[] = $zoneMap[$id];
            unset($zoneMap[$id]);
        }
    }

    // Append any zones not in the order list
    foreach ($zoneMap as $zone) {
        $reorderedZones[] = $zone;
    }

    $config['zones'] = $reorderedZones;

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Zones reordered successfully'];
}

// ============================================================================
// Quick Links (Custom URL Buttons) Management
// ============================================================================

/**
 * Get a quick link by ID
 *
 * @param string $linkId The link ID
 * @return array|null Link configuration or null if not found
 */
function getQuickLinkById(string $linkId): ?array
{
    $config = loadZonesConfig();

    foreach ($config['specialLinks'] ?? [] as $link) {
        if (($link['id'] ?? '') === $linkId) {
            return $link;
        }
    }

    return null;
}

/**
 * Add a new quick link
 *
 * @param array $linkData Link configuration data
 * @return array Result with 'success' and 'message' keys
 */
function addQuickLink(array $linkData): array
{
    $config = loadZonesConfig();

    // Validate required fields
    if (empty($linkData['id'])) {
        return ['success' => false, 'message' => 'Link ID is required'];
    }

    if (empty($linkData['url'])) {
        return ['success' => false, 'message' => 'URL is required'];
    }

    // Sanitize link ID (lowercase, alphanumeric and hyphens only)
    $linkId = preg_replace('/[^a-z0-9\-]/', '', strtolower($linkData['id']));
    if (empty($linkId)) {
        return ['success' => false, 'message' => 'Link ID must contain alphanumeric characters'];
    }

    // Check if link already exists
    foreach ($config['specialLinks'] ?? [] as $link) {
        if (($link['id'] ?? '') === $linkId) {
            return ['success' => false, 'message' => 'Link ID already exists'];
        }
    }

    // Create new link entry
    $newLink = [
        'id' => $linkId,
        'name' => $linkData['name'] ?? ucfirst($linkId),
        'url' => $linkData['url'],
        'description' => $linkData['description'] ?? '',
        'enabled' => true,
        'showInNav' => $linkData['showInNav'] ?? true,
        'color' => $linkData['color'] ?? '#2196F3',
        'icon' => $linkData['icon'] ?? 'link',
        'openInNewTab' => $linkData['openInNewTab'] ?? false
    ];

    if (!isset($config['specialLinks'])) {
        $config['specialLinks'] = [];
    }

    $config['specialLinks'][] = $newLink;

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Quick link added successfully', 'link' => $newLink];
}

/**
 * Update an existing quick link
 *
 * @param string $linkId The link ID to update
 * @param array $updates The fields to update
 * @return array Result with 'success' and 'message' keys
 */
function updateQuickLink(string $linkId, array $updates): array
{
    $config = loadZonesConfig();
    $found = false;

    foreach ($config['specialLinks'] as &$link) {
        if (($link['id'] ?? '') === $linkId) {
            // Don't allow changing the ID
            unset($updates['id']);

            // Merge updates
            $link = array_merge($link, $updates);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'message' => 'Quick link not found'];
    }

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Quick link updated successfully'];
}

/**
 * Remove a quick link from configuration
 *
 * @param string $linkId The link ID to remove
 * @return array Result with 'success' and 'message' keys
 */
function removeQuickLink(string $linkId): array
{
    $config = loadZonesConfig();
    $found = false;

    foreach ($config['specialLinks'] as $index => $link) {
        if (($link['id'] ?? '') === $linkId) {
            array_splice($config['specialLinks'], $index, 1);
            $found = true;
            break;
        }
    }

    if (!$found) {
        return ['success' => false, 'message' => 'Quick link not found'];
    }

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Quick link removed successfully'];
}

/**
 * Reorder quick links in the configuration
 *
 * @param array $orderedIds Array of link IDs in desired order
 * @return array Result with 'success' and 'message' keys
 */
function reorderQuickLinks(array $orderedIds): array
{
    $config = loadZonesConfig();
    $currentLinks = $config['specialLinks'] ?? [];
    $reorderedLinks = [];

    // Build map of existing links
    $linkMap = [];
    foreach ($currentLinks as $link) {
        $linkMap[$link['id']] = $link;
    }

    // Reorder based on provided IDs
    foreach ($orderedIds as $id) {
        if (isset($linkMap[$id])) {
            $reorderedLinks[] = $linkMap[$id];
            unset($linkMap[$id]);
        }
    }

    // Append any links not in the order list
    foreach ($linkMap as $link) {
        $reorderedLinks[] = $link;
    }

    $config['specialLinks'] = $reorderedLinks;

    if (!saveZonesConfig($config)) {
        return ['success' => false, 'message' => 'Failed to save configuration'];
    }

    return ['success' => true, 'message' => 'Quick links reordered successfully'];
}
