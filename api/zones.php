<?php
/**
 * Zones API Endpoint
 *
 * Returns zone and navigation data for dynamic loading.
 * Used by the main index page and other dynamic interfaces.
 *
 * @author Seth Morrow
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once dirname(__DIR__) . '/shared/zones.php';

$config = loadZonesConfig();
$baseDir = dirname(__DIR__);

// Get navigation zones and filter out any without valid directories
$zones = getNavigationZones();
$validZones = array_values(array_filter($zones, function($zone) use ($baseDir) {
    $zoneDir = $baseDir . '/' . $zone['id'];
    return is_dir($zoneDir);
}));

$response = [
    'zones' => $validZones,
    'specialLinks' => getSpecialLinks(),
    'settings' => $config['settings'] ?? []
];

echo json_encode($response);
