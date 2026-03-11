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

// Load zone list from zones.json
$zonesFile = dirname(__DIR__) . '/zones.json';
$zonesData = [];
if (file_exists($zonesFile)) {
    $zonesData = json_decode(file_get_contents($zonesFile), true) ?? [];
}
$zones = $zonesData['zones'] ?? [];

// Aggregate receivers from all zone config files
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

    $configFile = dirname(__DIR__) . '/' . $zoneId . '/config.php';
    if (!file_exists($configFile)) {
        continue;
    }

    // Read the config file as text and parse RECEIVERS
    $configContent = file_get_contents($configFile);

    // Extract the RECEIVERS block: const RECEIVERS = [ ... ];
    if (!preg_match('/const\s+RECEIVERS\s*=\s*\[(.*?)\];/s', $configContent, $receiversMatch)) {
        continue;
    }
    $receiversBlock = $receiversMatch[1];

    // Extract individual receiver entries: 'Name' => [ ... ]
    // Each entry's properties block ends with "]," or "]" (last entry)
    if (!preg_match_all("/'([^']+)'\s*=>\s*\[([^\]]*)\]/s", $receiversBlock, $entries, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($entries as $entry) {
        $name = $entry[1];
        $props = $entry[2];

        // Extract IP
        if (!preg_match("/'ip'\s*=>\s*'([^']+)'/", $props, $ipMatch)) {
            continue;
        }
        $ip = $ipMatch[1];

        // Skip duplicate IPs (first zone's definition wins)
        if (isset($seenIps[$ip])) {
            continue;
        }
        $seenIps[$ip] = true;

        // Extract show_power (defaults to true)
        $showPower = true;
        if (preg_match("/'show_power'\s*=>\s*(true|false)/", $props, $powerMatch)) {
            $showPower = $powerMatch[1] === 'true';
        }

        // Determine type from name patterns
        $type = 'video';
        if (preg_match('/Music|Zone Pro|Concession|Audio/i', $name)) {
            $type = 'audio';
        }

        $receiverConfig = [
            'ip' => $ip,
            'show_power' => $showPower,
            'type' => $type,
            'zone' => $zone['name'] ?? $zoneId,
        ];

        // Preserve CEC power command settings if present
        $cecFields = [
            'power_on_command', 'power_on_repeat', 'power_on_followup_command',
            'power_on_followup_fallback_command', 'power_on_followup_delay_ms',
            'power_off_pre_command', 'power_off_pre_delay_ms'
        ];
        foreach ($cecFields as $field) {
            if (preg_match("/'$field'\s*=>\s*(?:'([^']*)'|(\d+)|(true|false))/", $props, $fieldMatch)) {
                if (!empty($fieldMatch[1])) {
                    $receiverConfig[$field] = $fieldMatch[1];
                } elseif (!empty($fieldMatch[2])) {
                    $receiverConfig[$field] = intval($fieldMatch[2]);
                } elseif (!empty($fieldMatch[3])) {
                    $receiverConfig[$field] = $fieldMatch[3] === 'true';
                }
            }
        }

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
