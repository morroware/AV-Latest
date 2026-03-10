<?php
/**
 * Device Management - Add/Edit/Remove Receivers and Transmitters
 *
 * This page allows dynamic management of all receivers and transmitters
 * that appear on the Multi page.
 *
 * @author Seth Morrow
 * @version 1.0
 */

$devicesFile = dirname(__DIR__) . '/devices.json';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    try {
        $action = $_POST['action'] ?? '';
        $devices = json_decode(file_get_contents($devicesFile), true);

        if (!$devices) {
            throw new Exception('Could not load devices configuration');
        }

        switch ($action) {
            case 'add_receiver':
                $name = trim($_POST['name'] ?? '');
                $ip = trim($_POST['ip'] ?? '');
                $type = $_POST['type'] ?? 'video';
                $showPower = isset($_POST['show_power']) && $_POST['show_power'] === 'true';

                if (empty($name) || empty($ip)) {
                    throw new Exception('Name and IP address are required');
                }

                // Validate IP format
                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new Exception('Invalid IP address format');
                }

                // Check for duplicate IP
                foreach ($devices['receivers'] as $receiver) {
                    if ($receiver['ip'] === $ip) {
                        throw new Exception('A receiver with this IP already exists');
                    }
                }

                // Check for duplicate name
                foreach ($devices['receivers'] as $receiver) {
                    if (strtolower($receiver['name']) === strtolower($name)) {
                        throw new Exception('A receiver with this name already exists');
                    }
                }

                $devices['receivers'][] = [
                    'name' => $name,
                    'ip' => $ip,
                    'type' => $type,
                    'show_power' => $showPower,
                    'enabled' => true
                ];

                $response['message'] = "Receiver '$name' added successfully";
                break;

            case 'edit_receiver':
                $originalIp = $_POST['original_ip'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $ip = trim($_POST['ip'] ?? '');
                $type = $_POST['type'] ?? 'video';
                $showPower = isset($_POST['show_power']) && $_POST['show_power'] === 'true';
                $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';

                if (empty($name) || empty($ip)) {
                    throw new Exception('Name and IP address are required');
                }

                if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                    throw new Exception('Invalid IP address format');
                }

                $found = false;
                foreach ($devices['receivers'] as &$receiver) {
                    if ($receiver['ip'] === $originalIp) {
                        $receiver['name'] = $name;
                        $receiver['ip'] = $ip;
                        $receiver['type'] = $type;
                        $receiver['show_power'] = $showPower;
                        $receiver['enabled'] = $enabled;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    throw new Exception('Receiver not found');
                }

                $response['message'] = "Receiver '$name' updated successfully";
                break;

            case 'delete_receiver':
                $ip = $_POST['ip'] ?? '';

                $originalCount = count($devices['receivers']);
                $devices['receivers'] = array_values(array_filter($devices['receivers'], function($r) use ($ip) {
                    return $r['ip'] !== $ip;
                }));

                if (count($devices['receivers']) === $originalCount) {
                    throw new Exception('Receiver not found');
                }

                $response['message'] = "Receiver deleted successfully";
                break;

            case 'add_transmitter':
                $name = trim($_POST['name'] ?? '');
                $channel = intval($_POST['channel'] ?? 0);

                if (empty($name) || $channel <= 0) {
                    throw new Exception('Name and channel number are required');
                }

                // Check for duplicate channel
                foreach ($devices['transmitters'] as $tx) {
                    if ($tx['channel'] === $channel) {
                        throw new Exception('A transmitter with this channel already exists');
                    }
                }

                // Check for duplicate name
                foreach ($devices['transmitters'] as $tx) {
                    if (strtolower($tx['name']) === strtolower($name)) {
                        throw new Exception('A transmitter with this name already exists');
                    }
                }

                $devices['transmitters'][] = [
                    'name' => $name,
                    'channel' => $channel,
                    'enabled' => true
                ];

                $response['message'] = "Transmitter '$name' added successfully";
                break;

            case 'edit_transmitter':
                $originalChannel = intval($_POST['original_channel'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $channel = intval($_POST['channel'] ?? 0);
                $enabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';

                if (empty($name) || $channel <= 0) {
                    throw new Exception('Name and channel number are required');
                }

                $found = false;
                foreach ($devices['transmitters'] as &$tx) {
                    if ($tx['channel'] === $originalChannel) {
                        $tx['name'] = $name;
                        $tx['channel'] = $channel;
                        $tx['enabled'] = $enabled;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    throw new Exception('Transmitter not found');
                }

                $response['message'] = "Transmitter '$name' updated successfully";
                break;

            case 'delete_transmitter':
                $channel = intval($_POST['channel'] ?? 0);

                $originalCount = count($devices['transmitters']);
                $devices['transmitters'] = array_values(array_filter($devices['transmitters'], function($t) use ($channel) {
                    return $t['channel'] !== $channel;
                }));

                if (count($devices['transmitters']) === $originalCount) {
                    throw new Exception('Transmitter not found');
                }

                $response['message'] = "Transmitter deleted successfully";
                break;

            default:
                throw new Exception('Invalid action');
        }

        // Save updated devices
        $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        if (file_put_contents($devicesFile, json_encode($devices, $jsonOptions)) === false) {
            throw new Exception('Could not save devices configuration');
        }

        $response['success'] = true;
        $response['devices'] = $devices;

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

// Load current devices
$devices = json_decode(file_get_contents($devicesFile), true);
if (!$devices) {
    $devices = ['receivers' => [], 'transmitters' => []];
}

// Sort receivers by type then name
usort($devices['receivers'], function($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'video' ? -1 : 1;
    }
    return strcasecmp($a['name'], $b['name']);
});

// Sort transmitters by channel
usort($devices['transmitters'], function($a, $b) {
    return $a['channel'] - $b['channel'];
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Management - Multi Zone</title>
    <link rel="stylesheet" href="../shared/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- LiveCode browser widget compatibility layer -->
    <script src="../livecode-compat.js"></script>
    <style>
        .device-management {
            max-width: 1400px;
            margin: 0 auto;
        }

        .device-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .device-sections {
                grid-template-columns: 1fr;
            }
        }

        .device-section {
            background: var(--glass-bg);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .device-section h3 {
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .device-count {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .device-table {
            width: 100%;
            border-collapse: collapse;
        }

        .device-table th,
        .device-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--surface-border);
        }

        .device-table th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .device-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .device-table .type-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .device-table .type-badge.video {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .device-table .type-badge.audio {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .device-table .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .device-table .status-badge.enabled {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }

        .device-table .status-badge.disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-icon.edit {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
        }

        .btn-icon.edit:hover {
            background: rgba(59, 130, 246, 0.4);
        }

        .btn-icon.delete {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        .btn-icon.delete:hover {
            background: rgba(239, 68, 68, 0.4);
        }

        .add-device-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .add-device-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--surface-solid);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 1.5rem;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal h3 {
            margin: 0 0 1.5rem 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            background: var(--glass-bg);
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .form-group .checkbox-label input[type="checkbox"] {
            width: auto;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .modal-actions button {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .modal-actions .btn-cancel {
            background: var(--glass-bg);
            color: var(--text-primary);
            border: 1px solid var(--surface-border);
        }

        .modal-actions .btn-cancel:hover {
            background: var(--surface-border);
        }

        .modal-actions .btn-save {
            background: var(--primary-color);
            color: white;
        }

        .modal-actions .btn-save:hover {
            background: var(--primary-hover);
        }

        .modal-actions .btn-delete {
            background: #ef4444;
            color: white;
        }

        .modal-actions .btn-delete:hover {
            background: #dc2626;
        }

        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 2000;
            animation: slideIn 0.3s ease;
        }

        .toast.success {
            background: #22c55e;
        }

        .toast.error {
            background: #ef4444;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
            text-decoration: none;
            margin-bottom: 1rem;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <div class="logo-container">
                    <a href="../index.html">
                        <img src="../logo.png" alt="Castle AV Controls Logo" class="logo">
                    </a>
                </div>
                <h1>Device Management</h1>
            </div>
            <div class="header-buttons">
                <a href="index.php" class="button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                    </svg>
                    Back to Multi
                </a>
                <a href="../index.html" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
            </div>
        </header>

        <div class="device-management">
            <div class="device-sections">
                <!-- Receivers Section -->
                <div class="device-section">
                    <h3>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12z"/>
                        </svg>
                        Receivers
                        <span class="device-count"><?php echo count($devices['receivers']); ?></span>
                    </h3>

                    <?php if (empty($devices['receivers'])): ?>
                    <div class="empty-state">No receivers configured</div>
                    <?php else: ?>
                    <table class="device-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>IP Address</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="receiversTable">
                            <?php foreach ($devices['receivers'] as $receiver): ?>
                            <tr data-ip="<?php echo htmlspecialchars($receiver['ip']); ?>">
                                <td><?php echo htmlspecialchars($receiver['name']); ?></td>
                                <td><?php echo htmlspecialchars($receiver['ip']); ?></td>
                                <td>
                                    <span class="type-badge <?php echo $receiver['type']; ?>">
                                        <?php echo $receiver['type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo ($receiver['enabled'] ?? true) ? 'enabled' : 'disabled'; ?>">
                                        <?php echo ($receiver['enabled'] ?? true) ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon edit" onclick="editReceiver(<?php echo htmlspecialchars(json_encode($receiver)); ?>)" title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon delete" onclick="deleteReceiver('<?php echo htmlspecialchars($receiver['ip']); ?>', '<?php echo htmlspecialchars($receiver['name']); ?>')" title="Delete">
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <button class="add-device-btn" onclick="showAddReceiverModal()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Add Receiver
                    </button>
                </div>

                <!-- Transmitters Section -->
                <div class="device-section">
                    <h3>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                        </svg>
                        Transmitters
                        <span class="device-count"><?php echo count($devices['transmitters']); ?></span>
                    </h3>

                    <?php if (empty($devices['transmitters'])): ?>
                    <div class="empty-state">No transmitters configured</div>
                    <?php else: ?>
                    <table class="device-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="transmittersTable">
                            <?php foreach ($devices['transmitters'] as $transmitter): ?>
                            <tr data-channel="<?php echo $transmitter['channel']; ?>">
                                <td><?php echo htmlspecialchars($transmitter['name']); ?></td>
                                <td><?php echo $transmitter['channel']; ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($transmitter['enabled'] ?? true) ? 'enabled' : 'disabled'; ?>">
                                        <?php echo ($transmitter['enabled'] ?? true) ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon edit" onclick="editTransmitter(<?php echo htmlspecialchars(json_encode($transmitter)); ?>)" title="Edit">
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                            </svg>
                                        </button>
                                        <button class="btn-icon delete" onclick="deleteTransmitter(<?php echo $transmitter['channel']; ?>, '<?php echo htmlspecialchars($transmitter['name']); ?>')" title="Delete">
                                            <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <button class="add-device-btn" onclick="showAddTransmitterModal()">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
                        </svg>
                        Add Transmitter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Receiver Modal -->
    <div class="modal-overlay" id="receiverModal">
        <div class="modal">
            <h3 id="receiverModalTitle">Add Receiver</h3>
            <form id="receiverForm">
                <input type="hidden" id="receiverOriginalIp" value="">
                <input type="hidden" id="receiverEditMode" value="false">

                <div class="form-group">
                    <label for="receiverName">Name</label>
                    <input type="text" id="receiverName" placeholder="e.g., Lobby TV" required>
                </div>

                <div class="form-group">
                    <label for="receiverIp">IP Address</label>
                    <input type="text" id="receiverIp" placeholder="e.g., 192.168.8.100" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                </div>

                <div class="form-group">
                    <label for="receiverType">Type</label>
                    <select id="receiverType">
                        <option value="video">Video</option>
                        <option value="audio">Audio</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="receiverShowPower">
                        Show power controls
                    </label>
                </div>

                <div class="form-group" id="receiverEnabledGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="receiverEnabled" checked>
                        Enabled
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeReceiverModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transmitter Modal -->
    <div class="modal-overlay" id="transmitterModal">
        <div class="modal">
            <h3 id="transmitterModalTitle">Add Transmitter</h3>
            <form id="transmitterForm">
                <input type="hidden" id="transmitterOriginalChannel" value="">
                <input type="hidden" id="transmitterEditMode" value="false">

                <div class="form-group">
                    <label for="transmitterName">Name</label>
                    <input type="text" id="transmitterName" placeholder="e.g., Cable Box 1" required>
                </div>

                <div class="form-group">
                    <label for="transmitterChannel">Channel Number</label>
                    <input type="number" id="transmitterChannel" placeholder="e.g., 1" min="1" required>
                </div>

                <div class="form-group" id="transmitterEnabledGroup" style="display: none;">
                    <label class="checkbox-label">
                        <input type="checkbox" id="transmitterEnabled" checked>
                        Enabled
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeTransmitterModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <h3>Confirm Delete</h3>
            <p id="deleteMessage">Are you sure you want to delete this device?</p>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-delete" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Receiver Modal Functions
        function showAddReceiverModal() {
            document.getElementById('receiverModalTitle').textContent = 'Add Receiver';
            document.getElementById('receiverForm').reset();
            document.getElementById('receiverOriginalIp').value = '';
            document.getElementById('receiverEditMode').value = 'false';
            document.getElementById('receiverEnabledGroup').style.display = 'none';
            document.getElementById('receiverModal').classList.add('active');
        }

        function editReceiver(receiver) {
            document.getElementById('receiverModalTitle').textContent = 'Edit Receiver';
            document.getElementById('receiverOriginalIp').value = receiver.ip;
            document.getElementById('receiverEditMode').value = 'true';
            document.getElementById('receiverName').value = receiver.name;
            document.getElementById('receiverIp').value = receiver.ip;
            document.getElementById('receiverType').value = receiver.type;
            document.getElementById('receiverShowPower').checked = receiver.show_power;
            document.getElementById('receiverEnabled').checked = receiver.enabled !== false;
            document.getElementById('receiverEnabledGroup').style.display = 'block';
            document.getElementById('receiverModal').classList.add('active');
        }

        function closeReceiverModal() {
            document.getElementById('receiverModal').classList.remove('active');
        }

        document.getElementById('receiverForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const editMode = document.getElementById('receiverEditMode').value === 'true';
            const data = {
                action: editMode ? 'edit_receiver' : 'add_receiver',
                name: document.getElementById('receiverName').value,
                ip: document.getElementById('receiverIp').value,
                type: document.getElementById('receiverType').value,
                show_power: document.getElementById('receiverShowPower').checked ? 'true' : 'false'
            };

            if (editMode) {
                data.original_ip = document.getElementById('receiverOriginalIp').value;
                data.enabled = document.getElementById('receiverEnabled').checked ? 'true' : 'false';
            }

            try {
                const response = await $.post('', data);
                if (response.success) {
                    showToast(response.message);
                    location.reload();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast('Failed to save receiver', 'error');
            }
        });

        // Transmitter Modal Functions
        function showAddTransmitterModal() {
            document.getElementById('transmitterModalTitle').textContent = 'Add Transmitter';
            document.getElementById('transmitterForm').reset();
            document.getElementById('transmitterOriginalChannel').value = '';
            document.getElementById('transmitterEditMode').value = 'false';
            document.getElementById('transmitterEnabledGroup').style.display = 'none';
            document.getElementById('transmitterModal').classList.add('active');
        }

        function editTransmitter(transmitter) {
            document.getElementById('transmitterModalTitle').textContent = 'Edit Transmitter';
            document.getElementById('transmitterOriginalChannel').value = transmitter.channel;
            document.getElementById('transmitterEditMode').value = 'true';
            document.getElementById('transmitterName').value = transmitter.name;
            document.getElementById('transmitterChannel').value = transmitter.channel;
            document.getElementById('transmitterEnabled').checked = transmitter.enabled !== false;
            document.getElementById('transmitterEnabledGroup').style.display = 'block';
            document.getElementById('transmitterModal').classList.add('active');
        }

        function closeTransmitterModal() {
            document.getElementById('transmitterModal').classList.remove('active');
        }

        document.getElementById('transmitterForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const editMode = document.getElementById('transmitterEditMode').value === 'true';
            const data = {
                action: editMode ? 'edit_transmitter' : 'add_transmitter',
                name: document.getElementById('transmitterName').value,
                channel: document.getElementById('transmitterChannel').value
            };

            if (editMode) {
                data.original_channel = document.getElementById('transmitterOriginalChannel').value;
                data.enabled = document.getElementById('transmitterEnabled').checked ? 'true' : 'false';
            }

            try {
                const response = await $.post('', data);
                if (response.success) {
                    showToast(response.message);
                    location.reload();
                } else {
                    showToast(response.message, 'error');
                }
            } catch (error) {
                showToast('Failed to save transmitter', 'error');
            }
        });

        // Delete Functions
        let deleteCallback = null;

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
            deleteCallback = null;
        }

        function deleteReceiver(ip, name) {
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete "${name}"?`;
            document.getElementById('deleteModal').classList.add('active');

            deleteCallback = async function() {
                try {
                    const response = await $.post('', { action: 'delete_receiver', ip: ip });
                    if (response.success) {
                        showToast(response.message);
                        location.reload();
                    } else {
                        showToast(response.message, 'error');
                    }
                } catch (error) {
                    showToast('Failed to delete receiver', 'error');
                }
                closeDeleteModal();
            };
        }

        function deleteTransmitter(channel, name) {
            document.getElementById('deleteMessage').textContent = `Are you sure you want to delete "${name}"?`;
            document.getElementById('deleteModal').classList.add('active');

            deleteCallback = async function() {
                try {
                    const response = await $.post('', { action: 'delete_transmitter', channel: channel });
                    if (response.success) {
                        showToast(response.message);
                        location.reload();
                    } else {
                        showToast(response.message, 'error');
                    }
                } catch (error) {
                    showToast('Failed to delete transmitter', 'error');
                }
                closeDeleteModal();
            };
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (deleteCallback) {
                deleteCallback();
            }
        });

        // Close modals on outside click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>
