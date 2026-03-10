<?php
/**
 * Facility Zone - AV Control Interface
 *
 * This is the main entry point for the Facility zone.
 * It uses the shared BaseController and utilities.
 *
 * @author Seth Morrow
 * @version 3.0 (Refactored)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();

// Load zone configuration
require_once __DIR__ . '/config.php';

// Load shared utilities and controller
require_once dirname(__DIR__) . '/shared/utils.php';
require_once dirname(__DIR__) . '/shared/BaseController.php';

// Create controller for this zone
$controller = new BaseController(__DIR__);

// Handle AJAX requests
if ($controller->handleRequest()) {
    // AJAX request was handled, script will exit
}

// Check if any receivers are reachable before rendering
$allReceiversUnreachable = $controller->checkReceiversReachable();

// Include the zone-specific template
include __DIR__ . '/template.php';

ob_end_flush();
