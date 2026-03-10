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
            }
            // on failure: intentionally silenced
        },
        error: function() {
            // intentionally silenced
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
    $(document).on('change', '.channel-select', function() {
        const deviceIp = $(this).data('ip');
        const selectedChannel = $(this).val();
        $.ajax({
            url: '',
            type: 'POST',
            data: { receiver_ip: deviceIp, channel: selectedChannel },
            dataType: 'json',
            success(response) {
                // failure notification removed
            },
            error() {
                // failure notification removed
            }
        });
    });

    // Handle volume slider input (real-time visual update during sliding)
    $(document).on('input', '.volume-slider', function() {
        updateVolumeLabel(this);
    });
    
    // Handle volume slider change (when user releases the slider)
    $(document).on('change', '.volume-slider', function() {
        const deviceIp = $(this).data('ip');
        const selectedVolume = $(this).val();
        $.ajax({
            url: '',
            type: 'POST',
            data: { receiver_ip: deviceIp, volume: selectedVolume },
            dataType: 'json',
            success(response) {
                // failure notification removed
            },
            error() {
                // failure notification removed
            }
        });
    });

    // Global power control buttons
    $('#power-all-on').on('click', function() {
        sendPowerCommandToAll('cec_tv_on.sh');
        // Schedule a repeat pass (skips toggle-only displays like Roku TVs)
        setTimeout(() => sendPowerCommandToAll('cec_tv_on.sh', { repeatPass: true }), 30000);
        showResponseMessage('Powering on devices... The command will repeat in 30 seconds.', true);
    });

    $('#power-all-off').on('click', function() {
        sendPowerCommandToAll('cec_tv_off.sh');
        showResponseMessage('Powering off devices.', true);
    });
}

/**
 * Update the visual label next to the volume slider
 */
function updateVolumeLabel(slider) {
    const label = slider.parentElement.querySelector('.volume-label');
    if (label) label.textContent = slider.value;
}

/**
 * Helper to wait a given number of milliseconds
 */
