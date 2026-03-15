<?php
/**
 * Multi Zone Configuration File
 *
 * Dynamically aggregates receivers from all zone config files
 * so the multi-receiver control interface always has up-to-date
 * device information matching each zone's actual configuration.
 *
 * Transmitters are loaded from devices.json (global source of truth).
 */

// Load zone list from zones.json via shared zone utilities
require_once dirname(__DIR__) . '/shared/zones.php';

$zonesConfig = loadZonesConfig();
$zones = $zonesConfig['zones'] ?? [];

// Aggregate receivers from all zone config files using safe isolated-scope loading
$receiversArray = [];
$seenIps = [];

foreach ($zones as $zone) {
    $zoneId = $zone['id'];

    // Skip aggregation zones (multi and all) and disabled zones
    if (in_array($zoneId, ['multi', 'all'])) {
        continue;
    }
    if (isset($zone['enabled']) && $zone['enabled'] === false) {
        continue;
    }

    // Load receivers from zone config in an isolated PHP process
    $zoneReceivers = loadZoneReceivers($zoneId);

    foreach ($zoneReceivers as $name => $config) {
        $ip = $config['ip'] ?? '';
        if (empty($ip)) {
            continue;
        }

        // Skip duplicate IPs (first zone's definition wins)
        if (isset($seenIps[$ip])) {
            continue;
        }
        $seenIps[$ip] = true;

        // Determine type from name patterns
        $type = 'video';
        if (preg_match('/Music|Zone Pro|Concession|Audio/i', $name)) {
            $type = 'audio';
        }

        // Build receiver config, preserving all original fields (including power commands)
        $receiverConfig = array_merge($config, [
            'type' => $type,
            'zone' => $zone['name'] ?? $zoneId,
        ]);

        $receiversArray[$name] = $receiverConfig;
    }
}

// Load transmitters from devices.json (global source of truth)
$devicesFile = dirname(__DIR__) . '/devices.json';
$devicesData = [];
if (file_exists($devicesFile)) {
    $devicesData = json_decode(file_get_contents($devicesFile), true) ?? [];
}

$transmittersArray = [];
if (!empty($devicesData['transmitters'])) {
    foreach ($devicesData['transmitters'] as $transmitter) {
        if (isset($transmitter['enabled']) && $transmitter['enabled'] === false) {
            continue;
        }
        $transmittersArray[$transmitter['name']] = $transmitter['channel'];
    }
}

// Define constants
define('RECEIVERS', $receiversArray);
define('TRANSMITTERS', $transmittersArray);

// Load settings from devices.json or use defaults
$settings = $devicesData['settings'] ?? [];
define('MAX_VOLUME', $settings['max_volume'] ?? 11);
define('MIN_VOLUME', $settings['min_volume'] ?? 0);
define('VOLUME_STEP', $settings['volume_step'] ?? 1);
define('API_TIMEOUT', $settings['api_timeout'] ?? 2);

// Static configuration
const HOME_URL = '/';
const LOG_LEVEL = 'error';

// Remote control configuration
const REMOTE_CONTROL_COMMANDS = [
    'power', 'guide', 'up', 'down', 'left', 'right', 'select',
    'channel_up', 'channel_down',
    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    'last', 'exit',
];

const VOLUME_CONTROL_MODELS = [
    '3G+4+ TX',
    '3G+AVP RX',
    '3G+AVP TX',
    '3G+WP4 TX',
    '2G/3G SX',
];

const ERROR_MESSAGES = [
    'connection' => 'Unable to connect to %s (%s). Please check the connection and try again.',
    'global' => 'Unable to connect to any receivers. Please check your network connection and try again.',
    'remote' => 'Unable to send remote command. Please try again.',
];

const LOG_FILE = __DIR__ . '/av_controls.log';
