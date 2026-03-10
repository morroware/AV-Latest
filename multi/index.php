<?php
/**
 * Multi Zone - Multi-Receiver Control Interface
 *
 * This page allows users to select multiple receivers via checkboxes
 * and change them all to a selected transmitter source.
 * It handles volume management with anti-popping measures.
 *
 * @author Seth Morrow
 * @version 3.0 (Refactored)
 */

require_once __DIR__ . '/config.php';
require_once dirname(__DIR__) . '/shared/utils.php';

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
                
                // Get target volume from POST data
                $targetVolume = isset($_POST['volumes'][$receiverIp]) ? intval($_POST['volumes'][$receiverIp]) : null;
                
                // If we have a current volume and a target volume, set to 0 first
                if ($currentVolume !== null && $targetVolume !== null) {
                    // Set volume to 0 to prevent popping
                    setVolume($receiverIp, 0);
                    usleep(500000); // 500ms delay
                }
                
                // Change channel
                $result = setChannel($receiverIp, $targetChannel);
                
                if ($result) {
                    $successCount++;
                    $response['results'][$receiverName] = [
                        'success' => true,
                        'message' => 'Channel changed successfully',
                        'volume_set' => false
                    ];
                    
                    // Set the target volume if applicable
                    if ($targetVolume !== null && supportsVolumeControl($receiverIp)) {
                        // Wait a moment for channel change to settle
                        usleep(500000); // 500ms
                        
                        $volumeResult = setVolume($receiverIp, $targetVolume);
                        if ($volumeResult) {
                            $response['results'][$receiverName]['volume_set'] = true;
                            $response['results'][$receiverName]['target_volume'] = $targetVolume;
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
    <title>Multi Zone - Multi-Receiver Control</title>
    <link rel="stylesheet" href="../shared/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <!-- LiveCode browser widget compatibility layer -->
    <script src="../livecode-compat.js"></script>
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
                <h1>Multi-Receiver Control</h1>
            </div>
            <div class="header-buttons">
                <a href="devices.php" class="button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                    </svg>
                    Manage Devices
                </a>
                <a href="../index.html" class="button home-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                    </svg>
                    Home
                </a>
            </div>
        </header>

        <div class="control-container">
            <div class="control-section">
                <h2>Select Receivers</h2>
                <p>Choose which receivers you want to update, then select a transmitter source below.</p>
                
                <div class="selection-info">
                    <span class="selected-count">0 receivers selected</span>
                    <div class="selection-action-buttons">
                        <button class="btn btn-primary" onclick="selectAllReceivers()">Select All</button>
                        <button class="btn btn-primary video-only" onclick="selectVideoReceivers()">Video</button>
                        <button class="btn btn-primary audio-only" onclick="selectAudioReceivers()">Audio</button>
                        <button class="btn btn-secondary" onclick="deselectAllReceivers()">Deselect All</button>
                    </div>
                </div>

                <?php
                // Separate receivers into video and audio using the type field
                $videoReceivers = [];
                $audioReceivers = [];

                foreach (RECEIVERS as $name => $config) {
                    $type = $config['type'] ?? 'video';
                    if ($type === 'audio') {
                        $audioReceivers[$name] = $config;
                    } else {
                        $videoReceivers[$name] = $config;
                    }
                }
                ?>

                <!-- Video Receivers Section -->
                <div class="receiver-category">
                    <h3 class="category-title">📺 Video Receivers</h3>
                    <div class="receivers-grid">
                        <?php foreach ($videoReceivers as $name => $config): ?>
                        <?php 
                            $supportsVolume = false;
                            $currentVolume = 0;
                            $currentChannel = null;
                            $currentTransmitter = 'Unknown';
                            
                            try {
                                // Get current channel
                                $currentChannel = getCurrentChannel($config['ip']);
                                
                                // Find transmitter name for current channel
                                if ($currentChannel !== null) {
                                    foreach (TRANSMITTERS as $txName => $txChannel) {
                                        if ($txChannel == $currentChannel) {
                                            $currentTransmitter = $txName;
                                            break;
                                        }
                                    }
                                    if ($currentTransmitter === 'Unknown') {
                                        $currentTransmitter = "Channel $currentChannel";
                                    }
                                }
                                
                                // Check volume support
                                $supportsVolume = supportsVolumeControl($config['ip']);
                                if ($supportsVolume) {
                                    $currentVolume = getCurrentVolume($config['ip']) ?? 0;
                                }
                            } catch (Exception $e) {
                                // Device might be offline
                                $currentTransmitter = 'Offline';
                            }
                        ?>
                        <div class="receiver-checkbox-card video-receiver" data-ip="<?php echo htmlspecialchars($config['ip']); ?>" data-name="<?php echo htmlspecialchars($name); ?>" data-type="video" onclick="toggleReceiver(this)">
                            <input type="checkbox" class="receiver-check" value="<?php echo htmlspecialchars($config['ip']); ?>">
                            <div class="checkbox-indicator"></div>
                            <div class="receiver-info">
                                <div class="receiver-name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="receiver-ip"><?php echo htmlspecialchars($config['ip']); ?></div>
                                <div class="current-transmitter">
                                    <span class="tx-label">Current TX:</span>
                                    <span class="tx-value"><?php echo htmlspecialchars($currentTransmitter); ?></span>
                                </div>
                                <?php if ($supportsVolume): ?>
                                <div class="volume-control">
                                    <label>New Volume:</label>
                                    <input type="range"
                                           class="volume-slider"
                                           min="<?php echo MIN_VOLUME; ?>"
                                           max="<?php echo MAX_VOLUME; ?>"
                                           value="<?php echo $currentVolume; ?>"
                                           data-ip="<?php echo htmlspecialchars($config['ip']); ?>"
                                           onclick="event.stopPropagation()">
                                    <span class="volume-value"><?php echo $currentVolume; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="receiver-status"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Audio Receivers Section -->
                <div class="receiver-category">
                    <h3 class="category-title">🔊 Audio Receivers</h3>
                    <div class="receivers-grid">
                        <?php foreach ($audioReceivers as $name => $config): ?>
                        <?php 
                            $supportsVolume = false;
                            $currentVolume = 0;
                            $currentChannel = null;
                            $currentTransmitter = 'Unknown';
                            
                            try {
                                // Get current channel
                                $currentChannel = getCurrentChannel($config['ip']);
                                
                                // Find transmitter name for current channel
                                if ($currentChannel !== null) {
                                    foreach (TRANSMITTERS as $txName => $txChannel) {
                                        if ($txChannel == $currentChannel) {
                                            $currentTransmitter = $txName;
                                            break;
                                        }
                                    }
                                    if ($currentTransmitter === 'Unknown') {
                                        $currentTransmitter = "Channel $currentChannel";
                                    }
                                }
                                
                                // Check volume support
                                $supportsVolume = supportsVolumeControl($config['ip']);
                                if ($supportsVolume) {
                                    $currentVolume = getCurrentVolume($config['ip']) ?? 0;
                                }
                            } catch (Exception $e) {
                                // Device might be offline
                                $currentTransmitter = 'Offline';
                            }
                        ?>
                        <div class="receiver-checkbox-card audio-receiver" data-ip="<?php echo htmlspecialchars($config['ip']); ?>" data-name="<?php echo htmlspecialchars($name); ?>" data-type="audio" onclick="toggleReceiver(this)">
                            <input type="checkbox" class="receiver-check" value="<?php echo htmlspecialchars($config['ip']); ?>">
                            <div class="checkbox-indicator"></div>
                            <div class="receiver-info">
                                <div class="receiver-name"><?php echo htmlspecialchars($name); ?></div>
                                <div class="receiver-ip"><?php echo htmlspecialchars($config['ip']); ?></div>
                                <div class="current-transmitter">
                                    <span class="tx-label">Current TX:</span>
                                    <span class="tx-value"><?php echo htmlspecialchars($currentTransmitter); ?></span>
                                </div>
                                <?php if ($supportsVolume): ?>
                                <div class="volume-control">
                                    <label>New Volume:</label>
                                    <input type="range"
                                           class="volume-slider"
                                           min="<?php echo MIN_VOLUME; ?>"
                                           max="<?php echo MAX_VOLUME; ?>"
                                           value="<?php echo $currentVolume; ?>"
                                           data-ip="<?php echo htmlspecialchars($config['ip']); ?>"
                                           onclick="event.stopPropagation()">
                                    <span class="volume-value"><?php echo $currentVolume; ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="receiver-status"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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
            
            // Add event handlers for volume sliders
            document.querySelectorAll('.volume-slider').forEach(slider => {
                slider.addEventListener('input', function(e) {
                    e.stopPropagation();
                    const value = this.value;
                    const valueDisplay = this.nextElementSibling;
                    if (valueDisplay) {
                        valueDisplay.textContent = value;
                    }
                });
                
                slider.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            });
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

        function selectVideoReceivers() {
            document.querySelectorAll('.receiver-checkbox-card.video-receiver').forEach(element => {
                const checkbox = element.querySelector('.receiver-check');
                const ip = checkbox.value;

                selectedReceivers.add(ip);
                element.classList.add('selected');
                checkbox.checked = true;
            });

            updateSelectedCount();
            updateApplyButton();
        }

        function selectAudioReceivers() {
            document.querySelectorAll('.receiver-checkbox-card.audio-receiver').forEach(element => {
                const checkbox = element.querySelector('.receiver-check');
                const ip = checkbox.value;

                selectedReceivers.add(ip);
                element.classList.add('selected');
                checkbox.checked = true;
            });

            updateSelectedCount();
            updateApplyButton();
        }

        function selectAllReceivers() {
            document.querySelectorAll('.receiver-checkbox-card').forEach(element => {
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

            document.querySelectorAll('.receiver-checkbox-card').forEach(element => {
                element.classList.remove('selected');
                element.querySelector('.receiver-check').checked = false;
            });

            updateSelectedCount();
            updateApplyButton();
        }

        function updateSelectedCount() {
            const count = selectedReceivers.size;
            const videoCount = document.querySelectorAll('.receiver-checkbox-card.video-receiver.selected').length;
            const audioCount = document.querySelectorAll('.receiver-checkbox-card.audio-receiver.selected').length;
            
            let text = '';
            if (count === 0) {
                text = '0 receivers selected';
            } else if (count === 1) {
                text = '1 receiver selected';
            } else {
                text = `${count} receivers selected`;
                if (videoCount > 0 && audioCount > 0) {
                    text += ` (${videoCount} video, ${audioCount} audio)`;
                }
            }
            
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
                // Collect volume settings for each selected receiver
                const volumes = {};
                selectedReceivers.forEach(ip => {
                    const slider = document.querySelector(`.receiver-checkbox-card[data-ip="${ip}"] .volume-slider`);
                    if (slider) {
                        volumes[ip] = slider.value;
                    }
                });
                
                const response = await $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'change_receivers',
                        receivers: Array.from(selectedReceivers),
                        channel: selectedChannel,
                        volumes: volumes
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
                            
                            if (result.success && result.volume_set) {
                                message += ` (volume: ${result.target_volume})`;
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
