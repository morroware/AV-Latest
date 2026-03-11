<?php
/**
 * Generated Configuration File
 * Last Updated: 2026-03-11 00:00:00
 * ALL Zone - Controls all receivers across the facility
 */

const RECEIVERS = [
    'Bowling Bar Music' => [
        'ip' => '192.168.8.60',
        'show_power' => false
    ],
    'Right DJ TV' => [
        'ip' => '192.168.8.61',
        'show_power' => true
    ],
    'NeoVerse 6' => [
        'ip' => '192.168.8.62',
        'show_power' => true
    ],
    'Skate Shack RX' => [
        'ip' => '192.168.8.63',
        'show_power' => true
    ],
    'Bowling Bar TV 1' => [
        'ip' => '192.168.8.64',
        'show_power' => true
    ],
    'Facility Zone Pro' => [
        'ip' => '192.168.8.65',
        'show_power' => false
    ],
    'Driving Range Music RX' => [
        'ip' => '192.168.8.66',
        'show_power' => false
    ],
    'Left DJ TV' => [
        'ip' => '192.168.8.67',
        'show_power' => true
    ],
    'NeoVerse 5' => [
        'ip' => '192.168.8.68',
        'show_power' => true
    ],
    'NeoVerse 4' => [
        'ip' => '192.168.8.69',
        'show_power' => true
    ],
    'Dining Area TV' => [
        'ip' => '192.168.8.70',
        'show_power' => true,
        'power_on_command' => 'cec_power_on_tv',
        'power_on_repeat' => false,
        'power_on_followup_command' => 'cec_watch_me.sh',
        'power_on_followup_fallback_command' => 'cec_power_on_tv',
        'power_on_followup_delay_ms' => 7000,
        'power_off_pre_command' => 'cec_watch_me.sh',
        'power_off_pre_delay_ms' => 3000
    ],
    'NeoVerse 2' => [
        'ip' => '192.168.8.71',
        'show_power' => true
    ],
    'NeoVerse 1' => [
        'ip' => '192.168.8.72',
        'show_power' => true
    ],
    'Bowling Bar TV 3' => [
        'ip' => '192.168.8.73',
        'show_power' => true
    ],
    'Mobile Audio RX' => [
        'ip' => '192.168.8.74',
        'show_power' => false
    ],
    'Bowling Music' => [
        'ip' => '192.168.8.75',
        'show_power' => false
    ],
    'Axe Billiards Music' => [
        'ip' => '192.168.8.76',
        'show_power' => false
    ],
    'Rink Video RX' => [
        'ip' => '192.168.8.77',
        'show_power' => true
    ],
    'Rink Music RX' => [
        'ip' => '192.168.8.78',
        'show_power' => false
    ],
    'Attic RX 2' => [
        'ip' => '192.168.8.79',
        'show_power' => true
    ],
    'NeoVerse 3' => [
        'ip' => '192.168.8.80',
        'show_power' => true
    ],
    'Bowling Bar TV' => [
        'ip' => '192.168.8.81',
        'show_power' => true
    ],
    'Bowling Bar TV 2' => [
        'ip' => '192.168.8.82',
        'show_power' => true
    ],
    'Attic RX 4' => [
        'ip' => '192.168.8.19',
        'show_power' => true
    ],
];

const TRANSMITTERS = [
    'Apple TV' => 1,
    'RockBot Audio' => 2,
    'Cable Box 2 (Attic TX 2)' => 3,
    'Cable Box 1 (Attic TX 1)' => 4,
    'Mobile Video TX' => 5,
    'Wireless Mic TX' => 6,
    'Cable Box 3 (Attic TX 3)' => 7,
    'Unifi Signage' => 8,
    'Trivia (Spare TX 2)' => 9,
];

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
