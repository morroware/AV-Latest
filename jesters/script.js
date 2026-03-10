/**
 * Combined JavaScript for AV Controls and Remote Control System
 * 
 * This script provides the client-side functionality for the AV Control System.
 * It handles user interactions with the control elements, communicates with
 * the backend via AJAX, and provides visual feedback to users.
 * 
 * Main features:
 * - Receiver control (channel/volume adjustments)
 * - Remote control functionality for IR commands
 * - WLED lighting system control
 * - Favorite channels management
 * - Visual feedback and error handling
 * 
 * The script is organized into logical sections for different functionality areas.
 */

/**
 * Initialize all components when the DOM is fully loaded
 * This is the main entry point that sets up all interactive elements
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize the AV receiver controls (volume sliders, channel dropdowns)
    initializeReceiverControls();
    
    // Load the list of transmitters from the server configuration
    loadTransmitters();
    
    // Load the favorite channels list from favorites.ini
    loadFavorites();
    
    // Set up the WLED lighting control buttons in the footer
    initializeWLEDControls();
});

/**
 * Initialize WLED control buttons in the footer
 * 
 * This function sets up event handlers for the WLED lighting control
 * buttons, which allow users to turn venue lighting on or off.
 */
function initializeWLEDControls() {
    // Add click event for WLED power on button
    $('#wled-footer-controls .power-on').on('click', function() {
        // Send the command to turn WLED devices on
        toggleWLED('on');
        
        // Add visual feedback animation when button is clicked
        $(this).addClass('clicked');
        setTimeout(() => $(this).removeClass('clicked'), 300);
    });
    
    // Add click event for WLED power off button
    $('#wled-footer-controls .power-off').on('click', function() {
        // Send the command to turn WLED devices off
        toggleWLED('off');
        
        // Add visual feedback animation when button is clicked
        $(this).addClass('clicked');
        setTimeout(() => $(this).removeClass('clicked'), 300);
    });
}

/**
 * Send WLED control commands to the backend
 * 
 * This function sends AJAX requests to the wled.php endpoint
 * to control the WLED lighting devices throughout the venue.
 * 
 * @param {string} action - Either 'on' or 'off'
 */
function toggleWLED(action) {
    $.ajax({
        url: 'wled.php',
        type: 'POST',
        data: { action: action },
        dataType: 'json',
        success: function(response) {
            // Show success message if the command was executed successfully
            if (response.success) {
                showResponseMessage(`WLED lights turned ${action}`, true);
            } else {
                // Show error message with details from the server
                showResponseMessage(`Failed to turn WLED lights ${action}: ${response.message}`, false);
            }
        },
        error: function() {
            // Show generic error message if server communication failed
            showResponseMessage(`Failed to communicate with WLED controller`, false);
        }
    });
}

/**
 * Initialize AV Receiver Controls
 * 
 * This function sets up event handlers for all receiver controls:
 * - Channel selection dropdowns
 * - Volume sliders
 * - Power control buttons
 */
function initializeReceiverControls() {
    // Handle channel dropdown changes
    // When a user selects a different input source, immediately send the update
    $(document).on('change', '.channel-select', function() {
        const deviceIp = $(this).data('ip');
        const selectedChannel = $(this).val();
        $.ajax({
            url: '',
            type: 'POST',
            data: { receiver_ip: deviceIp, channel: selectedChannel },
            dataType: 'json',
            success(response) {
                // Only show messages on failure to avoid excessive notifications
                if (!response.success) showResponseMessage("Channel update failed: " + response.message, false);
            },
            error() {
                showResponseMessage('Failed to update channel', false);
            }
        });
    });

    // Handle volume slider input (real-time visual update during sliding)
    $(document).on('input', '.volume-slider', function() {
        updateVolumeLabel(this);
    });
    
    // Handle volume slider change (when user releases the slider)
    // This is when we actually send the update to the device
    $(document).on('change', '.volume-slider', function() {
        const deviceIp = $(this).data('ip');
        const selectedVolume = $(this).val();
        $.ajax({
            url: '',
            type: 'POST',
            data: { receiver_ip: deviceIp, volume: selectedVolume },
            dataType: 'json',
            success(response) {
                // Only show messages on failure to avoid excessive notifications
                if (!response.success) showResponseMessage("Volume update failed: " + response.message, false);
            },
            error() {
                showResponseMessage('Failed to update volume', false);
            }
        });
    });

    // Global power control buttons
    
    // Power All On button - turns on all compatible receivers
    $('#power-all-on').on('click', function() {
        // Send power on command to all devices
        sendPowerCommandToAll('cec_tv_on.sh');
        
        // Schedule a second attempt after 30 seconds
        // This helps with devices that might not respond to the first command
        setTimeout(() => sendPowerCommandToAll('cec_tv_on.sh'), 30000);
        
        showResponseMessage('Powering on devices... The command will repeat in 30 seconds.', true);
    });
    
    // Power All Off button - turns off all compatible receivers
    $('#power-all-off').on('click', function() {
        sendPowerCommandToAll('cec_tv_off.sh');
        showResponseMessage('Powering off devices.', true);
    });
}

