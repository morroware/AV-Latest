<?php
/**
 * Device Directory API
 *
 * Aggregates all device information from zone configs, devices.json,
 * DBconfigs.ini, and WLEDlist.ini into a single JSON response.
 * Used by the Device Directory page.
 *
 * @author Castle AV System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$baseDir = dirname(__DIR__);
$devices = [];

// Load shared zone utilities for safe config loading
require_once $baseDir . '/shared/zones.php';

// ---- 1. Zone Receivers (from each zone's config.php) ----
$zonesConfig = loadZonesConfig();
$zoneList = $zonesConfig['zones'] ?? [];
$zoneReceivers = [];

foreach ($zoneList as $zone) {
    $zoneId = $zone['id'] ?? '';
    if (!$zoneId) continue;

    // Load receivers using isolated-scope loader (no regex, no constant conflicts)
    $receivers = loadZoneReceivers($zoneId);

    foreach ($receivers as $name => $config) {
        $ip = $config['ip'] ?? '';
        if (empty($ip)) continue;

        $key = $ip; // deduplicate by IP
        if (!isset($zoneReceivers[$key])) {
            $zoneReceivers[$key] = [
                'name' => $name,
                'ip' => $ip,
                'category' => 'av-receivers',
                'zones' => []
            ];
        }
        if (!in_array($zone['name'] ?? $zoneId, $zoneReceivers[$key]['zones'])) {
            $zoneReceivers[$key]['zones'][] = $zone['name'] ?? $zoneId;
        }
    }
}

foreach ($zoneReceivers as $rx) {
    $devices[] = $rx;
}

// ---- 2. Transmitter Hardware (from transmitters.txt files) ----
$txSeen = [];
foreach ($zoneList as $zone) {
    $txFile = $baseDir . '/' . ($zone['id'] ?? '') . '/transmitters.txt';
    if (!file_exists($txFile)) continue;
    $lines = file($txFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $ip = trim($line);
        if (filter_var($ip, FILTER_VALIDATE_IP) && !isset($txSeen[$ip])) {
            $txSeen[$ip] = true;
            $devices[] = [
                'name' => 'IR Transmitter (' . $ip . ')',
                'ip' => $ip,
                'category' => 'transmitters',
                'zones' => ['All Zones']
            ];
        }
    }
}

// ---- 3. WLED Devices (from DBconfigs.ini) ----
$dbConfig = $baseDir . '/DBconfigs.ini';
if (file_exists($dbConfig)) {
    $ini = parse_ini_file($dbConfig, true);

    // WLED section
    if (!empty($ini['WLED'])) {
        foreach ($ini['WLED'] as $name => $value) {
            $ip = trim($value);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $devices[] = [
                    'name' => $name,
                    'ip' => $ip,
                    'category' => 'wled',
                    'zones' => []
                ];
            }
        }
    }

    // Temp Sensors
    if (!empty($ini['Temp Sensors'])) {
        foreach ($ini['Temp Sensors'] as $name => $value) {
            $value = trim($value);
            // Extract IP (may have port and path)
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)/', $value, $m)) {
                $devices[] = [
                    'name' => $name,
                    'ip' => $m[1],
                    'url' => $value,
                    'category' => 'infrastructure',
                    'zones' => []
                ];
            }
        }
    }

    // Pi Projects
    if (!empty($ini['Pi Projects'])) {
        foreach ($ini['Pi Projects'] as $name => $value) {
            $value = trim($value);
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)/', $value, $m)) {
                $devices[] = [
                    'name' => preg_replace('/\s*\(.*\)/', '', $name),
                    'ip' => $m[1],
                    'url' => $value,
                    'category' => 'infrastructure',
                    'zones' => []
                ];
            }
        }
    }

    // Printers
    if (!empty($ini['Printers'])) {
        foreach ($ini['Printers'] as $name => $value) {
            $value = trim($value);
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)/', $value, $m)) {
                $devices[] = [
                    'name' => $name,
                    'ip' => $m[1],
                    'url' => $value,
                    'category' => 'printers',
                    'zones' => []
                ];
            }
        }
    }
}

// ---- 4. Global devices.json transmitters (logical, no IP but include for completeness) ----
$devicesFile = $baseDir . '/devices.json';
if (file_exists($devicesFile)) {
    $devData = json_decode(file_get_contents($devicesFile), true) ?? [];
    if (!empty($devData['transmitters'])) {
        foreach ($devData['transmitters'] as $tx) {
            if (isset($tx['enabled']) && !$tx['enabled']) continue;
            $devices[] = [
                'name' => $tx['name'],
                'ip' => null,
                'channel' => $tx['channel'] ?? null,
                'category' => 'transmitters',
                'zones' => ['All Zones'],
                'type' => 'logical'
            ];
        }
    }
}

echo json_encode([
    'devices' => $devices,
    'generated' => date('Y-m-d H:i:s'),
    'categories' => [
        ['id' => 'av-receivers', 'name' => 'AV Receivers', 'icon' => 'monitor'],
        ['id' => 'transmitters', 'name' => 'Transmitters', 'icon' => 'broadcast'],
        ['id' => 'wled', 'name' => 'WLED Lighting', 'icon' => 'lightbulb'],
        ['id' => 'infrastructure', 'name' => 'Infrastructure', 'icon' => 'server'],
        ['id' => 'printers', 'name' => 'Network Printers', 'icon' => 'printer']
    ]
], JSON_PRETTY_PRINT);
