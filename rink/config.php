<?php
/**
 * Generated Configuration File
 * Last Updated: 2026-03-10 12:14:35
 */

const RECEIVERS = [
    'Rink Music' => [
        'ip' => '192.168.8.78',
        'show_power' => false
    ],
    'Rink Video Wall' => [
        'ip' => '192.168.8.77',
        'show_power' => false
    ],
];

const TRANSMITTERS = [
    'Cable Box 1 (Attic TX 1)' => 7,
    'Cable Box 2 (Attic TX 2)' => 3,
    'Cable Box 3 (Attic TX 3)' => 7,
    'Apple TV' => 4,
    'Unifi Signage' => 8,
    'Mobile Video TX' => 9,
    'Mobile Audio TX' => 5,
    'RockBot Audio' => 2,
    'Wireless Mic TX' => 6,
    'Trivia' => 11,
];

const MAX_VOLUME = 9;
const MIN_VOLUME = 1;
const VOLUME_STEP = 1;
const HOME_URL = '/';
const LOG_LEVEL = 'info';
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
