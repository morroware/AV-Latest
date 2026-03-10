<?php
/**
 * Multi-Receiver Control Interface
 * 
 * This page allows users to select multiple receivers via checkboxes
 * and change them all to a selected transmitter source.
 * It handles volume management similar to DJ.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils.php';

// Handle AJAX request for changing multiple receivers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_receivers') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'message' => '',
        'results' => [],
        'errors' => []
    ];
    
    try {
        // Get selected receivers and target channel
        $selectedReceivers = isset($_POST['receivers']) ? $_POST['receivers'] : [];
        $targetChannel = isset($_POST['channel']) ? intval($_POST['channel']) : null;
        
        if (empty($selectedReceivers)) {
            throw new Exception('No receivers selected');
        }
        
        if (!$targetChannel) {
            throw new Exception('No transmitter channel selected');
        }
        
        $successCount = 0;
        $failureCount = 0;
        
        // Process each selected receiver
        foreach ($selectedReceivers as $receiverIp) {
            $receiverName = '';
            // Find receiver name for logging
            foreach (RECEIVERS as $name => $config) {
                if ($config['ip'] === $receiverIp) {
                    $receiverName = $name;
                    break;
                }
            }
            
            try {
                // Get current volume before changing channel
                $currentVolume = null;
                if (supportsVolumeControl($receiverIp)) {
                    $currentVolume = getCurrentVolume($receiverIp);
                }
                
                // Change channel
                $result = setChannel($receiverIp, $targetChannel);
                
                if ($result) {
                    $successCount++;
                    $response['results'][$receiverName] = [
                        'success' => true,
                        'message' => 'Channel changed successfully',
                        'volume_restored' => false
                    ];
                    
                    // Restore volume if applicable
                    if ($currentVolume !== null && $currentVolume > 0) {
                        // Wait a moment for channel change to settle
                        usleep(500000); // 500ms
                        
                        $volumeResult = setVolume($receiverIp, $currentVolume);
                        if ($volumeResult) {
                            $response['results'][$receiverName]['volume_restored'] = true;
                        }
                    }
                } else {
                    $failureCount++;
                    $response['results'][$receiverName] = [
                        'success' => false,
                        'message' => 'Failed to change channel'
                    ];
                }
                
                // Small delay between receivers to prevent network congestion
                usleep(200000); // 200ms
                
            } catch (Exception $e) {
                $failureCount++;
                $response['errors'][] = "Error with {$receiverName}: " . $e->getMessage();
                $response['results'][$receiverName] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        // Determine overall success
        if ($failureCount === 0) {
            $response['success'] = true;
            $response['message'] = "Successfully updated all {$successCount} receivers";
        } else if ($successCount > 0) {
            $response['success'] = true;
            $response['message'] = "{$successCount} receivers succeeded, {$failureCount} failed";
        } else {
            $response['message'] = "All receivers failed to update";
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Receiver Control</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Custom styles for multi-receiver control */
        .control-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .control-section {
            background: var(--surface-color);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .receivers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .receiver-checkbox {
            background: rgba(0, 0, 0, 0.2);
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .receiver-checkbox:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .receiver-checkbox.selected {
            background: rgba(187, 134, 252, 0.1);
            border-color: var(--primary-color);
        }

        .receiver-checkbox input[type="checkbox"] {
            display: none;
        }

        .checkbox-indicator {
            width: 24px;
            height: 24px;
            border: 2px solid var(--primary-color);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }

        .receiver-checkbox.selected .checkbox-indicator {
            background: var(--primary-color);
        }

        .receiver-checkbox.selected .checkbox-indicator::after {
            content: '✓';
            color: var(--bg-color);
            font-weight: bold;
        }

        .receiver-info {
            flex: 1;
        }

        .receiver-name {
            font-weight: 600;
            font-size: 1.1em;
            margin-bottom: 0.25rem;
        }

        .receiver-ip {
            font-size: 0.9em;
            opacity: 0.7;
        }

        .receiver-status {
            font-size: 0.85em;
            margin-top: 0.25rem;
            display: none;
        }

        .receiver-status.success {
            color: var(--success-color);
            display: block;
        }

        .receiver-status.error {
            color: var(--error-color);
            display: block;
        }

        .receiver-status.updating {
            color: var(--warning-color);
            display: block;
        }

        .transmitter-section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .transmitter-select {
            width: 100%;
            max-width: 400px;
            padding: 0.875rem;
            background: var(--bg-color);
            color: var(--text-color);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            font-size: 1.1em;
            margin-bottom: 1.5rem;
        }

        .control-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .apply-button {
            background: var(--secondary-color);
            color: var(--bg-color);
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-button:hover:not(:disabled) {
            opacity: 0.85;
            transform: translateY(-2px);
        }

        .apply-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .select-all-button, .deselect-all-button {
            background: var(--primary-color);
            color: var(--bg-color);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .select-all-button:hover, .deselect-all-button:hover {
            opacity: 0.85;
        }

        .selection-info {
            margin: 1rem 0;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .selected-count {
            font-size: 1.1em;
            font-weight: 500;
            color: var(--secondary-color);
        }

        .progress-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .progress-content {
            background: var(--surface-color);
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .progress-title {
            font-size: 1.5em;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .progress-details {
            margin: 1.5rem 0;
            max-height: 300px;
            overflow-y: auto;
        }

        .progress-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            margin: 0.25rem 0;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }

        .progress-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(187, 134, 252, 0.3);
            border-top: 3px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .control-container {
                padding: 1rem;
            }
            
            .control-section {
                padding: 1.5rem;
            }
            
            .receivers-grid {
                grid-template-columns: 1fr;
            }
            
            .control-buttons {
                flex-direction: column;
            }
            
            .apply-button, .select-all-button, .deselect-all-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <h1>Multi-Receiver Control</h1>
            </div>
            <div class="header-buttons">
                <a href="index.php" class="button home-button">Back to Control Panel</a>
            </div>
        </header>

        <div class="control-container">
            <div class="control-section">
                <h2>Select Receivers</h2>
                <p>Choose which receivers you want to update, then select a transmitter source below.</p>
                
                <div class="selection-info">
                    <span class="selected-count">0 receivers selected</span>
                    <div>
                        <button class="select-all-button" onclick="selectAllReceivers()">Select All</button>
                        <button class="deselect-all-button" onclick="deselectAllReceivers()">Deselect All</button>
                    </div>
                </div>

                <div class="receivers-grid">
                    <?php foreach (RECEIVERS as $name => $config): ?>
                    <div class="receiver-checkbox" data-ip="<?php echo htmlspecialchars($config['ip']); ?>" data-name="<?php echo htmlspecialchars($name); ?>" onclick="toggleReceiver(this)">
                        <input type="checkbox" class="receiver-check" value="<?php echo htmlspecialchars($config['ip']); ?>">
                        <div class="checkbox-indicator"></div>
                        <div class="receiver-info">
                            <div class="receiver-name"><?php echo htmlspecialchars($name); ?></div>
                            <div class="receiver-ip"><?php echo htmlspecialchars($config['ip']); ?></div>
                            <div class="receiver-status"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="transmitter-section">
                    <h3>Select Transmitter Source</h3>
                    <select class="transmitter-select" id="transmitterSelect">
                        <option value="">-- Select a Transmitter --</option>
                        <?php foreach (TRANSMITTERS as $name => $channel): ?>
                        <option value="<?php echo htmlspecialchars($channel); ?>">
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <button class="apply-button" id="applyButton" onclick="applyChanges()" disabled>
                        Apply Changes to Selected Receivers
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="progress-overlay" id="progressOverlay">
        <div class="progress-content">
            <div class="progress-spinner"></div>
            <div class="progress-title">Updating Receivers...</div>
            <div class="progress-details" id="progressDetails"></div>
        </div>
    </div>

    <script>
        let selectedReceivers = new Set();
        let isUpdating = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add change handler to transmitter select
            document.getElementById('transmitterSelect').addEventListener('change', updateApplyButton);
        });

        function toggleReceiver(element) {
            const checkbox = element.querySelector('.receiver-check');
            const ip = checkbox.value;
            
            if (selectedReceivers.has(ip)) {
                selectedReceivers.delete(ip);
                element.classList.remove('selected');
                checkbox.checked = false;
            } else {
                selectedReceivers.add(ip);
                element.classList.add('selected');
                checkbox.checked = true;
            }
            
            updateSelectedCount();
            updateApplyButton();
        }

        function selectAllReceivers() {
            document.querySelectorAll('.receiver-checkbox').forEach(element => {
                const checkbox = element.querySelector('.receiver-check');
                const ip = checkbox.value;
                
                selectedReceivers.add(ip);
                element.classList.add('selected');
                checkbox.checked = true;
            });
            
            updateSelectedCount();
            updateApplyButton();
        }

        function deselectAllReceivers() {
            selectedReceivers.clear();
            
            document.querySelectorAll('.receiver-checkbox').forEach(element => {
                element.classList.remove('selected');
                element.querySelector('.receiver-check').checked = false;
            });
            
            updateSelectedCount();
            updateApplyButton();
        }

        function updateSelectedCount() {
            const count = selectedReceivers.size;
            const text = count === 1 ? '1 receiver selected' : `${count} receivers selected`;
            document.querySelector('.selected-count').textContent = text;
        }

        function updateApplyButton() {
            const button = document.getElementById('applyButton');
            const transmitterSelect = document.getElementById('transmitterSelect');
            
            button.disabled = selectedReceivers.size === 0 || !transmitterSelect.value || isUpdating;
        }

        function resetStatuses() {
            document.querySelectorAll('.receiver-status').forEach(status => {
                status.textContent = '';
                status.className = 'receiver-status';
            });
        }

        function updateReceiverStatus(ip, status, message) {
            const element = document.querySelector(`[data-ip="${ip}"] .receiver-status`);
            if (element) {
                element.textContent = message;
                element.className = `receiver-status ${status}`;
            }
        }

        function showProgress(show) {
            const overlay = document.getElementById('progressOverlay');
            overlay.style.display = show ? 'flex' : 'none';
            
            if (!show) {
                document.getElementById('progressDetails').innerHTML = '';
            }
        }

        function addProgressItem(name, status, message) {
            const details = document.getElementById('progressDetails');
            const item = document.createElement('div');
            item.className = 'progress-item';
            item.innerHTML = `
                <span>${name}</span>
                <span style="color: ${status === 'success' ? 'var(--success-color)' : status === 'error' ? 'var(--error-color)' : 'var(--warning-color)'}">${message}</span>
            `;
            details.appendChild(item);
        }

        async function applyChanges() {
            if (isUpdating || selectedReceivers.size === 0) return;
            
            const transmitterSelect = document.getElementById('transmitterSelect');
            const selectedChannel = transmitterSelect.value;
            const selectedTransmitterName = transmitterSelect.options[transmitterSelect.selectedIndex].text;
            
            if (!selectedChannel) {
                alert('Please select a transmitter');
                return;
            }
            
            isUpdating = true;
            updateApplyButton();
            resetStatuses();
            showProgress(true);
            
            // Show updating status for all selected receivers
            selectedReceivers.forEach(ip => {
                updateReceiverStatus(ip, 'updating', 'Updating...');
            });
            
            try {
                const response = await $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'change_receivers',
                        receivers: Array.from(selectedReceivers),
                        channel: selectedChannel
                    },
                    dataType: 'json',
                    timeout: 60000 // 60 second timeout
                });
                
                // Process results
                if (response.results) {
                    Object.keys(response.results).forEach(receiverName => {
                        const result = response.results[receiverName];
                        // Find IP by receiver name
                        const receiverElement = document.querySelector(`[data-name="${receiverName}"]`);
                        if (receiverElement) {
                            const ip = receiverElement.dataset.ip;
                            const status = result.success ? 'success' : 'error';
                            let message = result.success ? `✓ Changed to ${selectedTransmitterName}` : '✗ Failed';
                            
                            if (result.success && result.volume_restored) {
                                message += ' (volume restored)';
                            }
                            
                            updateReceiverStatus(ip, status, message);
                            addProgressItem(receiverName, status, message);
                        }
                    });
                }
                
                // Show overall result
                const messageEl = document.createElement('div');
                messageEl.style.marginTop = '1rem';
                messageEl.style.padding = '1rem';
                messageEl.style.background = response.success ? 'var(--success-color)' : 'var(--error-color)';
                messageEl.style.color = 'white';
                messageEl.style.borderRadius = '4px';
                messageEl.textContent = response.message;
                document.getElementById('progressDetails').appendChild(messageEl);
                
                // Hide progress after 3 seconds
                setTimeout(() => {
                    showProgress(false);
                }, 3000);
                
            } catch (error) {
                console.error('Error:', error);
                
                // Update all selected receivers to error state
                selectedReceivers.forEach(ip => {
                    updateReceiverStatus(ip, 'error', '✗ Error');
                });
                
                // Show error in progress
                const messageEl = document.createElement('div');
                messageEl.style.marginTop = '1rem';
                messageEl.style.padding = '1rem';
                messageEl.style.background = 'var(--error-color)';
                messageEl.style.color = 'white';
                messageEl.style.borderRadius = '4px';
                messageEl.textContent = 'Error: Failed to update receivers';
                document.getElementById('progressDetails').appendChild(messageEl);
                
                setTimeout(() => {
                    showProgress(false);
                }, 3000);
                
            } finally {
                isUpdating = false;
                updateApplyButton();
            }
        }
    </script>
</body>
</html>
