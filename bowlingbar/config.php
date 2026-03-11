<?php
/**
 * Generated Configuration File
 * Last Updated: 2026-03-10 12:41:10
 */

const RECEIVERS = [
    'Bowling Bar TV 1' => [
        'ip' => '192.168.8.64',
        'show_power' => true
    ],
    'Bowling Bar TV 2' => [
        'ip' => '192.168.8.82',
        'show_power' => true
    ],
    'Bowling Bar TV 3' => [
        'ip' => '192.168.8.73',
        'show_power' => true
    ],
    'Bowling Bar TV 4' => [
        'ip' => '192.168.8.81',
        'show_power' => true
    ],
    'Billiards TV 1' => [
        'ip' => '192.168.8.68',
        'show_power' => true
    ],
    'Left DJ TV' => [
        'ip' => '192.168.8.67',
        'show_power' => true
    ],
    'Right DJ TV' => [
        'ip' => '192.168.8.61',
        'show_power' => true
    ],
    'Bowling Bar Music' => [
        'ip' => '192.168.8.75',
        'show_power' => false
    ],
    'Axe/Billiards Music' => [
        'ip' => '192.168.8.76',
        'show_power' => false
    ],
    'Bowling Music' => [
        'ip' => '192.168.8.60',
        'show_power' => false
    ],
];

const TRANSMITTERS = [
    'Cable Box 1 (Attic TX 1)' => 4,
    'Cable Box 2 (Attic TX 2)' => 3,
    'Cable Box 3 (Attic TX 3)' => 7,
    'Apple TV' => 1,
    'Unifi Signage' => 8,
    'Mobile Video TX' => 5,
    'Mobile Audio TX' => 1,
    'RockBot Audio' => 2,
    'Wireless Mic TX' => 6,
    'Trivia' => 9,
];

const MAX_VOLUME = 11;
const MIN_VOLUME = 0;
const VOLUME_STEP = 1;
const HOME_URL = '/';
const LOG_LEVEL = 'error';
const API_TIMEOUT = 1;

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