function waitMs(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Send a single power command to a device
 */
function sendPowerCommand(deviceIp, command) {
    return $.ajax({
        url: '',
        type: 'POST',
        data: { receiver_ip: deviceIp, power_command: command },
        dataType: 'json'
    });
}

/**
 * Send a configured power-on sequence for a receiver element.
 * Reads CEC follow-up settings from data attributes for displays
 * that need input selection after power-on (e.g. Roku TVs).
 */
function sendConfiguredPowerOn(receiverElement, deviceIp) {
    const powerOnCommand = receiverElement.dataset.powerOnCommand || 'cec_tv_on.sh';
    const followupCommand = receiverElement.dataset.powerOnFollowupCommand;
    const followupFallbackCommand = receiverElement.dataset.powerOnFollowupFallbackCommand;
    const followupDelayMs = parseInt(receiverElement.dataset.powerOnFollowupDelayMs, 10) || 5000;

    return sendPowerCommand(deviceIp, powerOnCommand)
        .catch(function(error) {
            console.warn('Power-on request failed, continuing with follow-up:', error);
            return null;
        })
        .then(function(response) {
            if (!followupCommand) {
                return response;
            }

            return waitMs(Math.max(0, followupDelayMs))
                .then(() => sendPowerCommand(deviceIp, followupCommand)
                    .catch(function(error) {
                        if (!followupFallbackCommand) {
                            throw error;
                        }
                        console.warn('Follow-up failed, trying fallback:', error);
                        return sendPowerCommand(deviceIp, followupFallbackCommand);
                    }))
                .catch(function(error) {
                    console.warn('Power-on follow-up sequence failed:', error);
                    return response;
                })
                .then(() => response);
        });
}

/**
 * Send a configured power-off sequence for a receiver element.
 * For displays like Roku TVs that only accept CEC Standby when on
 * the correct HDMI input: switches input first, waits, then sends standby.
 */
function sendConfiguredPowerOff(receiverElement, deviceIp) {
    const powerOffCommand = receiverElement.dataset.powerOffCommand || 'cec_tv_off.sh';
    const preCommand = receiverElement.dataset.powerOffPreCommand;
    const preDelayMs = parseInt(receiverElement.dataset.powerOffPreDelayMs, 10) || 3000;

    if (!preCommand) {
        return sendPowerCommand(deviceIp, powerOffCommand);
    }

    return sendPowerCommand(deviceIp, preCommand)
        .catch(function(error) {
            console.warn('Power-off pre-command failed, continuing with standby:', error);
            return null;
        })
        .then(function() {
            return waitMs(Math.max(0, preDelayMs));
        })
        .then(function() {
            return sendPowerCommand(deviceIp, powerOffCommand);
        });
}

/**
 * Send a power command to all receiver devices
 */
function sendPowerCommandToAll(command, options) {
    options = options || {};
    const receivers = $('.receiver');
    const promises = [];
    const isPowerOn = command === 'cec_tv_on.sh';

    receivers.each(function() {
        const ip = $(this).data('ip');
        if (!ip) return;

        // Skip repeat pass for toggle-only displays (e.g. Roku TVs)
        if (options.repeatPass && this.dataset.powerOnRepeat === '0') {
            return;
        }

        if (isPowerOn) {
            promises.push(sendConfiguredPowerOn(this, ip));
        } else {
            promises.push(sendConfiguredPowerOff(this, ip));
        }
    });

    Promise.all(promises)
        .catch(() => {
            // intentionally silenced
        });
}

/**
 * Load transmitter list dropdown
 */
function loadTransmitters() {
    fetch('transmitters.txt')
        .then(res => res.text())
        .then(data => {
            const sel = document.createElement('select');
            sel.id = 'transmitter';
            data.split('\n').forEach(line => {
                line = line.trim();
                if (!line) return;
                const [name, url] = line.split(',').map(s => s.trim());
                const opt = document.createElement('option');
                opt.value = url;
                opt.textContent = name;
                sel.appendChild(opt);
            });
            const c = document.getElementById('transmitter-select');
            c.innerHTML = 'Select Transmitter: ';
            c.appendChild(sel);
        })
        .catch(() => {
            // failure notification removed
        });
}

/**
 * Send an infrared command to the selected transmitter
 */
function sendCommand(action) {
    const tx = document.getElementById('transmitter');
    if (!tx || !tx.value) {
        // failure notification removed
        return Promise.reject();
    }
    return $.ajax({
        url: 'api.php',
        type: 'POST',
        data: { device_url: tx.value, action: action },
        dataType: 'json'
    });
}

/**
 * Load favorite channels from favorites.ini
 */
function loadFavorites() {
    fetch('favorites.ini')
        .then(res => res.text())
        .then(txt => {
            const entries = txt.split('\n')
                .map(l => l.trim())
                .filter(l => l && !l.startsWith(';'))
                .map(l => l.split('='))
                .filter(pair => pair.length === 2);

            const sel = document.createElement('select');
            sel.id = 'favorite-channel';
            sel.innerHTML = '<option value="">Select Favorite…</option>';
            
            entries.forEach(([num,name]) => {
                const opt = document.createElement('option');
                opt.value = num.trim();
                opt.textContent = name.trim();
                sel.appendChild(opt);
            });
            
            sel.addEventListener('change', function() {
                if (this.value) {
                    sendFavoriteChannel(this.value);
                }
            });

            const c = document.getElementById('favorite-channels-select');
            c.innerHTML = 'Favorite Channels: ';
            c.appendChild(sel);
        })
        .catch(() => {
            // failure notification removed
        });
}

/**
 * Helper function to create a delay
 */
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Send a favorite channel by sending each digit sequentially
 */
async function sendFavoriteChannel(channel) {
    if (!channel) {
        return;
    }
    
    const delay = 50;
    try {
        for (const digit of channel) {
            await sendCommand(digit);
            await sleep(delay);
        }
        await sendCommand('select');
    } catch (err) {
        // failure notification removed
    }
}

/**
 * Show a temporary response message at the top of the page
 */
function showResponseMessage(message, success) {
    const box = $('#response-message');
    box.removeClass('success error')
       .addClass(success ? 'success' : 'error')
       .html(message)
       .fadeIn();
    
    setTimeout(() => box.fadeOut(), 5000);
}

/**
 * Show an error message in the remote control section
 * 
 * (This function is now unused but left in case you want to re-enable custom error panels later.)
 */
function showError(message) {
    const err = document.getElementById('error-message');
    const txt = document.getElementById('error-text');
    
    if (err && txt) {
        txt.textContent = message;
        err.style.display = 'block';
        setTimeout(() => err.style.display = 'none', 5000);
    } else {
        console.error(message);
    }
}
