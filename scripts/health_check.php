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

$startTime = microtime(true);
$root = dirname(__DIR__);

validateZones($root, $errors, $checks);
validateTopLevelJsonFiles($root, $errors, $warnings, $checks);
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