/**
 * Update the visual label next to the volume slider
 * 
 * This provides immediate visual feedback of the current volume level
 * while the user is adjusting the slider, before the actual value is sent
 * to the device.
 * 
 * @param {HTMLElement} slider - The volume slider DOM element being adjusted
 */
function updateVolumeLabel(slider) {
    const label = slider.parentElement.querySelector('.volume-label');
    if (label) label.textContent = slider.value;
}

/**
 * Send a power command to a specific device
 * 
 * This function sends a power on/off command to a single receiver device
 * and schedules a repeat command if it's a power-on operation (for reliability).
 * 
 * @param {string} deviceIp - IP address of the receiver
 * @param {string} command - Either 'cec_tv_on.sh' or 'cec_tv_off.sh'
 * @returns {Promise} - jQuery Promise object from the AJAX request
 */
function sendPowerCommand(deviceIp, command) {
    return $.ajax({
        url: '',
        type: 'POST',
        data: { receiver_ip: deviceIp, power_command: command },
        dataType: 'json'
    }).then(() => {
        // For power-on commands, repeat after 30 seconds
        // This helps with devices that might be slow to initialize HDMI-CEC
        if (command === 'cec_tv_on.sh') {
            setTimeout(() => {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: { receiver_ip: deviceIp, power_command: command },
                    dataType: 'json'
                });
            }, 30000);
        }
    });
}

/**
 * Send a power command to all receiver devices
 * 
 * This function loops through all receivers on the page and sends
 * the specified power command to each one, then displays a summary message.
 * 
 * @param {string} command - Either 'cec_tv_on.sh' or 'cec_tv_off.sh'
 */
function sendPowerCommandToAll(command) {
    const receivers = $('.receiver');
    const promises = [];
    
    // Send command to each receiver
    receivers.each(function() {
        const ip = $(this).data('ip');
        if (ip) promises.push(sendPowerCommand(ip, command));
    });
    
    // When all commands have been sent, show a summary message
    Promise.all(promises)
        .then(() => {
            const msg = command === 'cec_tv_on.sh'
                ? 'All devices are powering on. The command will repeat in 30 seconds.'
                : 'All devices are powering off.';
            showResponseMessage(msg, true);
        })
        .catch(() => {
            // Fail silently: do nothing on error
            // Individual device errors are handled by their respective handlers
        });
}

/**
 * Load transmitter list dropdown
 * 
 * This function fetches the list of IR transmitters from transmitters.txt
 * and populates the transmitter selection dropdown in the remote control section.
 */
function loadTransmitters() {
    fetch('transmitters.txt')
        .then(res => res.text())
        .then(data => {
            // Create a dropdown select element
            const sel = document.createElement('select');
            sel.id = 'transmitter';
            
            // Parse each line of the transmitters.txt file
            data.split('\n').forEach(line => {
                line = line.trim();
                if (!line) return;
                
                // Format: Name, URL
                const [name, url] = line.split(',').map(s => s.trim());
                
                // Create an option for each transmitter
                const opt = document.createElement('option');
                opt.value = url;
                opt.textContent = name;
                sel.appendChild(opt);
            });
            
            // Add the dropdown to the transmitter selection section
            const c = document.getElementById('transmitter-select');
            c.innerHTML = 'Select Transmitter: ';
            c.appendChild(sel);
        })
        .catch(() => showError('Failed to load transmitters'));
}

