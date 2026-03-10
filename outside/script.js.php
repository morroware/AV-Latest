/**
 * Combined JavaScript for AV Controls and Remote Control System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize both systems
    initializeReceiverControls();
    loadTransmitters();
});

// Receiver Control Functions
function initializeReceiverControls() {
    // Add event listener for channel changes
    $(document).on('change', '.channel-select', function() {
        const deviceIp = $(this).data('ip');
        const selectedChannel = $(this).val();
        
        // Send the channel update via AJAX
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                receiver_ip: deviceIp,
                channel: selectedChannel
            },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    showResponseMessage("Channel update failed: " + response.message, false);
                }
            },
            error: function() {
                showResponseMessage('Failed to update channel', false);
            }
        });
    });
    
    // Keep the input event just for updating the visual label while sliding
    $(document).on('input', '.volume-slider', function() {
        updateVolumeLabel(this);
    });
    
    // Add event listener for volume changes - only fires when slider is released
    $(document).on('change', '.volume-slider', function() {
        const deviceIp = $(this).data('ip');
        const selectedVolume = $(this).val();
        
        // Send the volume update via AJAX when the slider is released
        $.ajax({
            url: '',
            type: 'POST',
            data: {
                receiver_ip: deviceIp,
                volume: selectedVolume
            },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    showResponseMessage("Volume update failed: " + response.message, false);
                }
            },
            error: function() {
                showResponseMessage('Failed to update volume', false);
            }
        });
    });

    $('#power-all-on').on('click', function() {
        // Send the power-on command immediately
        sendPowerCommandToAll('cec_tv_on.sh');

        // Set a timer to send the command again after 30 seconds
        setTimeout(function() {
            sendPowerCommandToAll('cec_tv_on.sh');
        }, 30000);

        showResponseMessage('Powering on devices... The command will repeat in 30 seconds.', true);
    });

    $('#power-all-off').on('click', function() {
        sendPowerCommandToAll('cec_tv_off.sh');
        showResponseMessage('Powering off devices.', true);
    });
}

// Volume and Power functions
function updateVolumeLabel(slider) {
    const label = slider.parentElement.querySelector('.volume-label');
    if (label) {
        label.textContent = slider.value;
    }
}

// Power functions
function sendPowerCommand(deviceIp, command) {
    return $.ajax({
        url: '',
        type: 'POST',
        data: {
            receiver_ip: deviceIp,
            power_command: command
        },
        dataType: 'json'
    }).then(() => {
        // Wait 30 seconds and send the command again if it's a power-on command
        if (command === 'cec_tv_on.sh') {
            setTimeout(() => {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        receiver_ip: deviceIp,
                        power_command: command
                    },
                    dataType: 'json'
                });
            }, 30000);
        }
    });
}

function sendPowerCommandToAll(command) {
    const receivers = $('.receiver');
    let promises = [];

    receivers.each(function() {
        const deviceIp = $(this).data('ip');
        if (deviceIp) {
            promises.push(sendPowerCommand(deviceIp, command));
        }
    });

    Promise.all(promises).then(() => {
        if (command === 'cec_tv_on.sh') {
            showResponseMessage('All devices are powering on. The command will repeat in 30 seconds.', true);
        } else {
            showResponseMessage('All devices are powering off.', true);
        }
    }).catch(() => {
        showResponseMessage('Failed to update one or more devices.', false);
    });
}

// Updated Remote Control Functions
function loadTransmitters() {
    fetch('transmitters.txt')
        .then(response => response.text())
        .then(data => {
            const transmitters = data.split('\n').filter(line => line.trim() !== '');
            
            const select = document.createElement('select');
            select.id = 'transmitter';
            
            transmitters.forEach(transmitter => {
                const [name, url] = transmitter.split(',').map(item => item.trim());
                const option = document.createElement('option');
                option.value = url;
                option.textContent = name;
                select.appendChild(option);
            });
            
            const container = document.getElementById('transmitter-select');
            container.innerHTML = 'Select Transmitter: ';
            container.appendChild(select);
        })
        .catch(error => {
            console.error('Error loading transmitters:', error);
            showError('Failed to load transmitters');
        });
}

function sendCommand(action) {
    const transmitter = document.getElementById('transmitter');
    if (!transmitter || !transmitter.value) {
        showError('Please select a transmitter');
        return;
    }

    $.ajax({
        url: 'api.php',
        type: 'POST',
        data: {
            device_url: transmitter.value,
            action: action
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                showError(response.error || 'Command failed');
            }
        }
    }).fail(function(error) {
        console.error('Failed to send command:', error);
        showError('Failed to send command');
    });
}

// UI helper functions
function showResponseMessage(message, success) {
    const responseElement = $('#response-message');
    responseElement
        .removeClass('success error')
        .addClass(success ? 'success' : 'error')
        .html(message)
        .fadeIn();

    setTimeout(() => responseElement.fadeOut(), 5000);
}

function showError(message) {
    const errorElement = document.getElementById('error-message');
    const errorTextElement = document.getElementById('error-text');
    
    if (errorElement && errorTextElement) {
        errorTextElement.textContent = message;
        errorElement.style.display = 'block';
        
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    } else {
        // Fallback to alert if the error elements don't exist
        console.error(message);
    }
}
