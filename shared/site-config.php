<?php
/**
 * Central Site Configuration
 *
 * This file contains global configuration values that should be consistent
 * across all zones. Individual zone config.php files can override these values.
 *
 * To change the home URL, edit this file. All zones will automatically use
 * the updated value unless they define their own HOME_URL constant.
 *
 * @author Seth Morrow
 * @version 1.0
 */

// Home URL - the main dashboard/zone selection page
// Use relative paths for portability (recommended)
// Or set to a full URL like 'http://192.168.8.127' for absolute paths
if (!defined('HOME_URL')) {
    define('HOME_URL', 'index.html');
}

// Admin panel URL (for control+double-click on logo)
// Use relative path if admin is on same server
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', 'index.html');
}

// API endpoint base path (relative to device IP)
if (!defined('API_BASE_PATH')) {
    define('API_BASE_PATH', '/cgi-bin/api/');
}
