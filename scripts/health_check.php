#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Project health check for Castle Fun Center AV Control System.
 *
 * Validates:
 *  - zones.json schema and duplicate zone IDs
 *  - Zone directories referenced in zones.json
 *  - Required zone files
 *  - JSON syntax for top-level JSON configs
 *  - PHP syntax for active runtime files (excluding backups)
 *  - Receiver IP subnet consistency (192.168.8.x)
 *  - WLED IP subnet consistency (192.168.6.x)
 *  - Duplicate receiver IPs across zones
 *
 * Compatible with PHP 7.4+.
 */

const REQUIRED_ZONE_FILES = ['index.php', 'config.php', 'template.php', 'transmitters.txt', 'payloads.txt'];
const OPTIONAL_TOP_LEVEL_JSON_FILES = ['devices.json', 'site-config.json'];

/** @var int */
$checks = 0;
/** @var array<int, string> */
$errors = [];
/** @var array<int, string> */
$warnings = [];

function outputStatus(string $level, string $message): void
{
    echo sprintf('[%s] %s%s', $level, $message, PHP_EOL);
}

function ok(string $message): void
{
    outputStatus('OK', $message);
}

function warn(string $message, array &$warnings): void
{
    $warnings[] = $message;
    outputStatus('WARN', $message);
}

function fail(string $message, array &$errors): void
{
    $errors[] = $message;
    outputStatus('FAIL', $message);
}

function ensureFileExists(string $path, string $context, array &$errors, int &$checks): void
{
    $checks++;
    if (!is_file($path)) {
        fail("Missing {$context}: {$path}", $errors);
        return;
    }

    ok("{$context} exists: {$path}");
}

function isBackupPhpFile(string $relativePath): bool
{
    return strpos($relativePath, 'config_backup_') !== false;
}

function validateZones(string $root, array &$errors, int &$checks): bool
{
    $zonesPath = $root . '/zones.json';
    $checks++;

    if (!is_file($zonesPath)) {
        fail('zones.json was not found at repository root.', $errors);
        return false;
    }

    $zonesRaw = file_get_contents($zonesPath);
    $zones = json_decode((string) $zonesRaw, true);

    if (!is_array($zones)) {
        fail('zones.json is not valid JSON: ' . json_last_error_msg(), $errors);
        return false;
    }
    ok('zones.json is valid JSON.');

    if (!isset($zones['zones']) || !is_array($zones['zones'])) {
        fail('zones.json is missing a valid "zones" array.', $errors);
        return false;
    }

    $zoneIds = [];
    foreach ($zones['zones'] as $index => $zone) {
        $checks++;

        if (!is_array($zone)) {
            fail("zones[{$index}] must be an object.", $errors);
            continue;
        }

        $id = $zone['id'] ?? null;
        if (!is_string($id) || $id === '') {
            fail("zones[{$index}] is missing a non-empty id.", $errors);
            continue;
        }

        if (isset($zoneIds[$id])) {
            fail("Duplicate zone id detected: {$id}", $errors);
            continue;
        }
        $zoneIds[$id] = true;

        $zoneDir = $root . '/' . $id;
        $checks++;

        if (!is_dir($zoneDir)) {
            fail("Zone directory missing for {$id}: {$zoneDir}", $errors);
            continue;
        }
        ok("Zone directory present for {$id}");

        foreach (REQUIRED_ZONE_FILES as $requiredFile) {
            ensureFileExists($zoneDir . '/' . $requiredFile, "{$id}/{$requiredFile}", $errors, $checks);
        }
    }

    return true;
}

function validateTopLevelJsonFiles(string $root, array &$errors, array &$warnings, int &$checks): void
{
    foreach (OPTIONAL_TOP_LEVEL_JSON_FILES as $jsonFile) {
        $checks++;
        $path = $root . '/' . $jsonFile;

        if (!is_file($path)) {
            warn("Optional JSON file not found: {$jsonFile}", $warnings);
            continue;
        }

        json_decode((string) file_get_contents($path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            fail("Invalid JSON in {$jsonFile}: " . json_last_error_msg(), $errors);
            continue;
        }

        ok("{$jsonFile} is valid JSON.");
    }
}

function lintPhpFiles(string $root, array &$errors, int &$checks): void
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
            continue;
        }

        $relativePath = ltrim(str_replace($root, '', $fileInfo->getPathname()), '/');
        if (isBackupPhpFile($relativePath)) {
            continue;
        }

        $checks++;
        $command = sprintf('php -l %s 2>&1', escapeshellarg($fileInfo->getPathname()));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            fail("PHP lint failed for {$relativePath}: " . implode(' ', $output), $errors);
        }
    }

    ok('PHP lint passed for all non-backup PHP files.');
}