/**
 * Send an infrared command to the selected transmitter
 * 
 * This function sends a remote control command (like 'power', 'up', '1', etc.)
 * to the currently selected transmitter device.
 * 
 * @param {string} action - The remote control command to send
 * @returns {Promise} - Promise that resolves on success or rejects on failure
 */
function sendCommand(action) {
    const tx = document.getElementById('transmitter');
    
    // Check if a transmitter is selected
    if (!tx || !tx.value) {
        showError('Please select a transmitter');
        return Promise.reject();
    }
    
    // Send the command via AJAX
    return $.ajax({
        url: 'api.php',
        type: 'POST',
        data: { device_url: tx.value, action: action },
        dataType: 'json'
    }).then(response => {
        if (!response.success) return Promise.reject(response.error || 'Command failed');
    });
}

/**
 * Load favorite channels from favorites.ini
 * 
 * This function fetches the list of favorite channels from favorites.ini
 * and populates the favorites dropdown in the remote control section.
 * When a user selects a favorite, it automatically sends the channel digits.
 */
function loadFavorites() {
    fetch('favorites.ini')
        .then(res => res.text())
        .then(txt => {
            // Parse the INI file content
            // Format: Number=Name (one per line)
            const entries = txt.split('\n')
                .map(l => l.trim())
                .filter(l => l && !l.startsWith(';'))  // Skip comments (lines starting with ;)
                .map(l => l.split('='))
                .filter(pair => pair.length === 2);

            // Create the dropdown
            const sel = document.createElement('select');
            sel.id = 'favorite-channel';
            sel.innerHTML = '<option value="">Select Favorite…</option>';
            
            // Add each favorite channel as an option
            entries.forEach(([num,name]) => {
                const opt = document.createElement('option');
                opt.value = num.trim();
                opt.textContent = name.trim();
                sel.appendChild(opt);
            });
            
            // Add change event listener to automatically send the channel
            // when a selection is made (no "Go" button needed)
            sel.addEventListener('change', function() {
                if (this.value) {
                    sendFavoriteChannel(this.value);
                }
            });

            // Add the dropdown to the favorites section
            const c = document.getElementById('favorite-channels-select');
            c.innerHTML = 'Favorite Channels: ';
            c.appendChild(sel);
        })
        .catch(() => showError('Failed to load favorites'));
}

/**
 * Helper function to create a delay
 * 
 * Used between sequential actions to ensure devices have
 * time to process each command.
 * 
 * @param {number} ms - Milliseconds to wait
 * @returns {Promise} - Promise that resolves after the specified delay
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Send a favorite channel by sending each digit sequentially
 * 
 * This function sends each digit of the channel number as a separate
 * command with a delay between, then sends the select/enter command.
 * 
 * @param {string} channel - The channel number to send (e.g., "123")
 */
async function sendFavoriteChannel(channel) {
    if (!channel) {
        return;
    }
    
    const delay = 50;  // 300ms delay between digits
    try {
        // Send each digit of the channel number sequentially
        for (const digit of channel) {
            await sendCommand(digit);
            await sleep(delay);
        }
        
        // Send the select/enter command to confirm the channel
        await sendCommand('select');
    } catch (err) {
        showError('Error sending favorite channel: ' + err);
    }
}

/**
 * Show a temporary response message at the top of the page
 * 
 * This displays success or error messages to the user that
 * automatically fade out after a few seconds.
 * 
 * @param {string} message - The message to display
 * @param {boolean} success - Whether this is a success (true) or error (false) message
 */
function showResponseMessage(message, success) {
    const box = $('#response-message');
    box.removeClass('success error')
       .addClass(success ? 'success' : 'error')
       .html(message)
       .fadeIn();
    
    // Automatically hide the message after 5 seconds
    setTimeout(() => box.fadeOut(), 5000);
}

/**
 * Show an error message in the remote control section
 * 
 * This displays error messages specifically related to remote
 * control operations in the dedicated error box.
 * 
 * @param {string} message - The error message to display
 */
function showError(message) {
    const err = document.getElementById('error-message');
    const txt = document.getElementById('error-text');
    
    if (err && txt) {
        // Update the error message and make it visible
        txt.textContent = message;
        err.style.display = 'block';
        
        // Automatically hide after 5 seconds
        setTimeout(() => err.style.display = 'none', 5000);
    } else {
        // Fallback to console if the error display elements aren't found
        console.error(message);
    }
}
