<?php
/**
 * Multi Zone Configuration File
 *
 * This file dynamically loads receivers and transmitters from devices.json
 * for use by the multi-receiver control interface.
 *
 * Device management: Use the device management page at devices.php
 * to add, edit, or remove devices.
 */

// Load devices from JSON file
$devicesFile = dirname(__DIR__) . '/devices.json';
$devicesData = [];

if (file_exists($devicesFile)) {
    $devicesData = json_decode(file_get_contents($devicesFile), true) ?? [];
}

// Build RECEIVERS array from devices.json
$receiversArray = [];
if (!empty($devicesData['receivers'])) {
    foreach ($devicesData['receivers'] as $receiver) {
        // Only include enabled receivers
        if (isset($receiver['enabled']) && $receiver['enabled'] === false) {
            continue;
        }
        $receiversArray[$receiver['name']] = [
            'ip' => $receiver['ip'],
            'show_power' => $receiver['show_power'] ?? true,
            'type' => $receiver['type'] ?? 'video'
        ];
    }
}

// Build TRANSMITTERS array from devices.json
$transmittersArray = [];
if (!empty($devicesData['transmitters'])) {
    foreach ($devicesData['transmitters'] as $transmitter) {
        // Only include enabled transmitters
        if (isset($transmitter['enabled']) && $transmitter['enabled'] === false) {
            continue;
        }
        $transmittersArray[$transmitter['name']] = $transmitter['channel'];
    }
}

// Define constants for backwards compatibility
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
    'power',
    'guide',
    'up',
    'down',
    'left',
    'right',
    'select',
    'channel_up',
    'channel_down',
    '0',
    '1',
    '2',
    '3',
    '4',
    '5',
    '6',
    '7',
    '8',
    '9',
    'last',
    'exit',
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

/**
 * Get all receivers including their type information
 * @return array
 */
function getAllReceivers(): array {
    return RECEIVERS;
}

/**
 * Get all transmitters
 * @return array
 */
function getAllTransmitters(): array {
    return TRANSMITTERS;
}

/**
 * Get video receivers only
 * @return array
 */
function getVideoReceivers(): array {
    return array_filter(RECEIVERS, function($config) {
        $type = $config['type'] ?? 'video';
        return $type === 'video';
    });
}

/**
 * Get audio receivers only
 * @return array
 */
function getAudioReceivers(): array {
    return array_filter(RECEIVERS, function($config) {
        $type = $config['type'] ?? 'audio';
        return $type === 'audio';
    });
}