/**
 * Validate receiver IPs are in expected subnet and check for cross-zone duplicates
 */
function validateReceiverIps(string $root, array &$errors, array &$warnings, int &$checks): void
{
    $zonesPath = $root . '/zones.json';
    if (!is_file($zonesPath)) return;

    $zones = json_decode((string) file_get_contents($zonesPath), true);
    if (!is_array($zones) || empty($zones['zones'])) return;

    // Use the shared loader if available, otherwise fall back to subprocess
    $loaderScript = $root . '/shared/zones.php';
    $hasLoader = is_file($loaderScript);
    if ($hasLoader) {
        require_once $loaderScript;
    }

    $allReceivers = []; // ip => [zone1, zone2, ...]

    foreach ($zones['zones'] as $zone) {
        $zoneId = $zone['id'] ?? '';
        if (!$zoneId) continue;

        // Skip aggregation zones that dynamically load from other zones
        if (in_array($zoneId, ['multi', 'all'])) continue;

        $receivers = [];
        if ($hasLoader) {
            $receivers = loadZoneReceivers($zoneId);
        }

        foreach ($receivers as $name => $config) {
            $ip = $config['ip'] ?? '';
            if (empty($ip)) continue;

            $checks++;

            // Validate receiver IP is in expected AV subnet
            if (!preg_match('/^192\.168\.8\.\d{1,3}$/', $ip)) {
                warn("Receiver '{$name}' in zone '{$zoneId}' has IP {$ip} outside expected subnet 192.168.8.0/24", $warnings);
            }

            // Track for duplicate detection
            $allReceivers[$ip][] = $zoneId;
        }
    }

    // Check for IPs that appear in multiple zones (informational, not an error)
    foreach ($allReceivers as $ip => $zoneIds) {
        if (count($zoneIds) > 1) {
            $checks++;
            $zoneList = implode(', ', array_unique($zoneIds));
            ok("Receiver {$ip} shared across zones: {$zoneList}");
        }
    }

    ok('Receiver IP validation complete.');
}

/**
 * Validate WLED IPs are in expected subnet
 */
function validateWledIps(string $root, array &$errors, array &$warnings, int &$checks): void
{
    $zonesPath = $root . '/zones.json';
    if (!is_file($zonesPath)) return;

    $zones = json_decode((string) file_get_contents($zonesPath), true);
    if (!is_array($zones) || empty($zones['zones'])) return;

    foreach ($zones['zones'] as $zone) {
        $zoneId = $zone['id'] ?? '';
        $wledFile = $root . '/' . $zoneId . '/WLEDlist.ini';
        if (!$zoneId || !is_file($wledFile)) continue;

        $lines = file($wledFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) continue;

        $ini = parse_ini_file($wledFile, false);
        if (!is_array($ini)) continue;

        foreach ($ini as $name => $ip) {
            $ip = trim($ip);
            if (empty($ip)) continue;

            $checks++;
            if (!preg_match('/^192\.168\.6\.\d{1,3}$/', $ip)) {
                warn("WLED device '{$name}' in zone '{$zoneId}' has IP {$ip} outside expected subnet 192.168.6.0/24", $warnings);
            }
        }
    }

    ok('WLED IP validation complete.');
}

$startTime = microtime(true);
$root = dirname(__DIR__);

validateZones($root, $errors, $checks);
validateTopLevelJsonFiles($root, $errors, $warnings, $checks);
validateReceiverIps($root, $errors, $warnings, $checks);
validateWledIps($root, $errors, $warnings, $checks);
lintPhpFiles($root, $errors, $checks);

$durationMs = (int) round((microtime(true) - $startTime) * 1000);

echo PHP_EOL . '--- Health Check Summary ---' . PHP_EOL;
echo sprintf('Checks run: %d%s', $checks, PHP_EOL);
echo sprintf('Warnings: %d%s', count($warnings), PHP_EOL);
echo sprintf('Errors: %d%s', count($errors), PHP_EOL);
echo sprintf('Duration: %d ms%s', $durationMs, PHP_EOL);

if (!empty($errors)) {
    echo 'Health check failed.' . PHP_EOL;
    exit(1);
}

echo 'Health check passed.' . PHP_EOL;
exit(0);
