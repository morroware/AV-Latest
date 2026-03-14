<?php
/**
 * Generated Configuration File
 * Last Updated: 2026-03-10 12:41:10
 */

const RECEIVERS = [
    'Bowling Bar TV 1' => [
        'ip' => '192.168.8.39',
        'show_power' => true
    ],
    'Bowling Bar TV 2' => [
        'ip' => '192.168.8.42',
        'show_power' => true
    ],
    'Bowling Bar TV 3' => [
        'ip' => '192.168.8.32',
        'show_power' => true
    ],
    'Bowling Bar TV 4' => [
        'ip' => '192.168.8.41',
        'show_power' => true
    ],
    'Billiards TV 1' => [
        'ip' => '192.168.8.23',
        'show_power' => true,
        'power_on_command' => 'cec_power_on_tv',
        'power_on_repeat' => true,
        'power_on_followup_command' => 'cec_watch_me.sh',
        'power_on_followup_fallback_command' => 'cec_tv_on.sh',
        'power_on_followup_delay_ms' => 9000,
        'power_off_pre_command' => 'cec_watch_me.sh',
        'power_off_pre_delay_ms' => 3000,
        'power_off_command' => 'cec_power_off_tv'
    ],
    'Left DJ TV' => [
        'ip' => '192.168.8.27',
        'show_power' => true,
        'power_on_followup_command' => 'cec_watch_me.sh',
        'power_on_followup_fallback_command' => 'cec_power_on_tv',
        'power_on_followup_delay_ms' => 7000
    ],
    'Right DJ TV' => [
        'ip' => '192.168.8.30',
        'show_power' => true
    ],
    'Bowling Bar Music' => [
        'ip' => '192.168.8.22',
        'show_power' => false
    ],
    'Axe/Billiards Music' => [
        'ip' => '192.168.8.38',
        'show_power' => false
    ],
    'Bowling Music' => [
        'ip' => '192.168.8.34',
        'show_power' => false
    ],
];

const TRANSMITTERS = [
    'Apple TV' => 7,
    'RockBot Audio' => 5,
    'Cable Box 2 (Attic TX 2)' => 3,
    'Cable Box 1 (Attic TX 1)' => 9,
    'Mobile Video TX' => 2,
    'Wireless Mic TX' => 8,
    'Cable Box 3 (Attic TX 3)' => 4,
    'Unifi Signage' => 6,
    'Trivia (Spare TX 2)' => 1,
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
