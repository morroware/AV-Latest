<?php
/**
 * Bulk Input Switcher Interface
 * 
 * This page allows users to easily switch inputs on multiple receivers simultaneously.
 * Users can select which receivers to control and choose an input source to apply to all.
 * 
 * Features:
 * - Visual receiver grid with checkboxes for selection
 * - Quick selection buttons (All, None, preset groups)
 * - Input source selection with preview
 * - Real-time feedback during switching operations
 * - Error handling for individual receiver failures
 * 
 * @author Generated for Castle AV Control System
 * @version 1.0
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../shared/utils.php';

/**
 * Handle AJAX requests for bulk input switching
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_switch') {
        $selectedReceivers = $_POST['receivers'] ?? [];
        $selectedChannel = intval($_POST['channel'] ?? 0);
        
        if (empty($selectedReceivers) || !$selectedChannel) {
            echo json_encode(['success' => false, 'message' => 'Please select receivers and a channel']);
            exit;
        }
        
        $results = [];
        $successCount = 0;
        $totalCount = count($selectedReceivers);
        
        foreach ($selectedReceivers as $receiverName) {
            // Find the receiver configuration
            $receiverConfig = null;
            foreach (RECEIVERS as $name => $config) {
                if ($name === $receiverName) {
                    $receiverConfig = $config;
                    break;
                }
            }
            
            if (!$receiverConfig) {
                $results[$receiverName] = ['success' => false, 'message' => 'Receiver not found'];
                continue;
            }
            
            try {
                // Use the anti-popping channel change function
                $success = setChannelWithoutPopping($receiverConfig['ip'], $selectedChannel);
                $results[$receiverName] = [
                    'success' => $success,
                    'message' => $success ? 'Success' : 'Failed to change channel'
                ];
                if ($success) $successCount++;
            } catch (Exception $e) {
                $results[$receiverName] = [
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
                logMessage("Bulk switch error for {$receiverName}: " . $e->getMessage(), 'error');
            }
        }
        
        echo json_encode([
            'success' => true,
            'results' => $results,
            'summary' => "Successfully switched {$successCount} of {$totalCount} receivers"
        ]);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Input Switcher - Castle AV Control System</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- LiveCode browser widget compatibility layer -->
    <script src="../livecode-compat.js"></script>
    <style>
        .bulk-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section {
            background-color: var(--surface-color);
            border-radius: 12px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .receiver-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .receiver-item {
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            padding: 1.5rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .receiver-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .receiver-item.selected {
            border-color: var(--secondary-color);
            background-color: rgba(3, 218, 198, 0.1);
        }

        .receiver-checkbox {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 20px;
            height: 20px;
            accent-color: var(--secondary-color);
        }

        .receiver-name {
            font-size: 1.2em;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            padding-right: 2.5rem;
        }

        .receiver-ip {
            font-size: 0.9em;
            color: var(--text-color);
            opacity: 0.7;
        }

        .receiver-status {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.9em;
            text-align: center;
            display: none;
        }

        .receiver-status.success {
            background-color: var(--success-color);
            color: white;
        }

        .receiver-status.error {
            background-color: var(--error-color);
            color: white;
        }

        .receiver-status.processing {
            background-color: var(--warning-color);
            color: white;
        }

        .selection-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .selection-button {
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-color);
            color: var(--bg-color);
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .selection-button:hover {
            opacity: var(--button-hover-opacity);
            transform: translateY(-1px);
        }

        .input-selection {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 2rem;
            border-radius: 8px;
            margin: 2rem 0;
        }

        .input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .input-option {
            background-color: var(--surface-color);
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .input-option:hover {
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }

        .input-option.selected {
            background-color: var(--secondary-color);
            color: var(--bg-color);
            border-color: var(--secondary-color);
        }

        .input-name {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .input-channel {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .execute-section {
            text-align: center;
            margin-top: 2rem;
        }

        .execute-button {
            padding: 1rem 2rem;
            font-size: 1.2em;
            font-weight: 600;
            background-color: var(--secondary-color);
            color: var(--bg-color);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .execute-button:hover {
            opacity: var(--button-hover-opacity);
            transform: translateY(-2px);
        }

        .execute-button:disabled {
            background-color: #666;
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin: 1rem 0;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--secondary-color);
            width: 0%;
            transition: width 0.3s ease;
        }

        .summary-box {
            background-color: rgba(0, 0, 0, 0.2);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            display: none;
        }

        .selection-count {
            background-color: var(--secondary-color);
            color: var(--bg-color);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-left: 1rem;
        }

        @media (max-width: 768px) {
            .bulk-container {
                padding: 0 1rem;
            }

            .section {
                padding: 1.5rem;
            }

            .selection-controls {
                flex-direction: column;
            }

            .input-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <header>
            <div class="logo-title-group">
                <h1>Bulk Input Switcher</h1>
            </div>
            <div class="header-buttons">
                <a href="index.php" class="button home-button">Back to Control Panel</a>
                <a href="settings.php" class="button home-button">Settings</a>
            </div>
        </header>

        <div class="bulk-container">
            <div id="response-message" style="display: none; margin-bottom: 2rem; padding: 1rem; border-radius: 6px; text-align: center;"></div>

            <!-- Receiver Selection Section -->
            <div class="section">
                <h2>Select Receivers</h2>
                <p>Choose which receivers you want to control. Click on receivers or use the selection buttons below.</p>
                
                <div class="selection-controls">
                    <button class="selection-button" onclick="selectAll()">Select All</button>
                    <button class="selection-button" onclick="selectNone()">Select None</button>
                    <button class="selection-button" onclick="selectTVs()">TVs Only</button>
                    <button class="selection-button" onclick="selectAudio()">Audio Only</button>
                    <span class="selection-count" id="selection-count">0 selected</span>
                </div>

                <div class="receiver-grid" id="receiver-grid">
                    <?php foreach (RECEIVERS as $name => $config): ?>
                    <div class="receiver-item" data-name="<?php echo htmlspecialchars($name); ?>" onclick="toggleReceiver(this)">
                        <input type="checkbox" class="receiver-checkbox" data-name="<?php echo htmlspecialchars($name); ?>">
                        <div class="receiver-name"><?php echo htmlspecialchars($name); ?></div>
                        <div class="receiver-ip"><?php echo htmlspecialchars($config['ip']); ?></div>
                        <div class="receiver-status"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Input Selection Section -->
            <div class="section">
                <h2>Select Input Source</h2>
                <p>Choose the input source to apply to all selected receivers.</p>
                
                <div class="input-selection">
                    <div class="input-grid" id="input-grid">
                        <?php if (defined('TRANSMITTERS')): ?>
                            <?php foreach (TRANSMITTERS as $name => $channel): ?>
                            <div class="input-option" data-channel="<?php echo $channel; ?>" onclick="selectInput(this)">
                                <div class="input-name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="input-channel">Channel <?php echo $channel; ?></div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Execute Section -->
            <div class="section">
                <div class="execute-section">
                    <button class="execute-button" id="execute-button" onclick="executeBulkSwitch()" disabled>
                        Switch Selected Receivers
                    </button>
                    <div class="progress-bar" id="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="summary-box" id="summary-box"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedReceivers = new Set();
        let selectedChannel = null;

        // Update the execute button state
        function updateExecuteButton() {
            const button = document.getElementById('execute-button');
            const canExecute = selectedReceivers.size > 0 && selectedChannel !== null;
            button.disabled = !canExecute;
            
            if (canExecute) {
                button.textContent = `Switch ${selectedReceivers.size} Receiver${selectedReceivers.size > 1 ? 's' : ''}`;
            } else {
                button.textContent = 'Switch Selected Receivers';
            }
        }

        // Update selection count display
        function updateSelectionCount() {
            document.getElementById('selection-count').textContent = `${selectedReceivers.size} selected`;
        }

        // Toggle receiver selection
        function toggleReceiver(element) {
            const checkbox = element.querySelector('.receiver-checkbox');
            const name = element.dataset.name;
            
            if (selectedReceivers.has(name)) {
                selectedReceivers.delete(name);
                element.classList.remove('selected');
                checkbox.checked = false;
            } else {
                selectedReceivers.add(name);
                element.classList.add('selected');
                checkbox.checked = true;
            }
            
            updateSelectionCount();
            updateExecuteButton();
        }

        // Selection control functions
        function selectAll() {
            document.querySelectorAll('.receiver-item').forEach(item => {
                const name = item.dataset.name;
                selectedReceivers.add(name);
                item.classList.add('selected');
                item.querySelector('.receiver-checkbox').checked = true;
            });
            updateSelectionCount();
            updateExecuteButton();
        }

        function selectNone() {
            selectedReceivers.clear();
            document.querySelectorAll('.receiver-item').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('.receiver-checkbox').checked = false;
            });
            updateSelectionCount();
            updateExecuteButton();
        }

        function selectTVs() {
            selectNone();
            document.querySelectorAll('.receiver-item').forEach(item => {
                const name = item.dataset.name.toLowerCase();
                if (name.includes('tv')) {
                    selectedReceivers.add(item.dataset.name);
                    item.classList.add('selected');
                    item.querySelector('.receiver-checkbox').checked = true;
                }
            });
            updateSelectionCount();
            updateExecuteButton();
        }

        function selectAudio() {
            selectNone();
            document.querySelectorAll('.receiver-item').forEach(item => {
                const name = item.dataset.name.toLowerCase();
                if (name.includes('music') || name.includes('audio')) {
                    selectedReceivers.add(item.dataset.name);
                    item.classList.add('selected');
                    item.querySelector('.receiver-checkbox').checked = true;
                }
            });
            updateSelectionCount();
            updateExecuteButton();
        }

        // Select input source
        function selectInput(element) {
            // Remove selection from other inputs
            document.querySelectorAll('.input-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Select this input
            element.classList.add('selected');
            selectedChannel = parseInt(element.dataset.channel);
            updateExecuteButton();
        }

        // Execute bulk switch
        function executeBulkSwitch() {
            if (selectedReceivers.size === 0 || selectedChannel === null) {
                showMessage('Please select receivers and an input source', 'error');
                return;
            }

            const button = document.getElementById('execute-button');
            const progressBar = document.getElementById('progress-bar');
            const progressFill = document.getElementById('progress-fill');
            const summaryBox = document.getElementById('summary-box');

            // Disable button and show progress
            button.disabled = true;
            button.textContent = 'Switching...';
            progressBar.style.display = 'block';
            summaryBox.style.display = 'none';

            // Reset all receiver statuses
            document.querySelectorAll('.receiver-status').forEach(status => {
                status.style.display = 'none';
            });

            // Show processing status for selected receivers
            selectedReceivers.forEach(name => {
                const receiverItem = document.querySelector(`[data-name="${name}"]`);
                const status = receiverItem.querySelector('.receiver-status');
                status.className = 'receiver-status processing';
                status.textContent = 'Processing...';
                status.style.display = 'block';
            });

            // Send AJAX request
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'bulk_switch',
                    receivers: Array.from(selectedReceivers),
                    channel: selectedChannel
                },
                dataType: 'json',
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = evt.loaded / evt.total * 100;
                            progressFill.style.width = percentComplete + '%';
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    progressFill.style.width = '100%';
                    
                    if (response.success) {
                        showMessage(response.summary, 'success');
                        
                        // Update individual receiver statuses
                        Object.entries(response.results).forEach(([name, result]) => {
                            const receiverItem = document.querySelector(`[data-name="${name}"]`);
                            const status = receiverItem.querySelector('.receiver-status');
                            status.className = `receiver-status ${result.success ? 'success' : 'error'}`;
                            status.textContent = result.message;
                        });
                        
                        // Show summary
                        summaryBox.innerHTML = `<h3>Operation Complete</h3><p>${response.summary}</p>`;
                        summaryBox.style.display = 'block';
                    } else {
                        showMessage(response.message, 'error');
                    }
                },
                error: function() {
                    showMessage('Error communicating with server', 'error');
                },
                complete: function() {
                    // Re-enable button and hide progress
                    setTimeout(() => {
                        button.disabled = false;
                        progressBar.style.display = 'none';
                        updateExecuteButton();
                    }, 1000);
                }
            });
        }

        // Show message function
        function showMessage(message, type) {
            const msgBox = document.getElementById('response-message');
            msgBox.className = type;
            msgBox.textContent = message;
            msgBox.style.display = 'block';
            msgBox.style.backgroundColor = type === 'success' ? 'var(--success-color)' : 'var(--error-color)';
            msgBox.style.color = 'white';
            
            setTimeout(() => {
                msgBox.style.display = 'none';
            }, 5000);
        }

        // Initialize
        updateSelectionCount();
        updateExecuteButton();
    </script>
</body>
</html>
