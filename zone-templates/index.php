<?php
/**
 * Zone AV Control Interface - Entry Point
 *
 * This file handles requests for this zone's AV controls.
 * It loads the zone configuration and shared utilities.
 *
 * @version 1.0 (Template)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

ob_start();

// Load zone-specific configuration
require_once __DIR__ . '/config.php';

// Load shared utilities and controller
require_once dirname(__DIR__) . '/shared/utils.php';
require_once dirname(__DIR__) . '/shared/BaseController.php';

// Create controller for this zone
$controller = new BaseController(__DIR__);

// Handle AJAX requests (channel changes, volume, power, etc.)
if ($controller->handleRequest()) {
    // AJAX request was handled, script will exit
}

// Check if any receivers are reachable before rendering the page
$allReceiversUnreachable = $controller->checkReceiversReachable();

// Render the zone template
include __DIR__ . '/template.php';

ob_end_flush();
