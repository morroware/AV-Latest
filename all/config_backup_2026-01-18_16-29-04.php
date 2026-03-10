<?php
/**
 * Generated Configuration File
 * Last Updated: 2025-08-12 12:36:47
 */

const RECEIVERS = [
    'Bowling Bar TV 1' => [
        'ip' => '192.168.8.60',
        'show_power' => true
    ],
    'Bowling Bar TV 2' => [
        'ip' => '192.168.8.61',
        'show_power' => true
    ],
    'Bowling Bar TV 3' => [
        'ip' => '192.168.8.62',
        'show_power' => true
    ],
    'Bowling Bar TV 4' => [
        'ip' => '192.168.8.63',
        'show_power' => true
    ],
    'Billiards TV' => [
        'ip' => '192.168.8.54',
        'show_power' => true
    ],
    'NeoVerse 1' => [
        'ip' => '192.168.8.50',
        'show_power' => true
    ],
    'NeoVerse 2' => [
        'ip' => '192.168.8.51',
        'show_power' => true
    ],
    'NeoVerse 3' => [
        'ip' => '192.168.8.52',
        'show_power' => true
    ],
    'Dining Area TV' => [
        'ip' => '192.168.8.70',
        'show_power' => true
    ],
    'Rink Video Display' => [
        'ip' => '192.168.8.13',
        'show_power' => true
    ],
    'Attic RX 2' => [
        'ip' => '192.168.8.12',
        'show_power' => true
    ],
    'Attic RX 4' => [
        'ip' => '192.168.8.20',
        'show_power' => true
    ],
    'Bowling Bar Music' => [
        'ip' => '192.168.8.28',
        'show_power' => false
    ],
    'Axe/Billiards Music' => [
        'ip' => '192.168.8.27',
        'show_power' => false
    ],
    'Bowling Music' => [
        'ip' => '192.168.8.25',
        'show_power' => false
    ],
    'Rink Music' => [
        'ip' => '192.168.8.15',
        'show_power' => false
    ],
    'Facility Zone Pro' => [
        'ip' => '192.168.8.81',
        'show_power' => false
    ],
];

// Load transmitters from global devices.json
$devicesFile = dirname(__DIR__) . '/devices.json';
$transmittersArray = [];
if (file_exists($devicesFile)) {
    $devicesData = json_decode(file_get_contents($devicesFile), true) ?? [];
    if (!empty($devicesData['transmitters'])) {
        foreach ($devicesData['transmitters'] as $transmitter) {
            if (isset($transmitter['enabled']) && $transmitter['enabled'] === false) {
                continue;
            }
            $transmittersArray[$transmitter['name']] = $transmitter['channel'];
        }
    }
}
define('TRANSMITTERS', $transmittersArray);

const MAX_VOLUME = 11;
const MIN_VOLUME = 0;
const VOLUME_STEP = 1;
const HOME_URL = '/';
const LOG_LEVEL = 'error';
const API_TIMEOUT = 2;

// Remote control configuration
const REMOTE_CONTROL_COMMANDS = array (
  0 => 'power',
  1 => 'guide',
  2 => 'up',
  3 => 'down',
  4 => 'left',
  5 => 'right',
  6 => 'select',
  7 => 'channel_up',
  8 => 'channel_down',
  9 => '0',
  10 => '1',
  11 => '2',
  12 => '3',
  13 => '4',
  14 => '5',
  15 => '6',
  16 => '7',
  17 => '8',
  18 => '9',
  19 => 'last',
  20 => 'exit',
);

const VOLUME_CONTROL_MODELS = array (
  0 => '3G+4+ TX',
  1 => '3G+AVP RX',
  2 => '3G+AVP TX',
  3 => '3G+WP4 TX',
  4 => '2G/3G SX',
);

const ERROR_MESSAGES = array (
  'connection' => 'Unable to connect to %s (%s). Please check the connection and try again.',
  'global' => 'Unable to connect to any receivers. Please check your network connection and try again.',
  'remote' => 'Unable to send remote command. Please try again.',
);

const LOG_FILE = __DIR__ . '/av_controls.log';
